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
