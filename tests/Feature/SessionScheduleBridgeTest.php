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
use App\Services\SessionScheduleSynchroniser;
use App\Services\SessionTimeRequestService;
use Inertia\Testing\AssertableInertia;

function bridgeFixture(): array
{
    $conference = Conference::factory()->active()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($partnerUser)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);
    $admin = User::factory()->admin()->create();

    $roomA = Room::factory()->create(['conference_id' => $conference->id, 'name' => 'MH1', 'capacity' => 500]);
    $roomB = Room::factory()->create(['conference_id' => $conference->id, 'name' => 'MH2', 'capacity' => 500]);

    $slotA = SessionSlot::factory()->create([
        'conference_id' => $conference->id,
        'slot_code' => 'Parallel 1',
        'date' => '2027-03-01',
        'start_time' => '11:30:00',
        'end_time' => '13:00:00',
        'default_room_id' => $roomA->id,
    ]);
    $slotB = SessionSlot::factory()->create([
        'conference_id' => $conference->id,
        'slot_code' => 'Parallel 2',
        'date' => '2027-03-02',
        'start_time' => '14:00:00',
        'end_time' => '15:30:00',
        'default_room_id' => $roomB->id,
    ]);

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'status' => SessionStatus::Draft,
    ]);

    return compact('conference', 'partner', 'partnerUser', 'admin', 'roomA', 'roomB', 'slotA', 'slotB', 'session');
}

/** The board booking a session currently holds, as a comparable string. */
function boardPlacement(ConferenceSession $session): ?string
{
    $schedule = $session->fresh('schedule.room', 'schedule.timeSlot')->schedule;

    if (! $schedule) {
        return null;
    }

    return $schedule->room->name.' '
        .$schedule->timeSlot->date->toDateString().' '
        .$schedule->timeSlot->start_time.'-'.$schedule->timeSlot->end_time;
}

it('derives the board booking from the slot when a time request is approved', function () {
    ['session' => $session, 'slotA' => $slotA, 'partnerUser' => $user, 'admin' => $admin] = bridgeFixture();

    $service = app(SessionTimeRequestService::class);
    $request = $service->requestSlot($session, $slotA->id, $user);

    // Nothing on the board while it is only a request.
    expect(boardPlacement($session))->toBeNull();

    $service->approve($request, $admin);

    expect(boardPlacement($session))->toBe('MH1 2027-03-01 11:30:00-13:00:00');
});

it('creates a TimeSlot for a slot window the conference does not define', function () {
    ['session' => $session, 'slotA' => $slotA, 'partnerUser' => $user, 'admin' => $admin] = bridgeFixture();

    expect(TimeSlot::count())->toBe(0);

    $service = app(SessionTimeRequestService::class);
    $service->approve($service->requestSlot($session, $slotA->id, $user), $admin);

    $timeSlot = TimeSlot::sole();

    expect($timeSlot->date->toDateString())->toBe('2027-03-01')
        ->and($timeSlot->start_time)->toBe('11:30:00')
        ->and($timeSlot->end_time)->toBe('13:00:00');

    // A second session in the same window reuses it rather than duplicating.
    $other = ConferenceSession::factory()->create([
        'partner_id' => $session->partner_id,
        'conference_id' => $session->conference_id,
    ]);
    $slotA->update(['claimed_by_session_id' => null, 'claimed_at' => null]);
    app(SessionScheduleSynchroniser::class)->sync(
        tap($other)->update(['session_slot_id' => $slotA->id])
    );

    expect(TimeSlot::count())->toBe(1);
});

it('moves the board booking when the approved slot changes', function () {
    ['session' => $session, 'slotA' => $slotA, 'slotB' => $slotB, 'admin' => $admin] = bridgeFixture();

    $service = app(SessionTimeRequestService::class);

    $service->assignSlotDirectly($session, $slotA->id, $admin);
    expect(boardPlacement($session))->toBe('MH1 2027-03-01 11:30:00-13:00:00');

    $service->assignSlotDirectly($session->refresh(), $slotB->id, $admin);
    expect(boardPlacement($session))->toBe('MH2 2027-03-02 14:00:00-15:30:00');

    // Still exactly one booking — it moved, it did not duplicate.
    expect(SessionSchedule::where('conference_session_id', $session->id)->count())->toBe(1);
});

it('removes the board booking when the slot is released', function () {
    ['session' => $session, 'slotA' => $slotA, 'admin' => $admin] = bridgeFixture();

    $service = app(SessionTimeRequestService::class);
    $service->assignSlotDirectly($session, $slotA->id, $admin);
    expect(boardPlacement($session))->not->toBeNull();

    $service->assignSlotDirectly($session->refresh(), null, $admin);
    expect(boardPlacement($session))->toBeNull();
});

it('grants the held slot and books the room when the partner submits', function () {
    ['session' => $session, 'slotA' => $slotA, 'partnerUser' => $user] = bridgeFixture();

    app(SessionTimeRequestService::class)->requestSlot($session, $slotA->id, $user);

    expect($session->fresh()->session_slot_id)->toBeNull()
        ->and($session->fresh()->requested_session_slot_id)->toBe($slotA->id);

    app(SessionTimeRequestService::class)->grantPendingOnSubmission($session->refresh());

    $session->refresh();

    expect($session->session_slot_id)->toBe($slotA->id)
        ->and($session->requested_session_slot_id)->toBeNull()
        ->and(boardPlacement($session))->toBe('MH1 2027-03-01 11:30:00-13:00:00');

    expect($slotA->fresh()->claimed_by_session_id)->toBe($session->id)
        ->and($slotA->fresh()->held_by_session_id)->toBeNull();

    expect(ChangeRequest::sole()->status)->toBe(ChangeRequestStatus::Approved);
});

it('re-points the slot when a board move lands on a matching slot', function () {
    ['session' => $session, 'slotA' => $slotA, 'slotB' => $slotB, 'admin' => $admin, 'roomB' => $roomB] = bridgeFixture();

    $session->update(['status' => SessionStatus::Submitted]);
    app(SessionTimeRequestService::class)->assignSlotDirectly($session, $slotA->id, $admin);

    // Move on the board to exactly slotB's room + window.
    $timeSlotB = TimeSlot::firstOrCreate([
        'conference_id' => $session->conference_id,
        'date' => '2027-03-02',
        'start_time' => '14:00:00',
        'end_time' => '15:30:00',
    ], ['label' => 'PM', 'slot_type' => 'parallel']);

    test()->actingAs($admin)
        ->put(route('admin.scheduling.update', $session), [
            'room_id' => $roomB->id,
            'time_slot_id' => $timeSlotB->id,
        ])
        ->assertSessionHas('success');

    // The session followed the board onto the matching slot — no drift.
    expect($session->fresh()->session_slot_id)->toBe($slotB->id)
        ->and($slotA->fresh()->isAvailable())->toBeTrue()
        ->and($slotB->fresh()->claimed_by_session_id)->toBe($session->id);
});

it('releases the slot when a board move has no matching slot, so the two cannot disagree', function () {
    ['conference' => $conference, 'session' => $session, 'slotA' => $slotA, 'admin' => $admin, 'roomB' => $roomB] = bridgeFixture();

    $session->update(['status' => SessionStatus::Submitted]);
    app(SessionTimeRequestService::class)->assignSlotDirectly($session, $slotA->id, $admin);

    // A window no SessionSlot describes.
    $oddSlot = TimeSlot::create([
        'conference_id' => $conference->id,
        'date' => '2027-03-03',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'label' => 'Ad hoc',
        'slot_type' => 'other',
    ]);

    test()->actingAs($admin)
        ->put(route('admin.scheduling.update', $session), [
            'room_id' => $roomB->id,
            'time_slot_id' => $oddSlot->id,
        ])
        ->assertSessionHas('success');

    $session->refresh();

    // The board wins and the slot is freed: the session holds exactly one time.
    expect($session->session_slot_id)->toBeNull()
        ->and($slotA->fresh()->isAvailable())->toBeTrue()
        ->and(boardPlacement($session))->toBe('MH2 2027-03-03 09:00:00-10:00:00');
});

it('frees the slot when the board booking is removed', function () {
    ['session' => $session, 'slotA' => $slotA, 'admin' => $admin] = bridgeFixture();

    $session->update(['status' => SessionStatus::Submitted]);
    app(SessionTimeRequestService::class)->assignSlotDirectly($session, $slotA->id, $admin);

    test()->actingAs($admin)
        ->delete(route('admin.scheduling.destroy', $session))
        ->assertSessionHas('success');

    $session->refresh();

    expect($session->session_slot_id)->toBeNull()
        ->and(boardPlacement($session))->toBeNull()
        ->and($slotA->fresh()->isAvailable())->toBeTrue();
});

it('never leaves a session holding a slot and a booking that disagree', function () {
    ['session' => $session, 'slotA' => $slotA, 'slotB' => $slotB, 'admin' => $admin] = bridgeFixture();

    $service = app(SessionTimeRequestService::class);

    foreach ([$slotA, $slotB, $slotA] as $slot) {
        $service->assignSlotDirectly($session->refresh(), $slot->id, $admin);

        $session->refresh()->load('sessionSlot.defaultRoom', 'schedule.room', 'schedule.timeSlot');

        expect($session->schedule->room_id)->toBe($session->sessionSlot->default_room_id)
            ->and($session->schedule->timeSlot->date->toDateString())
            ->toBe($session->sessionSlot->date->toDateString())
            ->and($session->schedule->timeSlot->start_time)->toBe($session->sessionSlot->start_time)
            ->and($session->schedule->timeSlot->end_time)->toBe($session->sessionSlot->end_time);
    }
});

it('shows the partner their board booking when a board move released the slot', function () {
    ['conference' => $conference, 'session' => $session, 'partner' => $partner,
        'partnerUser' => $user, 'slotA' => $slotA, 'admin' => $admin, 'roomB' => $roomB] = bridgeFixture();

    $session->update(['status' => SessionStatus::Submitted]);
    app(SessionTimeRequestService::class)->assignSlotDirectly($session, $slotA->id, $admin);

    // Admin moves it to a window no slot describes — the slot is released.
    $oddSlot = TimeSlot::create([
        'conference_id' => $conference->id,
        'date' => '2027-03-03',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'label' => 'Ad hoc',
        'slot_type' => 'other',
    ]);

    test()->actingAs($admin)->put(route('admin.scheduling.update', $session), [
        'room_id' => $roomB->id,
        'time_slot_id' => $oddSlot->id,
    ]);

    expect($session->fresh()->session_slot_id)->toBeNull();

    // The partner's own sessions page still receives the room and time, so the
    // session cannot look unscheduled while it is actually booked.
    test()->actingAs($user)
        ->get('/partner/sessions')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sessions.0.session_slot', null)
            ->where('sessions.0.schedule.room.name', 'MH2')
            ->where('sessions.0.schedule.time_slot.date', '2027-03-03')
            ->where('sessions.0.schedule.time_slot.start_time', '09:00:00')
            ->where('sessions.0.schedule.time_slot.label', 'Ad hoc'),
        );
});

it('shows the board booking on the partner edit page when the slot was released', function () {
    ['conference' => $conference, 'session' => $session, 'partnerUser' => $user,
        'slotA' => $slotA, 'admin' => $admin, 'roomB' => $roomB] = bridgeFixture();

    $session->update(['status' => SessionStatus::Submitted]);
    app(SessionTimeRequestService::class)->assignSlotDirectly($session, $slotA->id, $admin);

    $oddSlot = TimeSlot::create([
        'conference_id' => $conference->id,
        'date' => '2027-03-03',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'label' => 'Ad hoc',
        'slot_type' => 'other',
    ]);

    test()->actingAs($admin)->put(route('admin.scheduling.update', $session), [
        'room_id' => $roomB->id,
        'time_slot_id' => $oddSlot->id,
    ]);

    expect($session->fresh()->session_slot_id)->toBeNull();

    test()->actingAs($user)
        ->get("/partner/sessions/{$session->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Partner/Sessions/Edit')
            ->where('session.session_slot', null)
            ->where('session.schedule.room.name', 'MH2')
            ->where('session.schedule.time_slot.date', '2027-03-03')
            ->where('session.schedule.time_slot.label', 'Ad hoc'),
        );
});

it('still exposes the slot room on the edit page in the normal case', function () {
    ['session' => $session, 'partnerUser' => $user, 'slotA' => $slotA, 'admin' => $admin] = bridgeFixture();

    app(SessionTimeRequestService::class)->assignSlotDirectly($session, $slotA->id, $admin);

    test()->actingAs($user)
        ->get("/partner/sessions/{$session->id}/edit")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session.session_slot.slot_code', 'Parallel 1')
            ->where('session.session_slot.default_room.name', 'MH1')
            // Slot and booking agree, so the panel shows one consistent time.
            ->where('session.schedule.room.name', 'MH1'),
        );
});
