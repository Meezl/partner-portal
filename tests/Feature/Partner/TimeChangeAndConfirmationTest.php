<?php

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Enums\PartnerStatus;
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
use Illuminate\Support\Facades\Notification;

/**
 * A time the team allocate is not settled until the partner says it works, and
 * a change request described in words moves nothing on its own.
 */
function confirmationFixture(): array
{
    $conference = Conference::factory()->active()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($partnerUser)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);
    $admin = User::factory()->admin()->create();

    $session = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    $room = Room::factory()->create(['conference_id' => $conference->id]);
    $timeSlot = TimeSlot::factory()->create(['conference_id' => $conference->id]);
    $otherSlot = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'start_time' => '16:00:00',
        'end_time' => '17:30:00',
    ]);

    return compact('conference', 'partnerUser', 'partner', 'admin', 'session', 'room', 'timeSlot', 'otherSlot');
}

it('lets a partner confirm the time the team allocated them', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'admin' => $admin, 'session' => $session,
        'room' => $room, 'timeSlot' => $timeSlot] = confirmationFixture();

    $this->actingAs($admin)
        ->post(route('admin.scheduling.assign', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $timeSlot->id,
        ])
        ->assertSessionHas('success');

    $schedule = SessionSchedule::sole();

    expect($schedule->confirmedByPartner())->toBeFalse();

    $this->actingAs($partnerUser)
        ->put(route('partner.schedule.confirm', $schedule))
        ->assertSessionHas('success');

    expect($schedule->fresh()->confirmedByPartner())->toBeTrue();
});

it('asks again when the team move a time the partner had confirmed', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'admin' => $admin, 'session' => $session,
        'room' => $room, 'timeSlot' => $timeSlot, 'otherSlot' => $otherSlot] = confirmationFixture();

    $this->actingAs($admin)
        ->post(route('admin.scheduling.assign', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $timeSlot->id,
        ]);

    $schedule = SessionSchedule::sole();

    $this->actingAs($partnerUser)->put(route('partner.schedule.confirm', $schedule));

    expect($schedule->fresh()->confirmedByPartner())->toBeTrue();

    // The team move it: the partner confirmed the old time, not this one.
    $this->actingAs($admin)
        ->put(route('admin.scheduling.update', $session), [
            'room_id' => $room->id,
            'time_slot_id' => $otherSlot->id,
        ])
        ->assertSessionHas('success');

    expect($schedule->fresh()->confirmedByPartner())->toBeFalse();
});

it('keeps one partner from confirming another partner\'s time', function () {
    ['admin' => $admin, 'session' => $session, 'room' => $room, 'timeSlot' => $timeSlot,
        'conference' => $conference] = confirmationFixture();

    $this->actingAs($admin)->post(route('admin.scheduling.assign', $session), [
        'room_id' => $room->id,
        'time_slot_id' => $timeSlot->id,
    ]);

    $stranger = User::factory()->partner()->create();
    Partner::factory()->forUser($stranger)->create(['conference_id' => $conference->id]);

    $this->actingAs($stranger)
        ->put(route('partner.schedule.confirm', SessionSchedule::sole()))
        ->assertForbidden();

    expect(SessionSchedule::sole()->confirmedByPartner())->toBeFalse();
});

it('notes a time request described in words instead of calling it approved', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'admin' => $admin, 'session' => $session,
        'partner' => $partner] = confirmationFixture();

    $this->actingAs($partnerUser)
        ->post(route('partner.change-requests.store'), [
            'conference_session_id' => $session->id,
            'type' => ChangeRequestType::Time->value,
            'requested_value' => 'Could we move to the afternoon? Our speaker lands at 11am.',
            'reason' => 'Speaker travel.',
        ])
        ->assertRedirect(route('partner.change-requests.index'));

    $changeRequest = ChangeRequest::sole();

    $this->actingAs($admin)
        ->put(route('admin.change-requests.approve', $changeRequest), [
            'resolution_notes' => 'We will look at the Tuesday slots.',
        ])
        ->assertSessionHas('success');

    // Nothing moved, so it is noted rather than approved.
    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Noted)
        ->and($session->fresh()->session_slot_id)->toBeNull();

    Notification::assertSentTo(
        $partnerUser,
        ChangeRequestNotification::class,
        function (ChangeRequestNotification $notification) use ($partnerUser) {
            $mail = $notification->toMail($partnerUser);

            return str_contains($mail->subject, 'noted')
                && collect($mail->introLines)->contains(
                    'The AHAIC partnerships team have noted your request and will action it.',
                )
                && collect($mail->introLines)->contains(
                    'Your session time has not changed yet. If the team reschedule it, you will be sent the new date and time to confirm.',
                );
        },
    );
});

it('still applies a time request raised by picking a slot', function () {
    Notification::fake();

    ['partnerUser' => $partnerUser, 'admin' => $admin, 'partner' => $partner,
        'conference' => $conference] = confirmationFixture();

    $slot = SessionSlot::factory()->create([
        'conference_id' => $conference->id,
        'slot_code' => 'Parallel 2.2',
    ]);

    // Picking a slot on the session form carries the slot id, so approving it
    // moves the session — unlike the free-text route above.
    $this->actingAs($partnerUser)
        ->post(route('partner.sessions.store'), [
            'title' => 'Picked a slot',
            'format' => 'panel',
            'session_slot_id' => $slot->id,
        ])
        ->assertRedirect(route('partner.sessions.index'));

    $changeRequest = ChangeRequest::where('partner_id', $partner->id)->sole();

    $this->actingAs($admin)
        ->put(route('admin.change-requests.approve', $changeRequest))
        ->assertSessionHas('success');

    expect($changeRequest->fresh()->status)->toBe(ChangeRequestStatus::Approved)
        ->and(ConferenceSession::where('title', 'Picked a slot')->sole()->session_slot_id)
        ->toBe($slot->id);
});
