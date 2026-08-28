<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SessionFormat;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Notifications\SessionReviewedNotification;
use App\Services\SessionTimeRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Programme review queue: the partnerships team reads every submitted session
 * in full, then approves it into the programme or sends it back to the partner.
 */
class SessionReviewController extends Controller
{
    public function __construct(private readonly SessionTimeRequestService $timeRequests) {}

    public function index(Request $request): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();
        $status = $request->query('status', SessionStatus::Submitted->value);

        $sessions = ConferenceSession::query()
            ->when($conference, fn ($q) => $q->where('conference_id', $conference->id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with([
                'partner:id,organization_name,contact_person,email',
                'sessionSlot.defaultRoom:id,name',
                'requestedSessionSlot.defaultRoom:id,name',
                'schedule.room:id,name',
                'schedule.timeSlot:id,date,start_time,end_time,label',
            ])
            ->orderByRaw('submitted_at is null, submitted_at desc')
            ->get();

        return Inertia::render('Admin/Sessions/Index', [
            'sessions' => $sessions,
            'filters' => ['status' => $status],
            'statuses' => array_map(
                fn (SessionStatus $case) => ['value' => $case->value, 'label' => $case->label()],
                SessionStatus::cases(),
            ),
            'formats' => array_map(
                fn (SessionFormat $case) => $case->value,
                SessionFormat::cases(),
            ),
            'availableSlots' => $conference
                ? $this->timeRequests->availableSlotsFor($conference->id)
                : [],
            'counts' => ConferenceSession::query()
                ->when($conference, fn ($q) => $q->where('conference_id', $conference->id))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    /**
     * Approve a submitted session into the programme.
     */
    public function approve(Request $request, ConferenceSession $session): RedirectResponse
    {
        if ($session->status === SessionStatus::Confirmed) {
            return back()->with('error', 'This session is already approved.');
        }

        if ($session->status === SessionStatus::Draft) {
            return back()->with('error', 'This session is still a draft — the partner has not submitted it yet.');
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $previous = $session->status;
        $session->update(['status' => SessionStatus::Confirmed]);

        $this->record($request, $session, 'session_approved', $previous, SessionStatus::Confirmed);
        $this->notifyPartner($session, true, $validated['notes'] ?? null);

        return back()->with('success', "\"{$session->title}\" has been approved.");
    }

    /**
     * Send a session back to the partner as a draft so they can revise it.
     */
    public function reject(Request $request, ConferenceSession $session): RedirectResponse
    {
        if ($session->status === SessionStatus::Draft) {
            return back()->with('error', 'This session is already back with the partner.');
        }

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $previous = $session->status;

        // Returning it to the partner frees the slot: the session is no longer
        // part of the programme until they resubmit and it is approved again.
        $this->timeRequests->releaseAll($session);

        $session->update([
            'status' => SessionStatus::Draft,
            'submitted_at' => null,
        ]);

        $this->record($request, $session, 'session_returned_to_partner', $previous, SessionStatus::Draft);
        $this->notifyPartner($session, false, $validated['notes']);

        return back()->with('success', "\"{$session->title}\" has been sent back to the partner.");
    }

    /**
     * Update the two fields the programme team owns: the session title and the
     * date/time slot. Everything else stays with the partner.
     */
    public function update(Request $request, ConferenceSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'session_slot_id' => ['nullable', 'integer', 'exists:session_slots,id'],
        ]);

        $session->update(['title' => $validated['title']]);

        // Throws a validation error if the slot was taken in the meantime.
        $this->timeRequests->assignSlotDirectly(
            $session,
            $validated['session_slot_id'] ?? null,
            $request->user(),
        );

        return back()->with('success', 'Session updated.');
    }

    private function record(Request $request, ConferenceSession $session, string $action, SessionStatus $from, SessionStatus $to): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => ConferenceSession::class,
            'auditable_id' => $session->id,
            'old_values' => ['status' => $from->value],
            'new_values' => ['status' => $to->value],
        ]);
    }

    private function notifyPartner(ConferenceSession $session, bool $approved, ?string $notes): void
    {
        $user = $session->partner?->user;

        if ($user) {
            $user->notify(new SessionReviewedNotification($session, $approved, $notes));
        }
    }
}
