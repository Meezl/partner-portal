<?php

namespace App\Http\Controllers\Partner;

use App\Enums\SessionFormat;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ConferenceSession;
use App\Models\SessionSlot;
use App\Services\OnboardingProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function index(Request $request): Response
    {
        $partner = $request->user()->partner;

        $sessions = ConferenceSession::with('sessionSlot')
            ->where('partner_id', $partner->id)
            ->latest()
            ->get();

        $progress = app(OnboardingProgressService::class)->calculate(
            $partner->fresh(['sessions', 'contacts', 'brandingRequirement']),
        );
        $partner->update(['onboarding_progress' => $progress]);

        return Inertia::render('Partner/Onboarding/SessionSubmission', [
            'partner' => $partner,
            'sessions' => $sessions,
            'progress' => $progress,
        ]);
    }

    public function create(Request $request): Response
    {
        $partner = $request->user()->partner;

        return Inertia::render('Partner/Sessions/Create', [
            'partner' => $partner,
            'formats' => SessionFormat::cases(),
            'availableSlots' => $this->availableSlotsFor($partner->conference_id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $partner = $request->user()->partner;

        DB::transaction(function () use ($validated, $partner) {
            $slot = $this->claimSlot($partner->conference_id, $validated['session_slot_id'] ?? null);

            $session = ConferenceSession::create([
                'partner_id' => $partner->id,
                'conference_id' => $partner->conference_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'format' => $validated['format'],
                'organizers' => $validated['organizers'] ?? [],
                'co_hosts' => $validated['co_hosts'] ?? [],
                'target_audience' => $validated['target_audience'] ?? null,
                'expected_participants' => $validated['expected_participants'] ?? null,
                'is_open' => $validated['is_open'] ?? false,
                'special_requirements' => $validated['special_requirements'] ?? [],
                'session_slot_id' => $slot?->id,
                'status' => SessionStatus::Draft,
            ]);

            if ($slot) {
                $slot->update([
                    'claimed_by_session_id' => $session->id,
                    'claimed_at' => now(),
                ]);
            }
        });

        $this->markOnboardingStarted($partner);
        $this->recalculateProgress($partner);

        return redirect()->route('partner.sessions.index')
            ->with('success', 'Session created successfully.');
    }

    public function edit(Request $request, ConferenceSession $session): Response
    {
        $partner = $request->user()->partner;

        if ($session->partner_id !== $partner->id) {
            abort(403, 'This session does not belong to your organization.');
        }

        return Inertia::render('Partner/Sessions/Edit', [
            'partner' => $partner,
            'session' => $session->load('sessionSlot'),
            'formats' => SessionFormat::cases(),
            'availableSlots' => $this->availableSlotsFor($partner->conference_id, $session->session_slot_id),
        ]);
    }

    public function update(Request $request, ConferenceSession $session): RedirectResponse
    {
        $partner = $request->user()->partner;

        if ($session->partner_id !== $partner->id) {
            abort(403, 'This session does not belong to your organization.');
        }

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated, $session) {
            $newSlotId = $validated['session_slot_id'] ?? null;

            if ($newSlotId !== $session->session_slot_id) {
                if ($session->session_slot_id) {
                    SessionSlot::where('id', $session->session_slot_id)
                        ->where('claimed_by_session_id', $session->id)
                        ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);
                }

                $slot = $this->claimSlot($session->conference_id, $newSlotId);

                if ($slot) {
                    $slot->update([
                        'claimed_by_session_id' => $session->id,
                        'claimed_at' => now(),
                    ]);
                }
            }

            $session->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'format' => $validated['format'],
                'organizers' => $validated['organizers'] ?? [],
                'co_hosts' => $validated['co_hosts'] ?? [],
                'target_audience' => $validated['target_audience'] ?? null,
                'expected_participants' => $validated['expected_participants'] ?? null,
                'is_open' => $validated['is_open'] ?? false,
                'special_requirements' => $validated['special_requirements'] ?? [],
                'session_slot_id' => $newSlotId,
            ]);
        });

        $this->markOnboardingStarted($partner);
        $this->recalculateProgress($partner);

        return redirect()->route('partner.sessions.index')
            ->with('success', 'Session updated successfully.');
    }

    public function destroy(Request $request, ConferenceSession $session): RedirectResponse
    {
        $partner = $request->user()->partner;

        if ($session->partner_id !== $partner->id) {
            abort(403, 'This session does not belong to your organization.');
        }

        if ($session->status !== SessionStatus::Draft) {
            return back()->with('error', 'Only draft sessions can be deleted.');
        }

        DB::transaction(function () use ($session) {
            if ($session->session_slot_id) {
                SessionSlot::where('id', $session->session_slot_id)
                    ->where('claimed_by_session_id', $session->id)
                    ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);
            }
            $session->delete();
        });

        $this->recalculateProgress($partner);

        return redirect()->route('partner.sessions.index')
            ->with('success', 'Session deleted successfully.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'format' => ['required', Rule::in(array_column(SessionFormat::cases(), 'value'))],
            'organizers' => ['nullable', 'array'],
            'organizers.*' => ['string', 'max:255'],
            'co_hosts' => ['nullable', 'array'],
            'co_hosts.*' => ['string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'expected_participants' => ['nullable', 'integer', 'min:1'],
            'is_open' => ['nullable', 'boolean'],
            'session_slot_id' => ['nullable', 'integer', 'exists:session_slots,id'],
            'special_requirements' => ['nullable', 'array'],
            'special_requirements.av_equipment' => ['nullable', 'boolean'],
            'special_requirements.translation' => ['nullable', 'boolean'],
            'special_requirements.seating_type' => ['nullable', 'string', 'max:255'],
            'special_requirements.catering' => ['nullable', 'boolean'],
        ]);
    }

    private function availableSlotsFor(int $conferenceId, ?int $includeSlotId = null)
    {
        return SessionSlot::with('defaultRoom:id,name')
            ->where('conference_id', $conferenceId)
            ->where('is_assignable', true)
            ->where(function ($q) use ($includeSlotId) {
                $q->whereNull('claimed_by_session_id');
                if ($includeSlotId) {
                    $q->orWhere('id', $includeSlotId);
                }
            })
            ->orderBy('day_index')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Pessimistically claim a slot inside an open transaction.
     */
    private function claimSlot(int $conferenceId, ?int $slotId): ?SessionSlot
    {
        if (! $slotId) {
            return null;
        }

        $slot = SessionSlot::where('id', $slotId)
            ->where('conference_id', $conferenceId)
            ->where('is_assignable', true)
            ->lockForUpdate()
            ->first();

        if (! $slot) {
            throw ValidationException::withMessages([
                'session_slot_id' => 'The selected slot is not available.',
            ]);
        }

        if ($slot->claimed_by_session_id !== null) {
            throw ValidationException::withMessages([
                'session_slot_id' => 'This slot was just claimed by another partner. Please choose another.',
            ]);
        }

        return $slot;
    }

    private function recalculateProgress($partner): void
    {
        $progress = app(OnboardingProgressService::class)->calculate(
            $partner->fresh(['sessions', 'contacts', 'brandingRequirement']),
        );
        $partner->update(['onboarding_progress' => $progress]);
    }

    private function markOnboardingStarted($partner): void
    {
        if ($partner->status === \App\Enums\PartnerStatus::Confirmed) {
            $partner->update(['status' => \App\Enums\PartnerStatus::Onboarding]);
        }
    }
}
