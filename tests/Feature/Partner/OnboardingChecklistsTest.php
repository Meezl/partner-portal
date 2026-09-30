<?php

use App\Enums\PartnerStatus;
use App\Models\BrandingRequirement;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\User;
use App\Services\OnboardingProgressService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The exhibition and communications checklists, batched branding uploads, and
 * the two mandatory contacts that complete the Contacts section.
 */
function checklistPartnerFixture(): array
{
    $conference = Conference::factory()->active()->create();
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Confirmed,
    ]);

    return [$user, $partner];
}

it('stores the exhibition checklist with every option and the other note', function () {
    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'organization'), [
            'exhibition_requirements' => [
                'additional_furniture' => true,
                'av_equipment' => true,
                'other' => 'A fridge for product samples',
            ],
            'exhibition_preferences' => 'Corner booth if possible.',
        ])
        ->assertRedirect(route('partner.onboarding.index'))
        ->assertSessionHas('success');

    $requirements = $partner->fresh()->exhibition_requirements;

    // Unticked options are stored as false rather than left out, so the
    // partnerships team can tell "not needed" from "not asked".
    expect($requirements['additional_furniture'])->toBeTrue()
        ->and($requirements['av_equipment'])->toBeTrue()
        ->and($requirements['storage_space'])->toBeFalse()
        ->and($requirements['additional_staff_passes'])->toBeFalse()
        ->and($requirements['other'])->toBe('A fridge for product samples');
});

it('sends the exhibition option labels to the organization form', function () {
    [$user] = checklistPartnerFixture();

    $this->actingAs($user)
        ->get(route('partner.onboarding.edit', 'organization'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Onboarding/OrganizationProfile')
            ->where('exhibitionOptions.additional_power', 'Additional power/electrical points')
            ->where('exhibitionOptions.branding_signage', 'Branding/signage requirements'),
        );
});

it('stores the communications checklist and counts it towards progress', function () {
    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'communications'), [
            'comms_checklist' => [
                'social_media' => true,
                'press_release' => true,
                'other' => null,
            ],
        ])
        ->assertRedirect(route('partner.onboarding.index'))
        ->assertSessionHas('success');

    $branding = BrandingRequirement::firstOrFail();

    expect($branding->comms_checklist['social_media'])->toBeTrue()
        ->and($branding->comms_checklist['newsletter_feature'])->toBeFalse()
        ->and($branding->comms_checklist['other'])->toBeNull()
        // The checklist stands in for the free-text notes, so the section is
        // complete without them, and without a media contact.
        ->and($branding->requirements)->toBeNull()
        ->and($partner->fresh()->onboarding_progress['communications'])->toBe(100);
});

it('stores a whole batch of branding assets in one save', function () {
    Storage::fake('public');

    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'communications'), [
            'requirements' => 'Standard cobranding.',
            'assets' => [
                UploadedFile::fake()->create('logo-pack.zip', 64, 'application/zip'),
                UploadedFile::fake()->image('banner.png'),
                UploadedFile::fake()->create('brand-guide.pdf', 64, 'application/pdf'),
            ],
        ])
        ->assertRedirect(route('partner.onboarding.index'))
        ->assertSessionHas('success');

    $assets = BrandingRequirement::firstOrFail()->assets;

    expect($assets)->toHaveCount(3)
        // The names the partner gave the files, not the hashed storage names.
        ->and(array_column($assets, 'name'))
        ->toBe(['logo-pack.zip', 'banner.png', 'brand-guide.pdf'])
        ->and(Storage::disk('public')->allFiles("partners/{$partner->id}/branding"))->toHaveCount(3);
});

it('keeps assets from earlier batches when a new batch is uploaded', function () {
    Storage::fake('public');

    [$user] = checklistPartnerFixture();

    $upload = fn (array $files) => $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'communications'), [
            'requirements' => 'Standard cobranding.',
            'assets' => $files,
        ])
        ->assertSessionHas('success');

    $upload([UploadedFile::fake()->image('first.png')]);
    $upload([UploadedFile::fake()->image('second.png'), UploadedFile::fake()->image('third.png')]);

    expect(BrandingRequirement::firstOrFail()->assets)->toHaveCount(3);
});

it('rejects a branding asset of the wrong type without losing the rest', function () {
    Storage::fake('public');

    [$user] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'communications'), [
            'requirements' => 'Standard cobranding.',
            'assets' => [
                UploadedFile::fake()->image('fine.png'),
                UploadedFile::fake()->create('macro.docx', 64, 'application/msword'),
            ],
        ])
        ->assertSessionHasErrors('assets.1');

    expect(BrandingRequirement::count())->toBe(0);
});

it('stores a designation for each contact', function () {
    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'contacts'), [
            'contacts' => [
                [
                    'name' => 'Amina Odhiambo',
                    'email' => 'amina@example.org',
                    'role' => 'session_lead',
                    'designation' => 'Head of Programmes',
                ],
                [
                    'name' => 'Brian Mutua',
                    'email' => 'brian@example.org',
                    'role' => 'comms_lead',
                    'designation' => 'Communications Manager',
                ],
            ],
        ])
        ->assertRedirect(route('partner.onboarding.index'))
        ->assertSessionHas('success');

    expect(PartnerContact::where('role', 'session_lead')->firstOrFail()->designation)
        ->toBe('Head of Programmes')
        ->and(PartnerContact::where('role', 'comms_lead')->firstOrFail()->designation)
        ->toBe('Communications Manager')
        ->and($partner->fresh()->onboarding_progress['contacts'])->toBe(100);
});

it('names the two mandatory contacts on the contacts form', function () {
    [$user] = checklistPartnerFixture();

    $this->actingAs($user)
        ->get(route('partner.onboarding.edit', 'contacts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Onboarding/Contacts')
            ->where('mandatoryRoles', [
                'session_lead' => 'Session Lead',
                'comms_lead' => 'Communications Lead',
            ]),
        );
});

it('holds contacts progress at half until both mandatory contacts are named', function () {
    [$user, $partner] = checklistPartnerFixture();

    $progress = app(OnboardingProgressService::class);

    PartnerContact::factory()->create([
        'partner_id' => $partner->id,
        'role' => 'session_lead',
    ]);

    expect($progress->calculate($partner->fresh(['contacts', 'brandingRequirement', 'sessions']))['contacts'])
        ->toBe(50);

    // More contacts of other roles do not move it — only the mandatory two do.
    PartnerContact::factory()->count(3)->create([
        'partner_id' => $partner->id,
        'role' => 'additional',
    ]);

    expect($progress->calculate($partner->fresh(['contacts', 'brandingRequirement', 'sessions']))['contacts'])
        ->toBe(50);

    PartnerContact::factory()->create([
        'partner_id' => $partner->id,
        'role' => 'comms_lead',
    ]);

    expect($progress->calculate($partner->fresh(['contacts', 'brandingRequirement', 'sessions']))['contacts'])
        ->toBe(100);
});

it('does not count a mandatory contact that is missing a name or email', function () {
    [$user, $partner] = checklistPartnerFixture();

    PartnerContact::factory()->create([
        'partner_id' => $partner->id,
        'role' => 'session_lead',
        'email' => '',
    ]);
    PartnerContact::factory()->create([
        'partner_id' => $partner->id,
        'role' => 'comms_lead',
    ]);

    expect(app(OnboardingProgressService::class)
        ->calculate($partner->fresh(['contacts', 'brandingRequirement', 'sessions']))['contacts'])
        ->toBe(50);
});

it('offers a media contact role and stores one', function () {
    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->get(route('partner.onboarding.edit', 'contacts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contactRoles.media_lead', 'Media Contact')
            // The media contact lives here now, not on the branding form, and
            // it stays optional.
            ->missing('mandatoryRoles.media_lead'),
        );

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'contacts'), [
            'contacts' => [
                ['name' => 'Amina', 'email' => 'amina@example.org', 'role' => 'session_lead'],
                ['name' => 'Brian', 'email' => 'brian@example.org', 'role' => 'comms_lead'],
                [
                    'name' => 'Grace Wanjiru',
                    'email' => 'grace@example.org',
                    'phone' => '+254700000001',
                    'role' => 'media_lead',
                    'designation' => 'Press Officer',
                ],
            ],
        ])
        ->assertSessionHas('success');

    $media = PartnerContact::where('role', 'media_lead')->firstOrFail();

    expect($media->name)->toBe('Grace Wanjiru')
        ->and($media->designation)->toBe('Press Officer')
        // The optional third contact does not change the section's progress.
        ->and($partner->fresh()->onboarding_progress['contacts'])->toBe(100);
});

it('rejects a contact role the form does not offer', function () {
    [$user] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'contacts'), [
            'contacts' => [
                ['name' => 'Amina', 'email' => 'amina@example.org', 'role' => 'chief_vibes_officer'],
            ],
        ])
        ->assertSessionHasErrors('contacts.0.role');

    expect(PartnerContact::count())->toBe(0);
});

it('completes communications without a media contact', function () {
    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'communications'), [
            'comms_checklist' => ['logo_placement' => true],
        ])
        ->assertSessionHas('success');

    expect($partner->fresh()->onboarding_progress['communications'])->toBe(100);
});

it('shows the uploaded assets on the review page rather than a media contact', function () {
    Storage::fake('public');

    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'communications'), [
            'comms_checklist' => ['logo_placement' => true],
            'assets' => [UploadedFile::fake()->image('our-logo.png')],
        ])
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->get(route('partner.submission.review'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Review')
            ->has('partner.branding_requirement.assets', 1)
            ->where('partner.branding_requirement.assets.0.name', 'our-logo.png')
            ->where('commsOptions.logo_placement', 'Logo placement on conference materials')
            ->missing('partner.branding_requirement.media_contact_name'),
        );
});

it('keeps the name of an uploaded logo for reviewers to download', function () {
    Storage::fake('public');

    [$user, $partner] = checklistPartnerFixture();

    $this->actingAs($user)
        ->put(route('partner.onboarding.update', 'organization'), [
            'logo' => UploadedFile::fake()->image('grit-ngo-mark.png'),
        ])
        ->assertSessionHas('success');

    $partner->refresh();

    expect($partner->logo_name)->toBe('grit-ngo-mark.png')
        // Storage still names the stored file by hash; only the label travels.
        ->and($partner->logo_path)->toContain("partners/{$partner->id}/logos/")
        ->and($partner->logo_path)->not->toContain('grit-ngo-mark.png');
});
