<?php

use App\Enums\PartnerStatus;
use App\Models\BrandingRequirement;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\PartnerContact;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Everything a partner uploads or fills in has to be reviewable by the
 * programme team, not just by the partner on their own review page.
 */
it('gives the admin the partner profile, branding assets and contacts', function () {
    $conference = Conference::factory()->active()->create();
    $partnerUser = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($partnerUser)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Onboarding,
        'logo_path' => '/storage/partners/1/logos/our-logo.png',
        'description' => 'A community health organisation.',
        'social_media' => ['website' => 'https://example.org', 'twitter' => ''],
        'number_of_participants' => 40,
        'exhibition_preferences' => 'Corner booth please.',
        'exhibition_requirements' => [
            'additional_furniture' => true,
            'storage_space' => false,
            'other' => 'A lockable cabinet',
        ],
    ]);

    BrandingRequirement::factory()->create([
        'partner_id' => $partner->id,
        'requirements' => 'Use our green palette.',
        'comms_checklist' => ['social_media' => true, 'press_release' => false, 'other' => null],
        'assets' => [
            ['name' => 'brand-kit.zip', 'url' => '/storage/partners/1/branding/abc.zip'],
        ],
    ]);

    PartnerContact::factory()->create([
        'partner_id' => $partner->id,
        'name' => 'Grace Wanjiru',
        'role' => 'media_lead',
        'designation' => 'Press Officer',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.partners.show', $partner))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Partners/Show')
            // Organization profile, including the logo to view and download.
            ->where('partner.logo_path', '/storage/partners/1/logos/our-logo.png')
            ->where('partner.description', 'A community health organisation.')
            ->where('partner.social_media.website', 'https://example.org')
            ->where('partner.exhibition_requirements.additional_furniture', true)
            ->where('partner.exhibition_requirements.other', 'A lockable cabinet')
            // Branding assets, named so they mean something to a reviewer.
            ->where('partner.branding_requirement.assets.0.name', 'brand-kit.zip')
            ->where('partner.branding_requirement.assets.0.url', '/storage/partners/1/branding/abc.zip')
            ->where('partner.branding_requirement.comms_checklist.social_media', true)
            ->where('partner.branding_requirement.requirements', 'Use our green palette.')
            // Contacts, with the designation and a readable role.
            ->where('partner.contacts.0.designation', 'Press Officer')
            ->where('partner.contacts.0.role', 'media_lead')
            // The label sets, so the admin sees the same wording the partner did.
            ->where('exhibitionOptions.additional_furniture', 'Additional furniture')
            ->where('commsOptions.social_media', 'Social media mentions and amplification')
            ->where('contactRoles.media_lead', 'Media Contact'),
        );
});

it('shows the partner their own logo and assets on the review page', function () {
    $conference = Conference::factory()->active()->create();
    $user = User::factory()->partner()->create();
    $partner = Partner::factory()->forUser($user)->create([
        'conference_id' => $conference->id,
        'status' => PartnerStatus::Onboarding,
        'logo_path' => '/storage/partners/2/logos/mark.svg',
    ]);

    BrandingRequirement::factory()->create([
        'partner_id' => $partner->id,
        'assets' => [['name' => 'banner.png', 'url' => '/storage/partners/2/branding/xyz.png']],
    ]);

    $this->actingAs($user)
        ->get(route('partner.submission.review'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Partner/Review')
            ->where('partner.logo_path', '/storage/partners/2/logos/mark.svg')
            ->where('partner.branding_requirement.assets.0.name', 'banner.png'),
        );
});
