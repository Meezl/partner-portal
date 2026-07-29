<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\BrandingRequirement;
use App\Models\PartnerContact;
use App\Services\OnboardingProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OnboardingController extends Controller
{
    /**
     * Show onboarding overview with progress per section.
     */
    public function index(Request $request): Response
    {
        $partner = $request->user()->partner;

        $partner->load(['contacts', 'brandingRequirement', 'sessions']);
        $progress = app(OnboardingProgressService::class)->calculate($partner);
        $partner->update(['onboarding_progress' => $progress]);

        return Inertia::render('Partner/Onboarding/Index', [
            'partner' => $partner,
            'progress' => $progress,
        ]);
    }

    /**
     * Show specific onboarding section form.
     */
    public function edit(Request $request, string $section): Response
    {
        $partner = $request->user()->partner;

        $allowedSections = ['organization', 'sessions', 'communications', 'contacts'];

        if (! in_array($section, $allowedSections)) {
            abort(404, 'Unknown onboarding section.');
        }

        $partner->load(['contacts', 'brandingRequirement', 'sessions']);
        $progress = app(OnboardingProgressService::class)->calculate($partner);
        $partner->update(['onboarding_progress' => $progress]);

        $pageMap = [
            'organization' => 'Partner/Onboarding/OrganizationProfile',
            'sessions' => 'Partner/Onboarding/SessionSubmission',
            'communications' => 'Partner/Onboarding/CommunicationsBranding',
            'contacts' => 'Partner/Onboarding/Contacts',
        ];

        $props = [
            'partner' => $partner,
            'section' => $section,
            'progress' => $progress,
        ];

        if ($section === 'communications') {
            $props['branding'] = $partner->brandingRequirement;
        }

        if ($section === 'contacts') {
            $props['contacts'] = $partner->contacts;
        }

        if ($section === 'sessions') {
            $props['sessions'] = $partner->sessions;
        }

        return Inertia::render($pageMap[$section], $props);
    }

    /**
     * Validate and save section data.
     */
    public function update(Request $request, string $section): RedirectResponse
    {
        $partner = $request->user()->partner;

        try {
            switch ($section) {
                case 'organization':
                    $this->updateOrganizationSection($request, $partner);
                    break;

                case 'communications':
                    $this->updateCommunicationsSection($request, $partner);
                    break;

                case 'contacts':
                    $this->updateContactsSection($request, $partner);
                    break;

                default:
                    abort(404, 'Unknown onboarding section.');
            }

            $this->markOnboardingStarted($partner);
            $progress = app(OnboardingProgressService::class)->calculate(
                $partner->fresh(['contacts', 'brandingRequirement', 'sessions']),
            );
            $partner->update(['onboarding_progress' => $progress]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('partner.onboarding.index')
                ->with('error', ucfirst($section).' section could not be saved. Please try again.');
        }

        return redirect()
            ->route('partner.onboarding.index')
            ->with('success', ucfirst($section).' section updated successfully.');
    }

    /**
     * Update organization profile section.
     */
    private function updateOrganizationSection(Request $request, $partner): void
    {
        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'max:5120'],
            'description' => ['nullable', 'string'],
            'social_media' => ['nullable', 'array'],
            'social_media.website' => ['nullable', 'url'],
            'social_media.twitter' => ['nullable', 'string'],
            'social_media.linkedin' => ['nullable', 'string'],
            'social_media.facebook' => ['nullable', 'string'],
            'number_of_participants' => ['nullable', 'integer', 'min:1'],
            'exhibition_preferences' => ['nullable', 'string', 'max:1000'],
        ]);

        // Validate description word count (max 100 words)
        if (isset($validated['description']) && str_word_count($validated['description']) > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'description' => 'Description must not exceed 100 words.',
            ]);
        }

        $socialMedia = collect($validated['social_media'] ?? [])
            ->filter(fn ($value) => filled($value))
            ->all();

        $updateData = [
            'description' => $validated['description'] ?? $partner->description,
            'social_media' => $socialMedia,
            'number_of_participants' => $validated['number_of_participants'] ?? $partner->number_of_participants,
            'exhibition_preferences' => $validated['exhibition_preferences'] ?? $partner->exhibition_preferences,
        ];

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("partners/{$partner->id}/logos", 'public');
            $updateData['logo_path'] = Storage::disk('public')->url($path);
        }

        $partner->update($updateData);
    }

    /**
     * Update communications/branding section.
     */
    private function updateCommunicationsSection(Request $request, $partner): void
    {
        $validated = $request->validate([
            'requirements' => ['nullable', 'string', 'max:5000'],
            'media_contact_name' => ['nullable', 'string', 'max:255'],
            'media_contact_email' => ['nullable', 'email', 'max:255'],
            'media_contact_phone' => ['nullable', 'string', 'max:50'],
            'assets' => ['nullable', 'file', 'mimes:zip,png,jpg,jpeg,pdf,svg', 'max:10240'],
        ]);

        $brandingRequirement = $partner->brandingRequirement;
        $existingAssets = $brandingRequirement?->assets ?? [];

        if ($request->hasFile('assets')) {
            $path = $request->file('assets')->store("partners/{$partner->id}/branding", 'public');
            $existingAssets[] = Storage::disk('public')->url($path);
        }

        BrandingRequirement::updateOrCreate(
            ['partner_id' => $partner->id],
            [
                'requirements' => $validated['requirements'] ?? null,
                'media_contact_name' => $validated['media_contact_name'] ?? null,
                'media_contact_email' => $validated['media_contact_email'] ?? null,
                'media_contact_phone' => $validated['media_contact_phone'] ?? null,
                'assets' => $existingAssets,
            ],
        );
    }

    /**
     * Update contacts section.
     */
    private function updateContactsSection(Request $request, $partner): void
    {
        $validated = $request->validate([
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.email' => ['required', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.role' => ['required', 'string', 'max:100'],
            'contacts.*.organization' => ['nullable', 'string', 'max:255'],
        ]);

        // Sync partner contacts
        $partner->contacts()->delete();

        foreach ($validated['contacts'] as $contact) {
            PartnerContact::create([
                'partner_id' => $partner->id,
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone'] ?? null,
                'role' => $contact['role'],
                'organization' => $contact['organization'] ?? null,
            ]);
        }
    }

    private function markOnboardingStarted($partner): void
    {
        if ($partner->status === \App\Enums\PartnerStatus::Confirmed) {
            $partner->update(['status' => \App\Enums\PartnerStatus::Onboarding]);
        }
    }
}
