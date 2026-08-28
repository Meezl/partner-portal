<?php

namespace App\Http\Controllers\Partner;

use App\Enums\PartnerStatus;
use App\Enums\SessionFormat;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\ConferenceSession;
use App\Services\OnboardingProgressService;
use App\Services\SessionTimeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function __construct(private readonly SessionTimeRequestService $timeRequests) {}

    public function index(Request $request): Response
    {
        $partner = $request->user()->partner;

        $sessions = ConferenceSession::with([
            // defaultRoom so the room shows for slot-backed sessions too, not
            // just ones falling back to the board booking.
            'sessionSlot.defaultRoom:id,name',
            'requestedSessionSlot.defaultRoom:id,name',
            'pendingTimeRequest',
            // Falls back to the board booking when the session has no slot —
            // an admin can move a session on the board to a room/time the slot
            // matrix does not describe, which releases the slot.
            'schedule.room:id,name',
            'schedule.timeSlot:id,date,start_time,end_time,label',
        ])
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
            'conference' => $partner->conference,
            'availableSlots' => $this->timeRequests->availableSlotsFor($partner->conference_id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $partner = $request->user()->partner;

        $requestedSlot = $validated['session_slot_id'] ?? null;

        DB::transaction(function () use ($validated, $partner, $requestedSlot, $request) {
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
                'status' => SessionStatus::Draft,
            ]);

            // The chosen date/time is a request, not a booking: it holds the
            // slot and waits on the partnerships team.
            if ($requestedSlot) {
                $this->timeRequests->requestSlot(
                    $session,
                    $requestedSlot,
                    $request->user(),
                    $validated['slot_reason'] ?? null,
                );
            }
        });

        $this->markOnboardingStarted($partner);
        $this->recalculateProgress($partner);

        return redirect()->route('partner.sessions.index')
            ->with('success', $requestedSlot
                ? 'Session created. Your requested date and time has been sent to the partnerships team for approval.'
                : 'Session created successfully.');
    }

    public function edit(Request $request, ConferenceSession $session): Response
    {
        $partner = $request->user()->partner;

        if ($session->partner_id !== $partner->id) {
            abort(403, 'This session does not belong to your organization.');
        }

        return Inertia::render('Partner/Sessions/Edit', [
            'partner' => $partner,
            'session' => $session->load([
                'sessionSlot.defaultRoom:id,name',
                'requestedSessionSlot.defaultRoom:id,name',
                'pendingTimeRequest',
                // The board booking, so the panel can still show a room and
                // time when an admin placed the session somewhere the slot
                // matrix does not describe and the slot was released.
                'schedule.room:id,name',
                'schedule.timeSlot:id,date,start_time,end_time,label',
            ]),
            'formats' => SessionFormat::cases(),
            'conference' => $partner->conference,
            'availableSlots' => $this->timeRequests->availableSlotsFor($partner->conference_id, $session),
        ]);
    }

    public function update(Request $request, ConferenceSession $session): RedirectResponse
    {
        $partner = $request->user()->partner;

        if ($session->partner_id !== $partner->id) {
            abort(403, 'This session does not belong to your organization.');
        }

        $validated = $this->validatePayload($request);

        $newSlotId = $validated['session_slot_id'] ?? null;
        $timeRequested = false;

        DB::transaction(function () use ($validated, $session, $newSlotId, $request, &$timeRequested) {
            // Everything except date/time saves straight away.
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
            ]);

            // A different date/time opens a fresh approval request instead of
            // moving the session outright.
            if ($newSlotId && $newSlotId !== $session->session_slot_id) {
                $timeRequested = (bool) $this->timeRequests->requestSlot(
                    $session,
                    $newSlotId,
                    $request->user(),
                    $validated['slot_reason'] ?? null,
                );
            }
        });

        $this->markOnboardingStarted($partner);
        $this->recalculateProgress($partner);

        return redirect()->route('partner.sessions.index')
            ->with('success', $timeRequested
                ? 'Session updated. Your new date and time has been sent to the partnerships team for approval.'
                : 'Session updated successfully.');
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
            $this->timeRequests->releaseAll($session);
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
            'slot_reason' => ['nullable', 'string', 'max:1000'],
            'special_requirements' => ['nullable', 'array'],
            'special_requirements.av_equipment' => ['nullable', 'boolean'],
            'special_requirements.translation' => ['nullable', 'boolean'],
            'special_requirements.seating_type' => ['nullable', 'string', 'max:255'],
            'special_requirements.catering' => ['nullable', 'boolean'],
        ]);
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
        if ($partner->status === PartnerStatus::Confirmed) {
            $partner->update(['status' => PartnerStatus::Onboarding]);
        }
    }
}
