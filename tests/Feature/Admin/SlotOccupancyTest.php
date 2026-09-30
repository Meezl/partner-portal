<?php

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Enums\PartnerStatus;
use App\Models\ChangeRequest;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\SessionSlot;
use App\Models\User;
use App\Services\SessionTimeRequestService;
use App\Services\SlotOccupancyReconciler;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A slot that reads as taken must always be accounted for: the board, the slot
 * inventory and the error a partner sees have to agree on who has it.
 */
function slotFixture(): array
{
    $conference = Conference::factory()->active()->create();
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);
    $slot = SessionSlot::factory()->create(['conference_id' => $conference->id]);

    return [$conference, $user, $partner, $slot];
}

it('frees the slot when a session holding it is deleted', function () {
    [$conference, $user, $partner, $slot] = slotFixture();

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    app(SessionTimeRequestService::class)->requestSlot($session, $slot->id, $user);

    expect($slot->fresh()->held_by_session_id)->toBe($session->id);

    // A plain delete, not the partner controller's release-then-delete: every
    // path must free the slot, including a soft delete.
    $session->delete();

    $slot->refresh();

    expect($slot->held_by_session_id)->toBeNull()
        ->and($slot->isAvailable())->toBeTrue()
        ->and(ChangeRequest::where('conference_session_id', $session->id)
            ->where('status', ChangeRequestStatus::Pending)->count())->toBe(0);
});

it('tells a partner when the slot is held by their own session, not a stranger', function () {
    [$conference, $user, $partner, $slot] = slotFixture();

    $first = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'title' => 'Our Breakfast Briefing',
    ]);

    app(SessionTimeRequestService::class)->requestSlot($first, $slot->id, $user);

    $this->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'A Second Session',
            'format' => 'panel',
            'session_slot_id' => $slot->id,
        ])
        ->assertSessionHasErrors([
            'session_slot_id' => sprintf(
                '%s is already held by your session "Our Breakfast Briefing". Release it there, or choose a different slot.',
                $slot->slot_code,
            ),
        ]);
});

it('does not name another partner\'s session when refusing a slot', function () {
    [$conference, , , $slot] = slotFixture();

    $otherUser = User::factory()->partner()->create();
    $otherPartner = Partner::factory()->forUser($otherUser)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);
    $theirSession = ConferenceSession::factory()->create([
        'partner_id' => $otherPartner->id,
        'conference_id' => $conference->id,
        'title' => 'Confidential Funders Meeting',
    ]);

    app(SessionTimeRequestService::class)->requestSlot($theirSession, $slot->id, $otherUser);

    $mineUser = User::factory()->partner()->create();
    Partner::factory()->forUser($mineUser)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);

    $this->actingAs($mineUser)
        ->post(route('partner.sessions.store'), [
            'title' => 'My Session',
            'format' => 'panel',
            'session_slot_id' => $slot->id,
        ])
        ->assertSessionHasErrors('session_slot_id');

    $message = session('errors')->getBag('default')->first('session_slot_id');

    expect($message)->toContain('has already been taken')
        ->and($message)->not->toContain('Confidential Funders Meeting');
});

it('shows a slot held by an unsubmitted draft in the inventory', function () {
    [$conference, $user, $partner, $slot] = slotFixture();

    $draft = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'title' => 'Half-finished Idea',
    ]);

    app(SessionTimeRequestService::class)->requestSlot($draft, $slot->id, $user);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.scheduling.slots'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Scheduling/Slots')
            ->where('slots.0.held_by_session_id', $draft->id)
            ->where('slots.0.held_by_session.title', 'Half-finished Idea')
            ->where('slots.0.held_by_session.partner.organization_name', $partner->organization_name)
            ->has('inconsistencies', 0),
        );
});

it('hands a held slot back to the pool when an admin releases it', function () {
    [$conference, $user, $partner, $slot] = slotFixture();

    $draft = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    app(SessionTimeRequestService::class)->requestSlot($draft, $slot->id, $user);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.scheduling.slots.release', $slot))
        ->assertSessionHas('success');

    $slot->refresh();

    expect($slot->isAvailable())->toBeTrue()
        ->and($draft->fresh()->requested_session_slot_id)->toBeNull()
        ->and(ChangeRequest::where('conference_session_id', $draft->id)
            ->where('type', ChangeRequestType::Time)
            ->where('status', ChangeRequestStatus::AutoResolved)->count())->toBe(1);
});

it('refuses to release a slot that is already free', function () {
    [, , , $slot] = slotFixture();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.scheduling.slots.release', $slot))
        ->assertSessionHas('error', 'That slot is already free.');
});

it('reports a slot held by a session that no longer points at it', function () {
    [$conference, $user, $partner, $slot] = slotFixture();

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'title' => 'Drifted Session',
    ]);

    app(SessionTimeRequestService::class)->requestSlot($session, $slot->id, $user);

    // Simulate the two sides drifting apart, which is what leaves a slot taken
    // with nothing on the board to explain it.
    $session->forceFill(['requested_session_slot_id' => null])->save();

    $reconciler = app(SlotOccupancyReconciler::class);
    $problems = $reconciler->problems($conference->id);

    expect($problems)->toHaveCount(1)
        ->and($problems[0]['kind'])->toBe('one-sided-hold')
        ->and($problems[0]['detail'])->toContain('Drifted Session');

    expect($reconciler->resolve($problems))->toBe(1)
        ->and($slot->fresh()->isAvailable())->toBeTrue()
        ->and($reconciler->problems($conference->id))->toHaveCount(0);
});

it('lists long-standing holds separately from broken ones', function () {
    [$conference, $user, $partner, $slot] = slotFixture();

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
        'title' => 'Forgotten Draft',
    ]);

    app(SessionTimeRequestService::class)->requestSlot($session, $slot->id, $user);
    $slot->forceFill(['held_at' => now()->subDays(10)])->save();

    $reconciler = app(SlotOccupancyReconciler::class);

    // A long hold is a real reservation, so it is not an inconsistency.
    expect($reconciler->problems($conference->id))->toHaveCount(0);

    $stale = $reconciler->staleHolds(7, $conference->id);

    expect($stale)->toHaveCount(1)
        ->and($stale[0]['detail'])->toContain('Forgotten Draft')
        ->and($reconciler->staleHolds(30, $conference->id))->toHaveCount(0);
});

it('reconciles from the command line', function () {
    [$conference, $user, $partner, $slot] = slotFixture();

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $conference->id,
    ]);

    app(SessionTimeRequestService::class)->requestSlot($session, $slot->id, $user);
    $session->forceFill(['requested_session_slot_id' => null])->save();

    // Without --fix the command only reports.
    $this->artisan('slots:reconcile', ['--conference' => $conference->id])
        ->assertSuccessful();

    expect($slot->fresh()->isAvailable())->toBeFalse();

    $this->artisan('slots:reconcile', ['--conference' => $conference->id, '--fix' => true])
        ->expectsOutputToContain('Released 1 slot(s).')
        ->assertSuccessful();

    expect($slot->fresh()->isAvailable())->toBeTrue();
});
