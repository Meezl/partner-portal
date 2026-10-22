<?php

use App\Enums\ChangeRequestStatus;
use App\Enums\PartnerStatus;
use App\Enums\SessionStatus;
use App\Models\ChangeRequest;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\Room;
use App\Models\SessionSchedule;
use App\Models\SessionSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\ChangeRequestNotification;
use App\Notifications\SessionScheduledNotification;
use App\Notifications\SessionUnscheduledNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The whole journey, end to end: a partner picks a date and time, the
 * partnerships team approve it, and an admin allocates the room on the
 * scheduling board. Partners never choose a room — that is the team's call —
 * so this covers who is told what at each step.
 */
function journeyFixture(): array
{
    $conference = Conference::factory()->active()->create(['start_date' => '2027-02-28']);

    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($partnerUser)->create([
        'conference_id' => $conference->id,
        'organization_name' => 'Journey Org',
        'status' => PartnerStatus::Confirmed,
    ]);

    $partnerships = User::factory()->partnerships()->create();
    $admin = User::factory()->admin()->create();

    $slot = SessionSlot::factory()->create([
        'conference_id' => $conference->id,
        'slot_code' => 'Parallel 1.1',
        'day_index' => 1,
        'date' => '2027-03-01',
        'time_label' => '11:30-13:00',
    ]);

    $room = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'MH1',
        'format_suitability' => ['panel', 'roundtable'],
    ]);

    $timeSlot = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'date' => '2027-03-01',
        'start_time' => '11:30:00',
        'end_time' => '13:00:00',
    ]);

    return compact(
        'conference', 'partnerUser', 'partner', 'partnerships', 'admin', 'slot', 'room', 'timeSlot',
    );
}

it('carries a session from a partner picking a time to an admin allocating the room', function () {
    Notification::fake();

    [
        'partnerUser' => $partnerUser,
        'partner' => $partner,
        'partnerships' => $partnerships,
        'admin' => $admin,
        'slot' => $slot,
        'room' => $room,
        'timeSlot' => $timeSlot,
    ] = journeyFixture();

    // 1. The partner proposes a session and a preferred date and time. No room
    //    is offered to them anywhere in the payload.
    $this->actingAs($partnerUser)
        ->post(route('partner.sessions.store'), [
            'title' => 'Financing Primary Health Care',
            'description' => 'A panel on domestic health financing.',
            'format' => 'panel',
            'expected_participants' => '50-80',
            'session_slot_id' => $slot->id,
            'slot_reason' => 'Our minister is only free on day one.',
        ])
        ->assertRedirect(route('partner.sessions.index'));

    $session = ConferenceSession::where('partner_id', $partner->id)->sole();

    // Requested, not booked: the slot is held until the team decide.
    expect($session->requested_session_slot_id)->toBe($slot->id)
        ->and($session->session_slot_id)->toBeNull()
        ->and($slot->fresh()->held_by_session_id)->toBe($session->id);

    // 2. The partnerships team approve the requested time.
    $changeRequest = ChangeRequest::where('conference_session_id', $session->id)->sole();

    $this->actingAs($partnerships)
        ->put(route('admin.change-requests.approve', $changeRequest))
        ->assertRedirect();

    $session->refresh();

    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Approved)
        ->and($session->session_slot_id)->toBe($slot->id)
        ->and($session->requested_session_slot_id)->toBeNull()
        ->and($slot->fresh()->claimed_by_session_id)->toBe($session->id);

    // The partner hears about the decision.
    Notification::assertSentTo($partnerUser, ChangeRequestNotification::class);

    // 3. A draft is not on the board yet — the board only shows sessions that
    //    reached the programme.
    $this->actingAs($admin)
        ->get(route('admin.scheduling.sessions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('sessions', 0));

    // The partner's final submission marks it submitted; that flow has its own
    // test, so this takes the session to the same place.
    $session->update(['status' => SessionStatus::Submitted, 'submitted_at' => now()]);

    $this->actingAs($admin)
        ->get(route('admin.scheduling.sessions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Scheduling/Sessions')
            ->has('sessions', 1)
            ->where('sessions.0.id', $session->id),
        );

    // 4. The admin allocates a room and time on the board.
    $this->actingAs($admin)
        ->post(route('admin.scheduling.assign', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $timeSlot->id,
        ])
        ->assertSessionHas('success');

    $schedule = SessionSchedule::where('conference_session_id', $session->id)->sole();

    expect($schedule->room_id)->toBe($room->id)
        ->and($schedule->time_slot_id)->toBe($timeSlot->id)
        ->and($schedule->assigned_by)->toBe($admin->id)
        ->and($session->fresh()->status)->toBe(SessionStatus::Scheduled);

    // The partner is told which room they are in.
    Notification::assertSentTo(
        $partnerUser,
        SessionScheduledNotification::class,
        function (SessionScheduledNotification $notification) use ($partnerUser, $room) {
            $mail = $notification->toMail($partnerUser);

            return collect([...$mail->introLines, ...$mail->outroLines])
                ->contains(fn (string $line) => str_contains($line, $room->name));
        },
    );

    // 5. The allocation matrix shows the session sitting in that room and time.
    $this->actingAs($admin)
        ->get(route('admin.scheduling.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Scheduling/Index')
            ->where('rooms.0.name', 'MH1')
            ->where('allocationSummary.scheduled_sessions', 1)
            ->where('allocationDays.0.slots.0.cells.0.schedule.session.title', 'Financing Primary Health Care')
            ->where('allocationDays.0.slots.0.cells.0.schedule.session.partner_name', 'Journey Org')
            ->where('allocationDays.0.slots.0.cells.0.room_name', 'MH1')
            // Nothing flags the room as unsuitable: the format matches and
            // seats are no longer part of the decision.
            ->where('allocationDays.0.slots.0.cells.0.schedule.fit_warnings', []),
        );
});

it('tells the partner when the team take the room back', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'partner' => $partner, 'admin' => $admin,
        'conference' => $conference, 'room' => $room, 'timeSlot' => $timeSlot] = journeyFixture();

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.scheduling.assign', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $timeSlot->id,
        ])
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->delete(route('admin.scheduling.destroy', $session))
        ->assertSessionHas('success');

    expect(SessionSchedule::where('conference_session_id', $session->id)->count())->toBe(0);

    Notification::assertSentTo($partnerUser, SessionUnscheduledNotification::class);
});

it('keeps a partner away from the room allocation screens', function () {
    ['partnerUser' => $partnerUser] = journeyFixture();

    $this->actingAs($partnerUser)
        ->get(route('admin.scheduling.index'))
        ->assertForbidden();
});

it('tells the partner when the team move their time on the board', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'partner' => $partner, 'admin' => $admin,
        'conference' => $conference, 'room' => $room, 'timeSlot' => $timeSlot] = journeyFixture();

    $later = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'date' => '2027-03-02',
        'start_time' => '14:00:00',
        'end_time' => '15:30:00',
    ]);

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.scheduling.assign', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $timeSlot->id,
        ])
        ->assertSessionHas('success');

    // Same room, a different time: the team can move the time after allocating.
    $this->actingAs($admin)
        ->put(route('admin.scheduling.update', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $later->id,
        ])
        ->assertSessionHas('success');

    expect(SessionSchedule::where('conference_session_id', $session->id)->sole()->time_slot_id)
        ->toBe($later->id);

    // Once for the allocation, once for the move.
    Notification::assertSentToTimes($partnerUser, SessionScheduledNotification::class, 2);
});

it('tells the partner when the team move their time from the review screen', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'partner' => $partner, 'admin' => $admin,
        'conference' => $conference, 'slot' => $slot, 'room' => $room] = journeyFixture();

    // The slot carries a room, so the board booking is derived from it.
    $slot->update(['default_room_id' => $room->id]);

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.sessions.update', $session), [
            'title' => $session->title,
            'session_slot_id' => $slot->id,
        ])
        ->assertSessionHas('success');

    expect($session->fresh()->session_slot_id)->toBe($slot->id)
        // The booking follows the slot: the slot is the source of truth.
        ->and(SessionSchedule::where('conference_session_id', $session->id)->sole()->room_id)
        ->toBe($room->id);

    Notification::assertSentTo($partnerUser, SessionScheduledNotification::class);
});

it('tells the partner when the team clear their time from the review screen', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'partner' => $partner, 'admin' => $admin,
        'conference' => $conference, 'slot' => $slot, 'room' => $room] = journeyFixture();

    $slot->update(['default_room_id' => $room->id]);

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.sessions.update', $session), [
            'title' => $session->title,
            'session_slot_id' => $slot->id,
        ])
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->put(route('admin.sessions.update', $session), [
            'title' => $session->title,
            'session_slot_id' => null,
        ])
        ->assertSessionHas('success');

    expect($session->fresh()->session_slot_id)->toBeNull()
        ->and(SessionSchedule::where('conference_session_id', $session->id)->count())->toBe(0);

    Notification::assertSentTo($partnerUser, SessionUnscheduledNotification::class);
});

it('says nothing to the partner when the team edit only the title', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'partner' => $partner, 'admin' => $admin,
        'conference' => $conference] = journeyFixture();

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.sessions.update', $session), [
            'title' => 'Retitled by the programme team',
            'session_slot_id' => null,
        ])
        ->assertSessionHas('success');

    Notification::assertNotSentTo($partnerUser, SessionScheduledNotification::class);
    Notification::assertNotSentTo($partnerUser, SessionUnscheduledNotification::class);
});

it('words a first placement as scheduled and a move as a change to the requested time', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'partner' => $partner, 'admin' => $admin,
        'conference' => $conference, 'room' => $room, 'timeSlot' => $timeSlot] = journeyFixture();

    $later = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'date' => '2027-03-02',
        'start_time' => '14:00:00',
        'end_time' => '15:30:00',
    ]);

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.scheduling.assign', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $timeSlot->id,
        ])
        ->assertSessionHas('success');

    $this->actingAs($admin)
        ->put(route('admin.scheduling.update', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $later->id,
        ])
        ->assertSessionHas('success');

    $mails = Notification::sent($partnerUser, SessionScheduledNotification::class)
        ->map(fn (SessionScheduledNotification $notification) => $notification->toMail($partnerUser));

    // The first placement is not a change to anything.
    expect($mails->first()->subject)->toBe('Your Session Has Been Scheduled')
        ->and(collect($mails->first()->introLines))
        ->toContain('Your session has been scheduled for the AHAIC conference.');

    // The move says so, and says where it was.
    $moved = $mails->last();

    expect($moved->subject)->toBe('Your requested session time has been changed')
        ->and(collect($moved->introLines))
        ->toContain('The AHAIC programme team have changed the date and time you requested for your session.')
        ->toContain('Now scheduled for:')
        ->toContain('Previously: MH1 on Mon 1 Mar, 11:30–13:00')
        ->toContain('Please confirm this works for you from your schedule page.')
        ->and(collect($moved->outroLines))
        ->toContain('If this time does not work for you, please submit a change request through the portal instead of confirming.');
});

it('does not call the first slot the team set a change to the requested time', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'partner' => $partner, 'admin' => $admin,
        'conference' => $conference, 'slot' => $slot, 'room' => $room] = journeyFixture();

    $slot->update(['default_room_id' => $room->id]);

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    // The session had no slot, so this is the team setting one, not moving it.
    $this->actingAs($admin)
        ->put(route('admin.sessions.update', $session), [
            'title' => $session->title,
            'session_slot_id' => $slot->id,
        ])
        ->assertSessionHas('success');

    $mail = Notification::sent($partnerUser, SessionScheduledNotification::class)
        ->first()
        ->toMail($partnerUser);

    expect($mail->subject)->toBe('Your Session Has Been Scheduled');
});
