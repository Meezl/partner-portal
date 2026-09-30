<?php

use App\Enums\PartnerStatus;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\Room;
use App\Models\SessionSlot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Room allocation follows the AHAIC matrix: a room is offered only when it
 * seats the expected headcount in the arrangement the partner asked for, and
 * only theatre style and round tables are on offer.
 */
function seatingFixture(): array
{
    $conference = Conference::factory()->active()->create(['start_date' => '2027-03-01']);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);

    // MH1 in the matrix: 500 theatre, 210 round table.
    $bigRoom = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'MH1',
        'capacity' => 500,
        'theatre_capacity' => 500,
        'round_capacity' => 210,
    ]);

    // AD11: small, and the only arrangement that matters here is round table.
    $smallRoom = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'AD11',
        'capacity' => 70,
        'theatre_capacity' => 70,
        'round_capacity' => 40,
    ]);

    // The Auditorium is never laid out with round tables ("N/A" in the matrix).
    $theatreOnlyRoom = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'Auditorium',
        'capacity' => 1200,
        'theatre_capacity' => 1200,
        'round_capacity' => null,
    ]);

    $slots = [
        'big' => SessionSlot::factory()->create([
            'conference_id' => $conference->id,
            'slot_code' => 'Parallel Big',
            'default_room_id' => $bigRoom->id,
        ]),
        'small' => SessionSlot::factory()->create([
            'conference_id' => $conference->id,
            'slot_code' => 'Parallel Small',
            'default_room_id' => $smallRoom->id,
        ]),
        'theatreOnly' => SessionSlot::factory()->create([
            'conference_id' => $conference->id,
            'slot_code' => 'Parallel Theatre Only',
            'default_room_id' => $theatreOnlyRoom->id,
        ]),
    ];

    return [$conference, $user, $partner, $slots];
}

it('offers every room with its two seating capacities so the picker can filter', function () {
    [, $user, , $slots] = seatingFixture();

    $this->actingAs($user)
        ->get(route('partner.sessions.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Sessions/Create')
            ->where('seatingArrangements', [
                ['value' => 'theatre', 'label' => 'Theatre style'],
                ['value' => 'round_table', 'label' => 'Round Tables'],
            ])
            ->has('availableSlots', 3)
            ->where('availableSlots.0.default_room.theatre_capacity', 500)
            ->where('availableSlots.0.default_room.round_capacity', 210),
        );

    expect($slots['big']->fresh()->defaultRoom->name)->toBe('MH1');
});

it('refuses a slot whose room cannot seat the group in the requested arrangement', function () {
    [, $user, , $slots] = seatingFixture();

    // MH1 seats 210 at round tables, so 300 does not fit even though the same
    // room takes 500 theatre style.
    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Financing Primary Care',
            'format' => 'workshop',
            'expected_participants' => 300,
            'session_slot_id' => $slots['big']->id,
            'special_requirements' => ['seating_type' => 'round_table'],
        ])
        ->assertSessionHasErrors('session_slot_id');

    expect(ConferenceSession::count())->toBe(0)
        ->and($slots['big']->fresh()->held_by_session_id)->toBeNull();
});

it('accepts the same room and headcount seated theatre style', function () {
    [, $user, , $slots] = seatingFixture();

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Financing Primary Care',
            'format' => 'workshop',
            'expected_participants' => 300,
            'session_slot_id' => $slots['big']->id,
            'special_requirements' => ['seating_type' => 'theatre'],
        ])
        ->assertSessionHasNoErrors();

    $session = ConferenceSession::firstOrFail();

    expect($session->requested_session_slot_id)->toBe($slots['big']->id)
        ->and($slots['big']->fresh()->held_by_session_id)->toBe($session->id);
});

it('refuses a room the venue never lays out with round tables', function () {
    [, $user, , $slots] = seatingFixture();

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Small Roundtable',
            'format' => 'roundtable',
            'expected_participants' => 20,
            'session_slot_id' => $slots['theatreOnly']->id,
            'special_requirements' => ['seating_type' => 'round_table'],
        ])
        ->assertSessionHasErrors('session_slot_id');

    expect(ConferenceSession::count())->toBe(0);
});

it('accepts only the two seating arrangements', function () {
    [, $user, , $slots] = seatingFixture();

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Classroom Please',
            'format' => 'workshop',
            'expected_participants' => 20,
            'session_slot_id' => $slots['small']->id,
            'special_requirements' => ['seating_type' => 'classroom'],
        ])
        ->assertSessionHasErrors('special_requirements.seating_type');

    expect(ConferenceSession::count())->toBe(0);
});

it('re-checks the room when a saved session raises its headcount', function () {
    [$conference, $user, $partner, $slots] = seatingFixture();

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'expected_participants' => 30,
        'special_requirements' => ['seating_type' => 'round_table'],
    ]);

    // AD11 seats 40 at round tables; 60 no longer fits.
    $this->actingAs($user)
        ->put(route('partner.sessions.update', $session), [
            'title' => $session->title,
            'format' => 'roundtable',
            'expected_participants' => 60,
            'session_slot_id' => $slots['small']->id,
            'special_requirements' => ['seating_type' => 'round_table'],
        ])
        ->assertSessionHasErrors('session_slot_id');

    expect($session->fresh()->requested_session_slot_id)->toBeNull();
});
