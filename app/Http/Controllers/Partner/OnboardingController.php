<?php

namespace App\Http\Controllers\Partner;

use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Models\BrandingRequirement;
use App\Models\PartnerContact;
use App\Services\OnboardingProgressService;
use App\Support\OnboardingChecklists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OnboardingController extends Controller
{
    /**
     * The communications section's two uploads, and the category each file is
     * filed under so a reviewer can tell guidelines from everything else.
     */
    private const ASSET_CATEGORIES = [
        'guideline_assets' => 'guidelines',
        'material_assets' => 'materials',
    ];

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
            $props['planOptions'] = OnboardingChecklists::ahaicPlans();
        }

        if ($section === 'contacts') {
            $props['contacts'] = $partner->contacts;
            $props['contactRoles'] = OnboardingProgressService::contactRoles();
            $props['roleLabels'] = OnboardingProgressService::storedContactRoles();
            $props['mandatoryRoles'] = OnboardingProgressService::mandatoryContactRoles();
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
            'social_media.linkedin' => ['nullable', 'string'],
            'exhibition_preferences' => ['nullable', 'string', 'max:1000'],
        ]);

        // Validate description word count (max 150 words)
        if (isset($validated['description']) && str_word_count($validated['description']) > 150) {
            throw ValidationException::withMessages([
                'description' => 'Description must not exceed 150 words.',
            ]);
        }

        $socialMedia = collect($validated['social_media'] ?? [])
            ->filter(fn ($value) => filled($value))
            ->all();

        $updateData = [
            'description' => $validated['description'] ?? $partner->description,
            'social_media' => $socialMedia,
            'exhibition_preferences' => $validated['exhibition_preferences'] ?? $partner->exhibition_preferences,
        ];

        if ($request->hasFile('logo')) {
            $disk = config('ahaic.disks.public');
            $logo = $request->file('logo');
            $path = $logo->store("partners/{$partner->id}/logos", $disk);

            $updateData['logo_path'] = Storage::disk($disk)->url($path);
            // Storage names the file by hash; keep the partner's own name so
            // reviewers can tell one downloaded logo from another.
            $updateData['logo_name'] = $logo->getClientOriginalName();
        }

        $partner->update($updateData);
    }

    /**
     * Update communications/branding section.
     */
    private function updateCommunicationsSection(Request $request, $partner): void
    {
        // Two separate asks, each a batch. Arr::wrap also accepts a lone file,
        // so a client posting a single upload still works.
        $uploads = [];

        foreach (self::ASSET_CATEGORIES as $field => $category) {
            $uploads[$field] = array_values(array_filter(Arr::wrap($request->file($field))));
        }

        $validated = validator(
            [...$request->except(array_keys(self::ASSET_CATEGORIES)), ...$uploads],
            [
                'plans_summary' => ['nullable', 'string', 'max:5000'],
                'guideline_assets' => ['array', 'max:20'],
                'guideline_assets.*' => ['file', 'mimes:zip,png,jpg,jpeg,pdf,svg', 'max:10240'],
                'material_assets' => ['array', 'max:20'],
                'material_assets.*' => ['file', 'mimes:zip,png,jpg,jpeg,pdf,svg', 'max:10240'],
                ...OnboardingChecklists::rulesFor('ahaic_plans', OnboardingChecklists::ahaicPlans()),
            ],
            [
                // The default wording names the field "guideline_assets.0",
                // which means nothing to a partner looking at filenames.
                'guideline_assets.max' => 'You can upload up to 20 branding guideline files at a time.',
                'guideline_assets.*.file' => 'Each branding guideline must be an uploaded file.',
                'guideline_assets.*.mimes' => 'Each branding guideline must be a ZIP, PNG, JPG, PDF or SVG file.',
                'guideline_assets.*.max' => 'Each branding guideline must be 10 MB or smaller.',
                'material_assets.max' => 'You can upload up to 20 branding materials at a time.',
                'material_assets.*.file' => 'Each branding material must be an uploaded file.',
                'material_assets.*.mimes' => 'Each branding material must be a ZIP, PNG, JPG, PDF or SVG file.',
                'material_assets.*.max' => 'Each branding material must be 10 MB or smaller.',
            ],
        )->validate();

        // Queried rather than read off the relation: a batch is appended to
        // whatever is already stored, so a stale relation would silently drop
        // the assets from an earlier save.
        $brandingRequirement = BrandingRequirement::firstWhere('partner_id', $partner->id);
        $existingAssets = $brandingRequirement?->assets ?? [];
        $disk = config('ahaic.disks.public');

        foreach (self::ASSET_CATEGORIES as $field => $category) {
            // Partners send a whole batch at once, so every file in the request
            // is stored rather than only the first. The name the partner gave
            // the file is kept: storage names them by hash, and a review screen
            // listing "qG9WgooMF….png" tells nobody what was uploaded.
            foreach ($uploads[$field] as $asset) {
                $path = $asset->store("partners/{$partner->id}/branding", $disk);

                $existingAssets[] = [
                    'name' => $asset->getClientOriginalName(),
                    'url' => Storage::disk($disk)->url($path),
                    'category' => $category,
                ];
            }
        }

        // `requirements` and `comms_checklist` are left out on purpose: the
        // form no longer asks for either, and writing them here would wipe the
        // answers partners gave before the section was reworked.
        BrandingRequirement::updateOrCreate(
            ['partner_id' => $partner->id],
            [
                'ahaic_plans' => OnboardingChecklists::normalise(
                    OnboardingChecklists::ahaicPlans(),
                    $validated['ahaic_plans'] ?? null,
                ),
                'plans_summary' => $validated['plans_summary'] ?? null,
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
            // Accepts a retired role too, so a contact stored as a
            // communications lead still saves when the rest of the form is
            // edited.
            'contacts.*.role' => ['required', Rule::in(array_keys(OnboardingProgressService::storedContactRoles()))],
            'contacts.*.designation' => ['nullable', 'string', 'max:255'],
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
                'designation' => $contact['designation'] ?? null,
            ]);
        }
    }

    private function markOnboardingStarted($partner): void
    {
        if ($partner->status === PartnerStatus::Confirmed) {
            $partner->update(['status' => PartnerStatus::Onboarding]);
        }
    }
}
