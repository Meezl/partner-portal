<?php

use App\Enums\PartnerStatus;
use App\Enums\SessionStatus;
use App\Models\BrandingRequirement;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Notifications\PartnerSubmitted;
use App\Notifications\SubmissionLockedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function confirmedPartnerFixture(array $partnerOverrides = []): array
{
    $conference = Conference::factory()->active()->create();
    $package = SponsorshipPackage::factory()->create(['conference_id' => $conference->id]);
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()
        ->forUser($user)
        ->create(array_merge([
            'conference_id' => $conference->id,
            'status' => PartnerStatus::Confirmed,
            'logo_path' => null,
            'description' => null,
            'social_media' => null,
            'number_of_participants' => null,
            'onboarding_progress' => [
                'organization' => 0,
                'sessions' => 0,
                'communications' => 0,
                'contacts' => 0,
            ],
        ], $partnerOverrides));

    $partner->packages()->sync([$package->id]);

    return compact('conference', 'package', 'user', 'partner');
}

it('completes onboarding sections and exposes the review page with computed progress', function () {
    Storage::fake('public');

    ['user' => $user, 'partner' => $partner] = confirmedPartnerFixture();

    $this
        ->actingAs($user)
        ->get(route('partner.onboarding.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Onboarding/Index')
            ->where('progress.organization', 0)
            ->where('progress.sessions', 0)
            ->where('progress.communications', 0)
            ->where('progress.contacts', 0));

    $this
        ->actingAs($user)
        ->put(route('partner.onboarding.update', 'organization'), [
            'logo' => UploadedFile::fake()->image('logo.png'),
            'description' => 'We convene African health leaders to accelerate policy and systems change.',
            'social_media' => [
                'website' => 'https://example.org',
                'twitter' => '',
                'linkedin' => '',
                'facebook' => '',
            ],
            'number_of_participants' => 8,
            'exhibition_preferences' => 'Corner booth preferred.',
        ])
        ->assertRedirect(route('partner.onboarding.index'))
        ->assertSessionHas('success', 'Organization section updated successfully.');

    $partner = $partner->fresh();

    expect($partner->status)->toBe(PartnerStatus::Onboarding)
        ->and($partner->onboarding_progress['organization'])->toBe(100)
        ->and($partner->logo_path)->toContain('/storage/');

    $this
        ->actingAs($user)
        ->put(route('partner.onboarding.update', 'communications'), [
            'requirements' => 'Please use our approved cobranding guide and green palette.',
            'media_contact_name' => 'Media Lead',
            'media_contact_email' => 'media@example.org',
            'media_contact_phone' => '+254700000001',
            'assets' => UploadedFile::fake()->create('brand-kit.zip', 256, 'application/zip'),
        ])
        ->assertRedirect(route('partner.onboarding.index'))
        ->assertSessionHas('success', 'Communications section updated successfully.');

    $branding = BrandingRequirement::firstOrFail();

    expect($branding->requirements)->toContain('cobranding')
        ->and($branding->assets)->toHaveCount(1)
        ->and($partner->fresh()->onboarding_progress['communications'])->toBe(100);

    $this
        ->actingAs($user)
        ->put(route('partner.onboarding.update', 'contacts'), [
            'contacts' => [
                [
                    'name' => 'Session Lead',
                    'email' => 'session@example.org',
                    'phone' => '+254700000002',
                    'role' => 'session_lead',
                    'organization' => 'Example Org',
                ],
                [
                    'name' => 'Communications Lead',
                    'email' => 'comms@example.org',
                    'phone' => '+254700000003',
                    'role' => 'comms_lead',
                    'organization' => 'Example Org',
                ],
            ],
        ])
        ->assertRedirect(route('partner.onboarding.index'))
        ->assertSessionHas('success', 'Contacts section updated successfully.');

    expect(PartnerContact::count())->toBe(2)
        ->and($partner->fresh()->onboarding_progress['contacts'])->toBe(100);

    $this
        ->actingAs($user)
        ->post(route('partner.sessions.store'), [
            'title' => 'Scaling Community Health Financing',
            'description' => 'A practical discussion on financing primary care at scale.',
            'format' => 'panel',
            'organizers' => ['Example Org'],
            'co_hosts' => ['Partner Co-host'],
            'target_audience' => 'Policy leaders',
            'expected_participants' => 150,
            'is_open' => true,
            'special_requirements' => [
                'av_equipment' => true,
                'translation' => false,
                'seating_type' => 'theater',
                'catering' => true,
            ],
        ])
        ->assertRedirect(route('partner.sessions.index'));

    $session = ConferenceSession::firstOrFail();

    expect($session->status)->toBe(SessionStatus::Draft)
        ->and($session->special_requirements['av_equipment'])->toBeTrue()
        ->and($partner->fresh()->onboarding_progress['sessions'])->toBe(100);

    $this
        ->actingAs($user)
        ->get(route('partner.submission.review'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Review')
            ->where('progress.organization', 100)
            ->where('progress.sessions', 100)
            ->where('progress.communications', 100)
            ->where('progress.contacts', 100)
            ->has('partner.sessions', 1)
            ->has('partner.contacts', 2));
});

it('blocks final submission until all onboarding sections are complete', function () {
    ['user' => $user, 'partner' => $partner] = confirmedPartnerFixture();

    ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $partner->conference_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->post(route('partner.submission.submit'));

    $response->assertSessionHas('error');

    expect($partner->fresh()->status)->toBe(PartnerStatus::Confirmed)
        ->and($partner->fresh()->locked_at)->toBeNull();
});

it('locks the submission, marks sessions submitted, and sends notifications', function () {
    Notification::fake();

    ['user' => $user, 'partner' => $partner] = confirmedPartnerFixture([
        'status' => PartnerStatus::Onboarding,
        'logo_path' => '/storage/partners/logo.png',
        'description' => 'Organization profile ready for conference materials.',
        'social_media' => ['website' => 'https://example.org'],
        'number_of_participants' => 5,
    ]);

    BrandingRequirement::factory()->create([
        'partner_id' => $partner->id,
    ]);

    PartnerContact::factory()->create([
        'partner_id' => $partner->id,
        'role' => 'session_lead',
    ]);

    PartnerContact::factory()->create([
        'partner_id' => $partner->id,
        'role' => 'comms_lead',
    ]);

    $session = ConferenceSession::factory()->create([
        'partner_id' => $partner->id,
        'conference_id' => $partner->conference_id,
        'status' => SessionStatus::Draft,
    ]);

    $this
        ->actingAs($user)
        ->post(route('partner.submission.submit'))
        ->assertRedirect(route('partner.dashboard'));

    $partner = $partner->fresh();
    $session = $session->fresh();

    expect($partner->status)->toBe(PartnerStatus::Submitted)
        ->and($partner->locked_at)->not->toBeNull()
        ->and($partner->submitted_at)->not->toBeNull()
        ->and($partner->onboarding_progress['organization'])->toBe(100)
        ->and($partner->onboarding_progress['sessions'])->toBe(100)
        ->and($partner->onboarding_progress['communications'])->toBe(100)
        ->and($partner->onboarding_progress['contacts'])->toBe(100)
        ->and($session->status)->toBe(SessionStatus::Submitted)
        ->and($session->submitted_at)->not->toBeNull();

    Notification::assertSentTo($user, SubmissionLockedNotification::class);
    Notification::assertSentOnDemand(PartnerSubmitted::class);

    $this
        ->actingAs($user)
        ->get(route('partner.onboarding.index'))
        ->assertRedirect(route('partner.dashboard'));
});
