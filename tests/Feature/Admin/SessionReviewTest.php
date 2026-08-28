<?php

use App\Enums\ChangeRequestStatus;
use App\Enums\PartnerStatus;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\ChangeRequest;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\Room;
use App\Models\SessionSchedule;
use App\Models\SessionSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\SessionReviewedNotification;
use App\Services\SessionTimeRequestService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function reviewFixture(array $sessionOverrides = []): array
{
    $conference = Conference::factory()->active()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($partnerUser)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Submitted,
    ]);

    $session = ConferenceSession::factory()->submitted()->create(array_merge([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'title' => 'Financing Primary Health Care',
    ], $sessionOverrides));

    $slotA = SessionSlot::factory()->create(['conference_id' => $conference->id, 'slot_code' => 'Parallel 1']);
    $slotB = SessionSlot::factory()->create(['conference_id' => $conference->id, 'slot_code' => 'Parallel 2']);

    return compact('conference', 'partnerUser', 'partner', 'session', 'slotA', 'slotB');
}

it('lists submitted sessions with their full details for review', function () {
    ['session' => $session] = reviewFixture([
        'description' => 'A panel on domestic health financing.',
        'organizers' => ['Amref'],
        'expected_participants' => 60,
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/sessions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sessions/Index')
            ->has('sessions', 1)
            ->where('sessions.0.title', 'Financing Primary Health Care')
            ->where('sessions.0.description', 'A panel on domestic health financing.')
            ->where('sessions.0.expected_participants', 60)
            ->where('sessions.0.partner.organization_name', $session->partner->organization_name)
            ->where('filters.status', SessionStatus::Submitted->value)
            ->has('availableSlots')
            ->has('statuses'),
        );
});

it('filters the queue by status', function () {
    ['conference' => $conference, 'partner' => $partner] = reviewFixture();

    ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'status' => SessionStatus::Draft,
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/sessions?status=draft')
        ->assertInertia(fn (Assert $page) => $page->has('sessions', 1)
            ->where('sessions.0.status', SessionStatus::Draft->value));

    $this->actingAs($admin)->get('/admin/sessions?status=all')
        ->assertInertia(fn (Assert $page) => $page->has('sessions', 2));
});

it('approves a submitted session and emails the partner', function () {
    Notification::fake();

    ['session' => $session, 'partnerUser' => $partnerUser] = reviewFixture();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post("/admin/sessions/{$session->id}/approve", ['notes' => 'Great fit for the track.'])
        ->assertSessionHas('success');

    expect($session->fresh()->status)->toBe(SessionStatus::Confirmed);

    Notification::assertSentTo($partnerUser, SessionReviewedNotification::class);
});

it('sends a session back to draft with a reason and frees its slot', function () {
    Notification::fake();

    ['session' => $session, 'slotA' => $slotA, 'partnerUser' => $partnerUser] = reviewFixture();

    $slotA->update(['claimed_by_session_id' => $session->id, 'claimed_at' => now()]);
    $session->update(['session_slot_id' => $slotA->id]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post("/admin/sessions/{$session->id}/reject", ['notes' => 'Please narrow the topic.'])
        ->assertSessionHas('success');

    $session->refresh();

    expect($session->status)->toBe(SessionStatus::Draft)
        ->and($session->submitted_at)->toBeNull()
        ->and($session->session_slot_id)->toBeNull()
        ->and($slotA->fresh()->isAvailable())->toBeTrue();

    Notification::assertSentTo($partnerUser, SessionReviewedNotification::class);
});

it('requires a reason before sending a session back', function () {
    ['session' => $session] = reviewFixture();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post("/admin/sessions/{$session->id}/reject", [])
        ->assertSessionHasErrors('notes');

    expect($session->fresh()->status)->toBe(SessionStatus::Submitted);
});

it('refuses to approve a draft or re-approve a confirmed session', function () {
    ['session' => $session] = reviewFixture(['status' => SessionStatus::Draft]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post("/admin/sessions/{$session->id}/approve")
        ->assertSessionHas('error');

    $session->update(['status' => SessionStatus::Confirmed]);

    $this->actingAs($admin)
        ->post("/admin/sessions/{$session->id}/approve")
        ->assertSessionHas('error');
});

it('updates only the title and the slot', function () {
    ['session' => $session, 'slotA' => $slotA] = reviewFixture([
        'description' => 'Untouched description.',
        'expected_participants' => 40,
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put("/admin/sessions/{$session->id}", [
            'title' => 'Renamed by the programme team',
            'session_slot_id' => $slotA->id,
            // These must be ignored — the partner owns them.
            'description' => 'HACKED',
            'expected_participants' => 9999,
        ])
        ->assertSessionHas('success');

    $session->refresh();

    expect($session->title)->toBe('Renamed by the programme team')
        ->and($session->session_slot_id)->toBe($slotA->id)
        ->and($session->description)->toBe('Untouched description.')
        ->and($session->expected_participants)->toBe(40);

    expect($slotA->fresh()->claimed_by_session_id)->toBe($session->id);
});

it('supersedes a pending partner time request when the team sets the time directly', function () {
    ['session' => $session, 'partnerUser' => $partnerUser, 'slotA' => $slotA, 'slotB' => $slotB] = reviewFixture();
    $admin = User::factory()->admin()->create();

    // Partner has a pending request on slotB.
    app(SessionTimeRequestService::class)
        ->requestSlot($session, $slotB->id, $partnerUser);

    expect($session->fresh()->requested_session_slot_id)->toBe($slotB->id)
        ->and($slotB->fresh()->held_by_session_id)->toBe($session->id);

    $this->actingAs($admin)->put("/admin/sessions/{$session->id}", [
        'title' => $session->title,
        'session_slot_id' => $slotA->id,
    ])->assertSessionHas('success');

    $session->refresh();

    expect($session->session_slot_id)->toBe($slotA->id)
        ->and($session->requested_session_slot_id)->toBeNull()
        ->and($slotB->fresh()->isAvailable())->toBeTrue();

    expect(ChangeRequest::sole()->status)->toBe(ChangeRequestStatus::AutoResolved);
});

it('rejects a slot another session already holds', function () {
    ['conference' => $conference, 'session' => $session, 'partner' => $partner, 'slotA' => $slotA] = reviewFixture();

    $other = ConferenceSession::factory()->submitted()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);
    $slotA->update(['claimed_by_session_id' => $other->id, 'claimed_at' => now()]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put("/admin/sessions/{$session->id}", [
            'title' => $session->title,
            'session_slot_id' => $slotA->id,
        ])
        ->assertSessionHasErrors('session_slot_id');

    expect($session->fresh()->session_slot_id)->toBeNull();
});

it('is reachable by partnerships but not by finance', function () {
    reviewFixture();

    $partnerships = User::factory()->create(['role' => UserRole::Partnerships, 'is_active' => true]);
    $finance = User::factory()->create(['role' => UserRole::Finance, 'is_active' => true]);

    $this->actingAs($partnerships)->get('/admin/sessions')->assertOk();
    $this->actingAs($finance)->get('/admin/sessions')->assertForbidden();
});

it('exposes the scheduling board room and time alongside the partner slot', function () {
    ['conference' => $conference, 'session' => $session, 'slotA' => $slotA] = reviewFixture();

    // The partner-facing slot the programme team approved…
    $slotA->update(['claimed_by_session_id' => $session->id, 'claimed_at' => now()]);
    $session->update(['session_slot_id' => $slotA->id]);

    // …and the separate operational booking made on the scheduling board.
    $room = Room::factory()->create([
        'conference_id' => $conference->id,
        'name' => 'Main Hall',
    ]);
    $timeSlot = TimeSlot::factory()->create([
        'conference_id' => $conference->id,
        'date' => '2027-03-02',
        'start_time' => '14:00:00',
        'end_time' => '15:30:00',
        'label' => 'Parallel Sessions PM',
    ]);
    SessionSchedule::factory()->create([
        'conference_session_id' => $session->id,
        'room_id' => $room->id,
        'time_slot_id' => $timeSlot->id,
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/sessions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessions.0.schedule.room.name', 'Main Hall')
            ->where('sessions.0.schedule.time_slot.label', 'Parallel Sessions PM')
            ->where('sessions.0.schedule.time_slot.start_time', '14:00:00')
            // A plain calendar date, not a timezone-shifted timestamp: rendering
            // the raw value must not move the session to the previous day.
            ->where('sessions.0.schedule.time_slot.date', '2027-03-02')
            // The partner-facing slot is still reported separately.
            ->where('sessions.0.session_slot.slot_code', 'Parallel 1'),
        );
});

it('reports no board booking when the session is not on the board', function () {
    reviewFixture();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/sessions')
        ->assertInertia(fn (Assert $page) => $page->where('sessions.0.schedule', null));
});
