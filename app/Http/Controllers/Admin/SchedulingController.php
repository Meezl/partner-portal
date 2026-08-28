<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\SessionSchedule;
use App\Models\SessionSlot;
use App\Models\TimeSlot;
use App\Notifications\SessionScheduledNotification;
use App\Notifications\SessionUnscheduledNotification;
use App\Services\ConflictDetectionService;
use App\Services\RoomAllocationMatrixService;
use App\Services\SessionScheduleSynchroniser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SchedulingController extends Controller
{
    /**
     * Show the scheduling board.
     */
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();
        $sessions = ConferenceSession::with(['partner', 'sessionSchedule.room', 'sessionSchedule.timeSlot'])
            ->when($conference, fn ($query) => $query->where('conference_id', $conference->id))
            ->whereIn('status', [SessionStatus::Submitted, SessionStatus::Scheduled, SessionStatus::Confirmed])
            ->get();

        $rooms = Room::when($conference, fn ($query) => $query->where('conference_id', $conference->id))
            ->orderBy('name')
            ->get();
        $timeSlots = TimeSlot::when($conference, fn ($query) => $query->where('conference_id', $conference->id))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
        $schedules = SessionSchedule::with(['session.partner', 'room', 'timeSlot'])
            ->when($conference, fn ($query) => $query->whereHas('session', fn ($sessionQuery) => $sessionQuery->where('conference_id', $conference->id)))
            ->get();
        $matrix = app(RoomAllocationMatrixService::class)->build($rooms, $timeSlots, $schedules, $sessions);

        return Inertia::render('Admin/Scheduling/Index', [
            'conference' => $conference,
            'sessions' => $sessions,
            'rooms' => $rooms,
            'timeSlots' => $timeSlots,
            'schedules' => $schedules,
            'allocationSummary' => $matrix['summary'],
            'allocationDays' => $matrix['days'],
            'roomStats' => $matrix['room_stats'],
        ]);
    }

    /**
     * Get all submitted sessions for scheduling.
     */
    public function sessions(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();
        $sessions = ConferenceSession::where('status', SessionStatus::Submitted)
            ->when($conference, fn ($query) => $query->where('conference_id', $conference->id))
            ->with('partner')
            ->get();

        return Inertia::render('Admin/Scheduling/Sessions', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Assign a session to a room and time slot.
     */
    public function assignSession(Request $request, ConferenceSession $session): RedirectResponse
    {
        $validated = $this->validateAssignment($request);
        $timeSlot = TimeSlot::find($validated['time_slot_id']);

        if ($session->sessionSchedule()->exists()) {
            return back()->with('error', 'This session already has a schedule. Please update the existing assignment instead.');
        }

        if ($error = $this->assignmentError($session, $validated)) {
            return back()->with('error', $error);
        }

        $schedule = SessionSchedule::create([
            'conference_session_id' => $session->id,
            'room_id' => $validated['room_id'],
            'time_slot_id' => $validated['time_slot_id'],
            'assigned_by' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $session->update(['status' => SessionStatus::Scheduled]);
        $this->reconcileSlot($session, $validated, $timeSlot);
        $this->notifyPartner($schedule->load(['session.partner.user', 'room', 'timeSlot']));

        return back()->with('success', 'Session scheduled successfully.');
    }

    /**
     * Update an existing schedule assignment.
     */
    public function updateSchedule(Request $request, ConferenceSession $session): RedirectResponse
    {
        $validated = $this->validateAssignment($request);
        $schedule = $session->sessionSchedule;

        if (! $schedule) {
            return back()->with('error', 'This session has no existing schedule to update.');
        }

        if ($error = $this->assignmentError($session, $validated, $schedule)) {
            return back()->with('error', $error);
        }

        $unchanged = $schedule->room_id === (int) $validated['room_id']
            && $schedule->time_slot_id === (int) $validated['time_slot_id'];

        if ($unchanged) {
            return back()->with('success', 'No change — the session is already in that room and time slot.');
        }

        $schedule->update([
            'room_id' => $validated['room_id'],
            'time_slot_id' => $validated['time_slot_id'],
            'assigned_by' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $session->update(['status' => SessionStatus::Scheduled]);
        $this->reconcileSlot($session, $validated, TimeSlot::find($validated['time_slot_id']));
        $this->notifyPartner($schedule->fresh(['session.partner.user', 'room', 'timeSlot']));

        return back()->with('success', 'Schedule updated successfully.');
    }

    /**
     * Remove a session's room + time assignment, returning it to the
     * unscheduled pool. Cascades to the schedule's resource assignments.
     */
    public function destroySchedule(Request $request, ConferenceSession $session): RedirectResponse
    {
        $schedule = $session->sessionSchedule;

        if (! $schedule) {
            return back()->with('error', 'This session has no schedule to remove.');
        }

        $hadSlot = $session->session_slot_id !== null;

        // Capture where it was before we delete it, so the partner is told what
        // they are losing rather than just that something changed.
        $schedule->loadMissing(['room', 'timeSlot']);
        $previous = $schedule->room && $schedule->timeSlot
            ? $schedule->room->name.' on '.$schedule->timeSlot->date->format('D j M')
                .', '.substr((string) $schedule->timeSlot->start_time, 0, 5)
                .'–'.substr((string) $schedule->timeSlot->end_time, 0, 5)
            : null;

        DB::transaction(function () use ($schedule, $session) {
            // resource_assignments cascade on delete at the DB level.
            $schedule->delete();

            // The slot is what produced this booking, so removing the booking
            // has to release it too — otherwise the session would still claim a
            // time it is no longer scheduled for, and the next sync would
            // silently recreate the booking.
            if ($session->session_slot_id) {
                SessionSlot::where('id', $session->session_slot_id)
                    ->where('claimed_by_session_id', $session->id)
                    ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);

                $session->update(['session_slot_id' => null]);
            }

            // Back to the pool: only sessions the partner already submitted can
            // be re-assigned, so Submitted is the correct resting state.
            $session->update(['status' => SessionStatus::Submitted]);
        });

        if ($partnerUser = $session->partner?->user) {
            $partnerUser->notify(new SessionUnscheduledNotification($session, $previous));
        }

        return back()->with('success', $hadSlot
            ? 'Assignment removed and the partner notified. The session is back in the unscheduled list and its slot is free again.'
            : 'Assignment removed and the partner notified. The session is back in the unscheduled list.');
    }

    /**
     * Shared validation for assign + update.
     *
     * @return array{room_id: int, time_slot_id: int}
     */
    /**
     * Keep the partner-facing slot honest after a board placement.
     *
     * The board may override the slot, but a session must never end up holding
     * two different times. If the new room + time matches a real slot we move
     * the session onto it; if it does not, the board wins and the slot is
     * released back to the pool so nothing contradicts the booking.
     */
    private function reconcileSlot(ConferenceSession $session, array $validated, ?TimeSlot $timeSlot): void
    {
        if (! $timeSlot) {
            return;
        }

        $match = app(SessionScheduleSynchroniser::class)
            ->slotMatching($session, (int) $validated['room_id'], $timeSlot);

        if ($match && $match->id === $session->session_slot_id) {
            return; // Already consistent.
        }

        // Whatever it held before is no longer where the session is.
        if ($session->session_slot_id) {
            SessionSlot::where('id', $session->session_slot_id)
                ->where('claimed_by_session_id', $session->id)
                ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);
        }

        if ($match) {
            $match->update([
                'claimed_by_session_id' => $session->id,
                'claimed_at' => now(),
                'held_by_session_id' => null,
                'held_at' => null,
            ]);
        }

        $session->update(['session_slot_id' => $match?->id]);
    }

    private function validateAssignment(Request $request): array
    {
        return $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'time_slot_id' => ['required', 'exists:time_slots,id'],
        ]);
    }

    /**
     * Every reason a session may not occupy the given room + time slot, or null
     * when the assignment is allowed. Shared by assign and update so the two
     * can never drift apart; $ignore excludes the row being updated.
     */
    private function assignmentError(
        ConferenceSession $session,
        array $validated,
        ?SessionSchedule $ignore = null,
    ): ?string {
        $room = Room::findOrFail($validated['room_id']);
        $timeSlot = TimeSlot::findOrFail($validated['time_slot_id']);

        if ($room->conference_id !== $session->conference_id || $timeSlot->conference_id !== $session->conference_id) {
            return 'The selected room or time slot does not belong to this conference.';
        }

        $clash = fn () => SessionSchedule::query()
            ->where('time_slot_id', $validated['time_slot_id'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id));

        if ((clone $clash())->where('room_id', $validated['room_id'])->exists()) {
            return 'This room is already booked for the selected time slot.';
        }

        $fitWarnings = app(RoomAllocationMatrixService::class)->fitWarnings($session, $room);

        if ($fitWarnings !== []) {
            return $fitWarnings[0];
        }

        $partnerClash = (clone $clash())
            ->whereHas('conferenceSession', fn ($q) => $q->where('partner_id', $session->partner_id))
            ->exists();

        if ($partnerClash) {
            return 'This partner already has a session scheduled for the selected time slot.';
        }

        return null;
    }

    /**
     * Detect and return scheduling conflicts.
     */
    public function conflicts(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();
        $conflicts = $conference
            ? app(ConflictDetectionService::class)->detectConflicts($conference)
            : [];

        return Inertia::render('Admin/Scheduling/Conflicts', [
            'conflicts' => $conflicts,
        ]);
    }

    private function notifyPartner(SessionSchedule $schedule): void
    {
        $partnerUser = $schedule->session?->partner?->user;

        if ($partnerUser) {
            $partnerUser->notify(new SessionScheduledNotification($schedule));
        }
    }
}
