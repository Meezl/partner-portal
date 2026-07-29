<?php

use App\Enums\PartnerStatus;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Notifications\EOISubmittedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('allows a partner to save an expression of interest as a draft', function () {
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('partner.eoi.draft'), [
            'organization_name' => 'Example Org',
            'contact_person' => 'Jane Partner',
            'email' => 'partner@example.test',
            'phone' => '+254700000001',
            'physical_address' => 'Nairobi, Kenya',
            'package_id' => $package->id,
        ]);

    $response->assertRedirect();

    $partner = Partner::firstOrFail();

    expect($partner->status)->toBe(PartnerStatus::Draft)
        ->and($partner->organization_name)->toBe('Example Org')
        ->and($partner->packages()->pluck('sponsorship_packages.id')->all())->toBe([$package->id])
        ->and($user->fresh()->partner_id)->toBe($partner->id);
});

it('submits an expression of interest, notifies the ahaic mailbox, and moves the partner to commitment', function () {
    Notification::fake();

    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('partner.eoi.store'), [
            'organization_name' => 'Amref Partner Org',
            'contact_person' => 'Jane Partner',
            'email' => 'partner@example.test',
            'phone' => '+254700000001',
            'physical_address' => 'Nairobi, Kenya',
            'package_id' => $package->id,
        ]);

    $response->assertRedirect(route('partner.commitment.edit'));

    $partner = Partner::firstOrFail()->fresh('packages');

    expect($partner->status)->toBe(PartnerStatus::PendingAgreement)
        ->and($partner->submitted_at)->not->toBeNull()
        ->and($partner->packages->first()?->id)->toBe($package->id);

    Notification::assertSentOnDemand(EOISubmittedNotification::class, function (EOISubmittedNotification $notification, array $channels, object $notifiable) {
        return in_array('mail', $channels, true)
            && ($notifiable->routes['mail'] ?? null) === config('ahaic.central_email');
    });
});

it('lets a rejected partner reopen the eoi form for revision', function () {
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();

    $partner = Partner::factory()
        ->forUser($user)
        ->create([
            'conference_id' => $conference->id,
            'status' => PartnerStatus::Rejected,
        ]);

    $partner->packages()->sync([$package->id]);

    $response = $this
        ->actingAs($user)
        ->get(route('partner.eoi.create'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Partner/ExpressionOfInterest')
        ->where('partner.id', $partner->id)
        ->has('packages', 1));
});
