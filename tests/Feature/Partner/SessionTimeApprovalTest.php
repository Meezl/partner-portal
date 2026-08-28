<?php

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Enums\PartnerStatus;
use App\Enums\UserRole;
use App\Models\ChangeRequest;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\SessionSlot;
use App\Models\User;
use App\Notifications\ChangeRequestNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function sessionTimeFixture(): array
{
    $conference = Conference::factory()->active()->create(['start_date' => '2027-03-01']);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);

    $admin = User::factory()->admin()->create();

    $slotA = SessionSlot::factory()->create([
        'conference_id' => $conference->id,
        'slot_code' => 'Parallel 1',
        'day_index' => 1,
        'date' => '2027-03-02',
        'time_label' => '11:00-12:30',
    ]);

    $slotB = SessionSlot::factory()->create([
        'conference_id' => $conference->id,
        'slot_code' => 'Parallel 2',
        'day_index' => 2,
        'date' => '2027-03-03',
        'time_label' => '14:00-15:30',
    ]);

    return compact('conference', 'user', 'partner', 'admin', 'slotA', 'slotB');
}

function sessionPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Financing Primary Health Care',
        'description' => 'A panel on domestic health financing.',
        'format' => 'panel',
        'target_audience' => 'Policy makers',
        'expected_participants' => 60,
        'is_open' => true,
    ], $overrides);
}

it('offers only bookable slots, with conference dates, on the create page', function () {
    ['user' => $user, 'slotA' => $slotA, 'slotB' => $slotB] = sessionTimeFixture();

    // A slot already confirmed for someone else must not be offered.
    $slotB->update(['claimed_by_session_id' => null, 'is_assignable' => false]);

    $this->actingAs($user)
        ->get('/partner/sessions/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Sessions/Create')
            ->has('availableSlots', 1)
            ->where('availableSlots.0.id', $slotA->id)
            ->where('availableSlots.0.date', fn ($date) => str_starts_with($date, '2027-03-02'))
            ->where('availableSlots.0.time_label', '11:00-12:30')
            ->where('conference.start_date', fn ($d) => str_starts_with($d, '2027-03-01')),
        );
});

it('creates a session with the chosen time held and pending approval, not booked', function () {
    ['user' => $user, 'partner' => $partner, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)
        ->post('/partner/sessions', sessionPayload([
            'session_slot_id' => $slotA->id,
            'slot_reason' => 'Our minister is only available on day 1.',
        ]))
        ->assertRedirect('/partner/sessions');

    $session = ConferenceSession::where('partner_id', $partner->id)->sole();

    // Requested, not confirmed.
    expect($session->session_slot_id)->toBeNull()
        ->and($session->requested_session_slot_id)->toBe($slotA->id);

    // The slot is held so nobody else can be approved into it.
    $slotA->refresh();
    expect($slotA->held_by_session_id)->toBe($session->id)
        ->and($slotA->claimed_by_session_id)->toBeNull()
        ->and($slotA->isAvailable())->toBeFalse();

    $changeRequest = ChangeRequest::where('conference_session_id', $session->id)->sole();
    expect($changeRequest->type)->toBe(ChangeRequestType::Time)
        ->and($changeRequest->status)->toBe(ChangeRequestStatus::Pending)
        ->and($changeRequest->requested_by)->toBe($user->id)
        ->and($changeRequest->current_value)->toBeNull()
        ->and($changeRequest->requested_value['slot_code'])->toBe('Parallel 1')
        ->and($changeRequest->requested_value['time_label'])->toBe('11:00-12:30')
        ->and($changeRequest->reason)->toBe('Our minister is only available on day 1.');
});

it('hides a slot held by another partner from everyone else', function () {
    ['user' => $user, 'slotA' => $slotA, 'slotB' => $slotB] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));

    $other = User::factory()->partner()->create();
    Partner::factory()->forUser($other)->create([
        'conference_id' => $slotA->conference_id,
        'status' => PartnerStatus::Confirmed,
    ]);

    $this->actingAs($other)
        ->get('/partner/sessions/create')
        ->assertInertia(fn (Assert $page) => $page
            ->has('availableSlots', 1)
            ->where('availableSlots.0.id', $slotB->id),
        );
});

it('rejects a request for a slot another partner is already holding', function () {
    ['user' => $user, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));

    $other = User::factory()->partner()->create();
    Partner::factory()->forUser($other)->create([
        'conference_id' => $slotA->conference_id,
        'status' => PartnerStatus::Confirmed,
    ]);

    $this->actingAs($other)
        ->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]))
        ->assertSessionHasErrors('session_slot_id');
});

it('confirms the slot on the session when the partnerships team approves', function () {
    ['user' => $user, 'admin' => $admin, 'partner' => $partner, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));

    $session = ConferenceSession::where('partner_id', $partner->id)->sole();
    $changeRequest = ChangeRequest::where('conference_session_id', $session->id)->sole();

    $this->actingAs($admin)
        ->put("/admin/change-requests/{$changeRequest->id}/approve")
        ->assertRedirect();

    $session->refresh();
    $slotA->refresh();

    expect($session->session_slot_id)->toBe($slotA->id)
        ->and($session->requested_session_slot_id)->toBeNull()
        ->and($slotA->claimed_by_session_id)->toBe($session->id)
        ->and($slotA->held_by_session_id)->toBeNull();

    $changeRequest->refresh();
    expect($changeRequest->status)->toBe(ChangeRequestStatus::Approved)
        ->and($changeRequest->reviewed_by)->toBe($admin->id)
        ->and($changeRequest->reviewed_at)->not->toBeNull();
});

it('releases the held slot and leaves the session unscheduled when rejected', function () {
    ['user' => $user, 'admin' => $admin, 'partner' => $partner, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));

    $session = ConferenceSession::where('partner_id', $partner->id)->sole();
    $changeRequest = ChangeRequest::where('conference_session_id', $session->id)->sole();

    $this->actingAs($admin)
        ->put("/admin/change-requests/{$changeRequest->id}/reject", [
            'resolution_notes' => 'That track is oversubscribed.',
        ])
        ->assertRedirect();

    $session->refresh();
    $slotA->refresh();

    expect($session->session_slot_id)->toBeNull()
        ->and($session->requested_session_slot_id)->toBeNull()
        ->and($slotA->held_by_session_id)->toBeNull()
        ->and($slotA->isAvailable())->toBeTrue();

    expect($changeRequest->refresh()->status)->toBe(ChangeRequestStatus::Rejected)
        ->and($changeRequest->resolution_notes)->toBe('That track is oversubscribed.');
});

it('requires resolution notes to reject', function () {
    ['user' => $user, 'admin' => $admin, 'partner' => $partner, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $changeRequest = ChangeRequest::sole();

    $this->actingAs($admin)
        ->put("/admin/change-requests/{$changeRequest->id}/reject", [])
        ->assertSessionHasErrors('resolution_notes');

    expect($changeRequest->refresh()->status)->toBe(ChangeRequestStatus::Pending);
});

it('saves non-time edits immediately but sends a time change for approval', function () {
    ['user' => $user, 'admin' => $admin, 'partner' => $partner, 'slotA' => $slotA, 'slotB' => $slotB] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $session = ConferenceSession::where('partner_id', $partner->id)->sole();

    // Approve the first request so the session has a confirmed time.
    $this->actingAs($admin)->put('/admin/change-requests/'.ChangeRequest::sole()->id.'/approve');

    $this->actingAs($user)
        ->put("/partner/sessions/{$session->id}", sessionPayload([
            'title' => 'Renamed session',
            'session_slot_id' => $slotB->id,
        ]))
        ->assertRedirect('/partner/sessions');

    $session->refresh();

    // Title saved outright; time still on the old slot pending review.
    expect($session->title)->toBe('Renamed session')
        ->and($session->session_slot_id)->toBe($slotA->id)
        ->and($session->requested_session_slot_id)->toBe($slotB->id);

    expect($slotA->refresh()->claimed_by_session_id)->toBe($session->id);
    expect($slotB->refresh()->held_by_session_id)->toBe($session->id);

    $second = ChangeRequest::where('status', ChangeRequestStatus::Pending)->sole();
    expect($second->current_value['slot_code'])->toBe('Parallel 1')
        ->and($second->requested_value['slot_code'])->toBe('Parallel 2');
});

it('swaps the slots when a time change is approved', function () {
    ['user' => $user, 'admin' => $admin, 'partner' => $partner, 'slotA' => $slotA, 'slotB' => $slotB] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $session = ConferenceSession::where('partner_id', $partner->id)->sole();
    $this->actingAs($admin)->put('/admin/change-requests/'.ChangeRequest::sole()->id.'/approve');

    $this->actingAs($user)->put("/partner/sessions/{$session->id}", sessionPayload([
        'session_slot_id' => $slotB->id,
    ]));

    $pending = ChangeRequest::where('status', ChangeRequestStatus::Pending)->sole();
    $this->actingAs($admin)->put("/admin/change-requests/{$pending->id}/approve");

    $session->refresh();

    expect($session->session_slot_id)->toBe($slotB->id)
        ->and($session->requested_session_slot_id)->toBeNull();

    // The old slot goes back into the pool.
    expect($slotA->refresh()->isAvailable())->toBeTrue()
        ->and($slotB->refresh()->claimed_by_session_id)->toBe($session->id);
});

it('locks the time while a request is pending', function () {
    ['user' => $user, 'partner' => $partner, 'slotA' => $slotA, 'slotB' => $slotB] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $session = ConferenceSession::where('partner_id', $partner->id)->sole();

    $this->actingAs($user)
        ->put("/partner/sessions/{$session->id}", sessionPayload(['session_slot_id' => $slotB->id]))
        ->assertSessionHasErrors('session_slot_id');

    expect($session->refresh()->requested_session_slot_id)->toBe($slotA->id)
        ->and($slotB->refresh()->isAvailable())->toBeTrue();
});

it('returns the slot to the pool when a draft session is deleted', function () {
    ['user' => $user, 'partner' => $partner, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $session = ConferenceSession::where('partner_id', $partner->id)->sole();

    $this->actingAs($user)->delete("/partner/sessions/{$session->id}")->assertRedirect('/partner/sessions');

    expect($slotA->refresh()->isAvailable())->toBeTrue();
    expect(ChangeRequest::sole()->status)->toBe(ChangeRequestStatus::AutoResolved);
});

it('shows the pending time on the edit page', function () {
    ['user' => $user, 'partner' => $partner, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $session = ConferenceSession::where('partner_id', $partner->id)->sole();

    $this->actingAs($user)
        ->get("/partner/sessions/{$session->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Sessions/Edit')
            ->where('session.requested_session_slot_id', $slotA->id)
            ->where('session.session_slot_id', null)
            ->where('session.requested_session_slot.slot_code', 'Parallel 1')
            ->where('session.requested_session_slot.time_label', '11:00-12:30'),
        );
});

it('will not review a change request twice', function () {
    ['user' => $user, 'admin' => $admin, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $changeRequest = ChangeRequest::sole();

    $this->actingAs($admin)->put("/admin/change-requests/{$changeRequest->id}/approve");

    $this->actingAs($admin)
        ->put("/admin/change-requests/{$changeRequest->id}/reject", ['resolution_notes' => 'Changed my mind.'])
        ->assertSessionHas('error');

    expect($changeRequest->refresh()->status)->toBe(ChangeRequestStatus::Approved);
});

it('emails the partner and the admin/partnerships team when a time change is approved', function () {
    Notification::fake();

    ['user' => $user, 'admin' => $admin, 'partner' => $partner, 'slotA' => $slotA] = sessionTimeFixture();

    $partnerships = User::factory()->create(['role' => UserRole::Partnerships, 'is_active' => true]);
    $inactive = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);
    $finance = User::factory()->create(['role' => UserRole::Finance, 'is_active' => true]);

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $changeRequest = ChangeRequest::sole();

    $this->actingAs($admin)->put("/admin/change-requests/{$changeRequest->id}/approve");

    // Partner who raised it gets the decision.
    Notification::assertSentTo($user, ChangeRequestNotification::class,
        fn ($n, $channels, $notifiable) => in_array('mail', $channels, true));

    // Admin and partnerships staff get a record copy.
    Notification::assertSentTo($admin, ChangeRequestNotification::class);
    Notification::assertSentTo($partnerships, ChangeRequestNotification::class);

    // Inactive staff and unrelated roles are left out.
    Notification::assertNotSentTo($inactive, ChangeRequestNotification::class);
    Notification::assertNotSentTo($finance, ChangeRequestNotification::class);

    // Configured team mailboxes are copied too.
    Notification::assertSentOnDemand(ChangeRequestNotification::class);
});

it('emails the partner and the team when a time change is rejected', function () {
    Notification::fake();

    ['user' => $user, 'admin' => $admin, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $changeRequest = ChangeRequest::sole();

    $this->actingAs($admin)->put("/admin/change-requests/{$changeRequest->id}/reject", [
        'resolution_notes' => 'That track is full.',
    ]);

    Notification::assertSentTo($user, ChangeRequestNotification::class);
    Notification::assertSentTo($admin, ChangeRequestNotification::class);
});

it('does not email anyone when a decision is refused as already reviewed', function () {
    ['user' => $user, 'admin' => $admin, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $changeRequest = ChangeRequest::sole();
    $this->actingAs($admin)->put("/admin/change-requests/{$changeRequest->id}/approve");

    Notification::fake();

    $this->actingAs($admin)
        ->put("/admin/change-requests/{$changeRequest->id}/reject", ['resolution_notes' => 'Nope.'])
        ->assertSessionHas('error');

    Notification::assertNothingSent();
});

it('builds a partner email naming the session and both slots', function () {
    ['user' => $user, 'admin' => $admin, 'slotA' => $slotA, 'slotB' => $slotB] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $session = ConferenceSession::sole();
    $this->actingAs($admin)->put('/admin/change-requests/'.ChangeRequest::sole()->id.'/approve');

    $this->actingAs($user)->put("/partner/sessions/{$session->id}", sessionPayload([
        'session_slot_id' => $slotB->id,
    ]));
    $pending = ChangeRequest::where('status', ChangeRequestStatus::Pending)->sole();
    $this->actingAs($admin)->put("/admin/change-requests/{$pending->id}/approve");

    $mail = (new ChangeRequestNotification($pending->refresh()))->toMail($user);
    $rendered = implode(' ', array_merge($mail->introLines, $mail->outroLines));

    expect($mail->subject)->toContain('approved')
        ->and($rendered)->toContain('Parallel 2')
        ->and($rendered)->toContain('Parallel 1')
        ->and($rendered)->toContain('11:00-12:30');
});

it('lets admin, partnerships and programme approve, but not finance or communications', function () {
    ['user' => $user, 'slotA' => $slotA] = sessionTimeFixture();

    $this->actingAs($user)->post('/partner/sessions', sessionPayload(['session_slot_id' => $slotA->id]));
    $changeRequest = ChangeRequest::sole();

    foreach ([UserRole::Finance, UserRole::Communications] as $role) {
        $staff = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($staff)
            ->get('/admin/change-requests')
            ->assertForbidden();

        $this->actingAs($staff)
            ->put("/admin/change-requests/{$changeRequest->id}/approve")
            ->assertForbidden();
    }

    expect($changeRequest->refresh()->status)->toBe(ChangeRequestStatus::Pending);

    foreach ([UserRole::Partnerships, UserRole::Programme, UserRole::Admin] as $role) {
        $staff = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($staff)->get('/admin/change-requests')->assertOk();
    }

    // Admin can carry the approval through.
    $adminUser = User::factory()->admin()->create();
    $this->actingAs($adminUser)
        ->put("/admin/change-requests/{$changeRequest->id}/approve")
        ->assertRedirect();

    expect($changeRequest->refresh()->status)->toBe(ChangeRequestStatus::Approved)
        ->and($changeRequest->reviewed_by)->toBe($adminUser->id);
});
