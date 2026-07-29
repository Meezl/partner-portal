<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Room;
use App\Models\SessionSchedule;
use App\Models\TimeSlot;
use App\Notifications\SessionScheduledNotification;
use App\Services\ConflictDetectionService;
use App\Services\RoomAllocationMatrixService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'time_slot_id' => ['required', 'exists:time_slots,id'],
        ]);

        $room = Room::findOrFail($validated['room_id']);
        $timeSlot = TimeSlot::findOrFail($validated['time_slot_id']);

        if ($room->conference_id !== $session->conference_id || $timeSlot->conference_id !== $session->conference_id) {
            return back()->with('error', 'The selected room or time slot does not belong to this conference.');
        }

        if ($session->sessionSchedule()->exists()) {
            return back()->with('error', 'This session already has a schedule. Please update the existing assignment instead.');
        }

        // Check for room + timeslot conflict
        $roomConflict = SessionSchedule::where('room_id', $validated['room_id'])
            ->where('time_slot_id', $validated['time_slot_id'])
            ->exists();

        if ($roomConflict) {
            return back()->with('error', 'This room is already booked for the selected time slot.');
        }

        $fitWarnings = app(RoomAllocationMatrixService::class)->fitWarnings($session, $room);

        if ($fitWarnings !== []) {
            return back()->with('error', $fitWarnings[0]);
        }

        // Check for partner + timeslot conflict (same partner can't have two sessions at the same time)
        $partnerConflict = SessionSchedule::where('time_slot_id', $validated['time_slot_id'])
            ->whereHas('conferenceSession', function ($q) use ($session) {
                $q->where('partner_id', $session->partner_id);
            })
            ->exists();

        if ($partnerConflict) {
            return back()->with('error', 'This partner already has a session scheduled for the selected time slot.');
        }

        $schedule = SessionSchedule::create([
            'conference_session_id' => $session->id,
            'room_id' => $validated['room_id'],
            'time_slot_id' => $validated['time_slot_id'],
            'assigned_by' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $session->update(['status' => SessionStatus::Scheduled]);
        $this->notifyPartner($schedule->load(['session.partner.user', 'room', 'timeSlot']));

        return back()->with('success', 'Session scheduled successfully.');
    }

    /**
     * Update an existing schedule assignment.
     */
    public function updateSchedule(Request $request, ConferenceSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'time_slot_id' => ['required', 'exists:time_slots,id'],
        ]);

        $room = Room::findOrFail($validated['room_id']);
        $timeSlot = TimeSlot::findOrFail($validated['time_slot_id']);

        if ($room->conference_id !== $session->conference_id || $timeSlot->conference_id !== $session->conference_id) {
            return back()->with('error', 'The selected room or time slot does not belong to this conference.');
        }

        $schedule = $session->sessionSchedule;

        if (! $schedule) {
            return back()->with('error', 'This session has no existing schedule to update.');
        }

        // Check for room + timeslot conflict (excluding current schedule)
        $roomConflict = SessionSchedule::where('room_id', $validated['room_id'])
            ->where('time_slot_id', $validated['time_slot_id'])
            ->where('id', '!=', $schedule->id)
            ->exists();

        if ($roomConflict) {
            return back()->with('error', 'This room is already booked for the selected time slot.');
        }

        $fitWarnings = app(RoomAllocationMatrixService::class)->fitWarnings($session, $room);

        if ($fitWarnings !== []) {
            return back()->with('error', $fitWarnings[0]);
        }

        // Check for partner + timeslot conflict (excluding current schedule)
        $partnerConflict = SessionSchedule::where('time_slot_id', $validated['time_slot_id'])
            ->where('id', '!=', $schedule->id)
            ->whereHas('conferenceSession', function ($q) use ($session) {
                $q->where('partner_id', $session->partner_id);
            })
            ->exists();

        if ($partnerConflict) {
            return back()->with('error', 'This partner already has a session scheduled for the selected time slot.');
        }

        $schedule->update([
            'room_id' => $validated['room_id'],
            'time_slot_id' => $validated['time_slot_id'],
            'assigned_by' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        $session->update(['status' => SessionStatus::Scheduled]);
        $this->notifyPartner($schedule->fresh(['session.partner.user', 'room', 'timeSlot']));

        return back()->with('success', 'Schedule updated successfully.');
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
