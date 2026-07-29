<?php

namespace App\Http\Controllers\Partner;

use App\Enums\PartnerStatus;
use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\SponsorshipPackage;
use App\Notifications\EOISubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExpressionOfInterestController extends Controller
{
    /**
     * Show the Expression of Interest form.
     * Allows viewing/editing only while the partner is still in the EOI stage.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $partner = $user->partner;

        if ($partner && ! in_array($partner->status, [PartnerStatus::Draft, PartnerStatus::Rejected])) {
            return redirect()->route('partner.dashboard');
        }

        $conference = Conference::where('status', 'active')->latest()->first();

        $packages = SponsorshipPackage::where('conference_id', $conference?->id)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return Inertia::render('Partner/ExpressionOfInterest', [
            'packages' => $packages,
            'conference' => $conference,
            'partner' => $partner?->load('packages'),
        ]);
    }

    /**
     * Save Expression of Interest as draft.
     */
    public function saveDraft(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'physical_address' => ['nullable', 'string', 'max:500'],
            'package_id' => ['nullable', 'exists:sponsorship_packages,id'],
        ]);

        $conference = Conference::where('status', 'active')->latest()->first();
        $user = $request->user();
        $partner = $user->partner;

        $slug = $this->generateUniqueSlug(
            $validated['organization_name'] ?? $user->name,
            $partner?->id,
        );

        if ($partner) {
            $partner->update([
                'organization_name' => $validated['organization_name'] ?? $partner->organization_name,
                'contact_person' => $validated['contact_person'] ?? $partner->contact_person,
                'email' => $validated['email'] ?? $partner->email,
                'phone' => $validated['phone'] ?? $partner->phone,
                'physical_address' => $validated['physical_address'] ?? $partner->physical_address,
                'slug' => $slug,
            ]);
        } else {
            $partner = Partner::create([
                'user_id' => $user->id,
                'conference_id' => $conference?->id,
                'organization_name' => $validated['organization_name'] ?? '',
                'contact_person' => $validated['contact_person'] ?? '',
                'email' => $validated['email'] ?? $user->email,
                'phone' => $validated['phone'] ?? null,
                'physical_address' => $validated['physical_address'] ?? null,
                'slug' => $slug,
                'status' => PartnerStatus::Draft,
                'onboarding_progress' => [
                    'organization' => 0,
                    'sessions' => 0,
                    'communications' => 0,
                    'contacts' => 0,
                ],
            ]);
        }

        $this->syncUserPartnerLink($user, $partner);

        // Sync selected package via pivot table
        if (!empty($validated['package_id'])) {
            $partner->packages()->sync([$validated['package_id']]);
        }

        return back()->with('success', 'Draft saved successfully.');
    }

    /**
     * Store and submit a new Expression of Interest.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'physical_address' => ['nullable', 'string', 'max:500'],
            'package_id' => ['required', 'exists:sponsorship_packages,id'],
        ]);

        $conference = Conference::where('status', 'active')->latest()->first();
        $user = $request->user();
        $partner = $user->partner;

        $slug = $this->generateUniqueSlug(
            $validated['organization_name'],
            $partner?->id,
        );

        if ($partner) {
            $partner->update([
                'organization_name' => $validated['organization_name'],
                'contact_person' => $validated['contact_person'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'physical_address' => $validated['physical_address'] ?? null,
                'slug' => $slug,
                'status' => PartnerStatus::PendingAgreement,
                'submitted_at' => now(),
            ]);
        } else {
            $partner = Partner::create([
                'user_id' => $user->id,
                'conference_id' => $conference?->id,
                'organization_name' => $validated['organization_name'],
                'contact_person' => $validated['contact_person'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'physical_address' => $validated['physical_address'] ?? null,
                'slug' => $slug,
                'status' => PartnerStatus::PendingAgreement,
                'submitted_at' => now(),
                'onboarding_progress' => [
                    'organization' => 0,
                    'sessions' => 0,
                    'communications' => 0,
                    'contacts' => 0,
                ],
            ]);
        }

        $this->syncUserPartnerLink($user, $partner);

        // Sync selected package via pivot table
        $partner->packages()->sync([$validated['package_id']]);

        // Notify the partnerships team for visibility while the partner
        // continues directly into the commitment and agreement phase.
        Notification::route('mail', config('ahaic.central_email', 'info@ahaic.org'))
            ->notify(new EOISubmittedNotification($partner));

        return redirect()->route('partner.commitment.edit')
            ->with('success', 'Your Expression of Interest has been submitted. Please confirm your package details to continue.');
    }

    /**
     * Update an existing draft EOI.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'physical_address' => ['nullable', 'string', 'max:500'],
            'package_id' => ['required', 'exists:sponsorship_packages,id'],
        ]);

        $partner = $request->user()->partner;

        $slug = $this->generateUniqueSlug(
            $validated['organization_name'],
            $partner->id,
        );

        $partner->update([
            'organization_name' => $validated['organization_name'],
            'contact_person' => $validated['contact_person'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'physical_address' => $validated['physical_address'] ?? null,
            'slug' => $slug,
        ]);

        // Sync selected package via pivot table
        $partner->packages()->sync([$validated['package_id']]);

        return back()->with('success', 'Your Expression of Interest has been updated.');
    }

    private function syncUserPartnerLink($user, Partner $partner): void
    {
        if ($user->partner_id !== $partner->id) {
            $user->forceFill(['partner_id' => $partner->id])->save();
        }
    }

    /**
     * Generate a unique slug, excluding the current partner's own record.
     */
    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Partner::withTrashed()
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
