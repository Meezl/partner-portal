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
 * Slots are offered without regard to room setup or seat counts: a session
 * declares a headcount band, and the programme team lay the room out to suit.
 */
function availableSlotFixture(): array
{
    $conference = Conference::factory()->active()->create(['start_date' => '2027-03-01']);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);

    $bigRoom = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'MH1',
        'capacity' => 500,
    ]);

    $tinyRoom = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'AD8',
        'capacity' => 10,
    ]);

    $slots = [
        'big' => SessionSlot::factory()->create([
            'conference_id' => $conference->id,
            'slot_code' => 'Parallel Big',
            'default_room_id' => $bigRoom->id,
        ]),
        'tiny' => SessionSlot::factory()->create([
            'conference_id' => $conference->id,
            'slot_code' => 'Parallel Tiny',
            'default_room_id' => $tinyRoom->id,
        ]),
    ];

    return [$conference, $user, $partner, $slots];
}

it('offers the headcount bands and every slot, whatever the room seats', function () {
    [, $user] = availableSlotFixture();

    $this->actingAs($user)
        ->get(route('partner.sessions.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Sessions/Create')
            ->missing('seatingArrangements')
            ->where('participantRanges.0', ['value' => '5-10', 'label' => '5-10'])
            ->where('participantRanges.7', ['value' => 'over 150', 'label' => 'Over 150'])
            ->has('availableSlots', 2),
        );
});

it('accepts a slot whose room is smaller than the headcount band', function () {
    [, $user, , $slots] = availableSlotFixture();

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Financing Primary Care',
            'format' => 'workshop',
            'expected_participants' => 'over 150',
            'session_slot_id' => $slots['tiny']->id,
        ])
        ->assertSessionHasNoErrors();

    $session = ConferenceSession::firstOrFail();

    expect($session->requested_session_slot_id)->toBe($slots['tiny']->id)
        ->and($slots['tiny']->fresh()->held_by_session_id)->toBe($session->id);
});

it('keeps the slot when a saved session raises its headcount band', function () {
    [$conference, $user, $partner, $slots] = availableSlotFixture();

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'expected_participants' => '15-30',
    ]);

    $this->actingAs($user)
        ->put(route('partner.sessions.update', $session), [
            'title' => $session->title,
            'format' => 'roundtable',
            'expected_participants' => 'over 150',
            'session_slot_id' => $slots['tiny']->id,
        ])
        ->assertSessionHasNoErrors();

    expect($session->fresh()->requested_session_slot_id)->toBe($slots['tiny']->id);
});

it('refuses a headcount that is not one of the bands', function () {
    [, $user, , $slots] = availableSlotFixture();

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Some Session',
            'format' => 'workshop',
            'expected_participants' => '42',
            'session_slot_id' => $slots['big']->id,
        ])
        ->assertSessionHasErrors('expected_participants');

    expect(ConferenceSession::count())->toBe(0);
});

it('caps the session description at 150 words', function () {
    [, $user, , $slots] = availableSlotFixture();

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Some Session',
            'format' => 'workshop',
            'description' => str_repeat('word ', 151),
            'session_slot_id' => $slots['big']->id,
        ])
        ->assertSessionHasErrors('description');

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Some Session',
            'format' => 'workshop',
            'description' => str_repeat('word ', 150),
            'session_slot_id' => $slots['big']->id,
        ])
        ->assertSessionHasNoErrors();

    expect(ConferenceSession::count())->toBe(1);
});
