<?php

use App\Enums\PartnerStatus;
use App\Enums\SessionStatus;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\ResourceAssignment;
use App\Models\Room;
use App\Models\SessionSchedule;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\SessionScheduledNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function schedulingFixture(): array
{
    $conference = Conference::factory()->active()->create();
    $admin = User::factory()->admin()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::factory()
        ->forUser($partnerUser)
        ->create([
            'conference_id' => $conference->id,
            'status' => PartnerStatus::Submitted,
        ]);

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $roomA = Room::factory()->create(['conference_id' => $conference->id, 'name' => 'Main Hall']);
    $roomB = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'Breakout Room',
        'capacity' => 220,
        'format_suitability' => ['panel', 'workshop'],
    ]);
    $slotA = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'date' => '2027-03-02',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
    ]);
    $slotB = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'date' => '2027-03-02',
        'start_time' => '10:30:00',
        'end_time' => '11:30:00',
    ]);

    return compact('conference', 'admin', 'partnerUser', 'partner', 'session', 'roomA', 'roomB', 'slotA', 'slotB');
}

it('renders the scheduling board and schedules a submitted session', function () {
    Notification::fake();

    [
        'admin' => $admin,
        'partnerUser' => $partnerUser,
        'session' => $session,
        'roomA' => $roomA,
        'slotA' => $slotA,
    ] = schedulingFixture();

    $this
        ->actingAs($admin)
        ->get(route('admin.scheduling.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Scheduling/Index')
            ->has('sessions', 1)
            ->has('rooms', 2)
            ->has('timeSlots', 2)
            ->has('schedules', 0)
            ->where('allocationSummary.active_rooms', 2)
            ->where('allocationSummary.unscheduled_sessions', 1)
            ->has('allocationDays', 1)
            ->where('allocationDays.0.slot_count', 2)
            ->where('allocationDays.0.scheduled_sessions', 0)
            ->has('roomStats', 2));

    $this
        ->actingAs($admin)
        ->get(route('admin.scheduling.sessions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Scheduling/Sessions')
            ->has('sessions', 1)
            ->where('sessions.0.id', $session->id));

    $this
        ->actingAs($admin)
        ->post(route('admin.scheduling.assign', $session), [
            'room_id' => $roomA->id,
            'time_slot_id' => $slotA->id,
        ])
        ->assertRedirect();

    $schedule = SessionSchedule::firstOrFail();

    expect($schedule->conference_session_id)->toBe($session->id)
        ->and($schedule->assigned_by)->toBe($admin->id)
        ->and($schedule->status)->toBe('scheduled')
        ->and($session->fresh()->status)->toBe(SessionStatus::Scheduled);

    Notification::assertSentTo($partnerUser, SessionScheduledNotification::class);
});

it('prevents room conflicts and partner overlap conflicts while scheduling', function () {
    [
        'conference' => $conference,
        'admin' => $admin,
        'partner' => $partner,
        'session' => $session,
        'roomA' => $roomA,
        'roomB' => $roomB,
        'slotA' => $slotA,
    ] = schedulingFixture();

    SessionSchedule::factory()->create([
        'conference_session_id' => $session->id,
        'room_id' => $roomA->id,
        'time_slot_id' => $slotA->id,
    ]);

    $otherPartner = Partner::factory()->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Submitted,
    ]);

    $roomConflictSession = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $otherPartner->id,
        'conference_id' => $conference->id,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.scheduling.assign', $roomConflictSession), [
            'room_id' => $roomA->id,
            'time_slot_id' => $slotA->id,
        ])
        ->assertSessionHas('error');

    $partnerOverlapSession = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.scheduling.assign', $partnerOverlapSession), [
            'room_id' => $roomB->id,
            'time_slot_id' => $slotA->id,
        ])
        ->assertSessionHas('error');
});

it('prevents assigning sessions into rooms that do not fit capacity or supported format', function () {
    [
        'conference' => $conference,
        'admin' => $admin,
        'partner' => $partner,
        'roomA' => $roomA,
        'slotA' => $slotA,
        'slotB' => $slotB,
    ] = schedulingFixture();

    $largeSession = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'expected_participants' => $roomA->capacity + 25,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.scheduling.assign', $largeSession), [
            'room_id' => $roomA->id,
            'time_slot_id' => $slotA->id,
        ])
        ->assertSessionHas('error', 'Expected attendance (145) exceeds room capacity (120).');

    $formatRestrictedRoom = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'Plenary Hall',
        'capacity' => 600,
        'format_suitability' => ['plenary'],
    ]);

    $panelSession = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'format' => \App\Enums\SessionFormat::Panel,
        'expected_participants' => 80,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.scheduling.assign', $panelSession), [
            'room_id' => $formatRestrictedRoom->id,
            'time_slot_id' => $slotB->id,
        ])
        ->assertSessionHas('error', 'Room is not marked suitable for Panel sessions.');
});

it('updates schedules, exposes conflicts, and supports resource assignments', function () {
    Notification::fake();

    [
        'conference' => $conference,
        'admin' => $admin,
        'partner' => $partner,
        'session' => $session,
        'roomA' => $roomA,
        'roomB' => $roomB,
        'slotA' => $slotA,
        'slotB' => $slotB,
    ] = schedulingFixture();

    $schedule = SessionSchedule::factory()->create([
        'conference_session_id' => $session->id,
        'room_id' => $roomA->id,
        'time_slot_id' => $slotA->id,
        'status' => 'scheduled',
    ]);

    $session->update(['status' => SessionStatus::Scheduled]);

    $this
        ->actingAs($admin)
        ->put(route('admin.scheduling.update', $session), [
            'room_id' => $roomB->id,
            'time_slot_id' => $slotB->id,
        ])
        ->assertRedirect();

    expect($schedule->fresh()->room_id)->toBe($roomB->id)
        ->and($schedule->fresh()->time_slot_id)->toBe($slotB->id);

    $conflictPartner = Partner::factory()->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Submitted,
    ]);

    $conflictSessionA = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $conflictPartner->id,
        'conference_id' => $conference->id,
    ]);
    $conflictSessionB = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $conflictPartner->id,
        'conference_id' => $conference->id,
    ]);

    SessionSchedule::factory()->create([
        'conference_session_id' => $conflictSessionA->id,
        'room_id' => $roomA->id,
        'time_slot_id' => $slotA->id,
    ]);
    SessionSchedule::factory()->create([
        'conference_session_id' => $conflictSessionB->id,
        'room_id' => $roomA->id,
        'time_slot_id' => $slotA->id,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.scheduling.conflicts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Scheduling/Conflicts')
            ->has('conflicts')
            ->where('conflicts.0.type', 'room_double_booking'));

    $this
        ->actingAs($admin)
        ->get(route('admin.resources.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Resources/Index')
            ->has('sessions')
            ->has('assignments'));

    $this
        ->actingAs($admin)
        ->post(route('admin.resources.store'), [
            'session_schedule_id' => $schedule->id,
            'resource_type' => 'moderator',
            'name' => 'Jane Moderator',
            'email' => 'moderator@example.org',
        ])
        ->assertRedirect();

    $assignment = ResourceAssignment::firstOrFail();

    expect($assignment->assigned_by)->toBe($admin->id);

    $otherScheduleSession = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);
    $otherSchedule = SessionSchedule::factory()->create([
        'conference_session_id' => $otherScheduleSession->id,
        'room_id' => $roomA->id,
        'time_slot_id' => $slotB->id,
        'status' => 'scheduled',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.resources.store'), [
            'session_schedule_id' => $otherSchedule->id,
            'resource_type' => 'moderator',
            'name' => 'Jane Moderator',
            'email' => 'moderator@example.org',
        ])
        ->assertSessionHas('error');

    $this
        ->actingAs($admin)
        ->post(route('admin.rooms.store'), [
            'name' => 'Media Room',
            'building' => 'Annex',
            'floor' => '2',
            'capacity' => 40,
            'format_suitability' => ['panel', 'side_event'],
            'equipment' => "projector: yes\nmicrophone: 2",
            'is_active' => true,
        ])
        ->assertRedirect();

    $room = Room::where('name', 'Media Room')->firstOrFail();

    expect($room->conference_id)->toBe($conference->id)
        ->and($room->equipment['projector'])->toBe('yes')
        ->and($room->format_suitability)->toBe(['panel', 'side_event']);
});
