<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Enums\UserRole;
use App\Models\ResourceAssignment;
use App\Models\SessionSchedule;
use App\Models\User;
use App\Services\ResourceAutoAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    /**
     * List all resource assignments.
     */
    public function index(): Response
    {
        $conference = Conference::where('status', 'active')->latest()->first();
        $assignments = ResourceAssignment::with([
            'schedule.session.partner',
            'schedule.room',
            'schedule.timeSlot',
        ])
            ->when($conference, fn ($query) => $query->whereHas('schedule.session', fn ($sessionQuery) => $sessionQuery->where('conference_id', $conference->id)))
            ->latest()
            ->get();

        $sessions = ConferenceSession::with(['schedule', 'partner'])
            ->when($conference, fn ($query) => $query->where('conference_id', $conference->id))
            ->whereIn('status', [SessionStatus::Scheduled, SessionStatus::Confirmed])
            ->get();

        $pool = User::whereIn('role', [UserRole::SessionLead, UserRole::Rapporteur])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return Inertia::render('Admin/Resources/Index', [
            'assignments' => $assignments,
            'sessions' => $sessions,
            'pool' => [
                'session_leads' => $pool->where('role', UserRole::SessionLead)->values(),
                'rapporteurs' => $pool->where('role', UserRole::Rapporteur)->values(),
            ],
        ]);
    }

    /**
     * Auto-assign 1 session lead + 2 rapporteurs to every scheduled session
     * for the active conference. Idempotent — existing manual picks are kept.
     */
    public function autoAssign(Request $request, ResourceAutoAssignmentService $service): RedirectResponse
    {
        $conference = Conference::where('status', 'active')->latest()->first();

        if (! $conference) {
            return back()->with('error', 'No active conference to assign resources for.');
        }

        $summary = $service->run($conference, $request->user()?->id);

        $msg = sprintf(
            'Auto-assign done: processed %d scheduled sessions, %d leads and %d rapporteurs newly assigned.',
            $summary['sessions_processed'],
            $summary['leads_assigned'],
            $summary['rapporteurs_assigned'],
        );

        if (! empty($summary['skipped'])) {
            $msg .= ' '.count($summary['skipped']).' slot(s) could not be filled — check the pool.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Store a new resource assignment.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_schedule_id' => ['required', 'exists:session_schedules,id'],
            'resource_type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        // Check for double-booking: same person assigned to same time slot
        $schedule = SessionSchedule::findOrFail($validated['session_schedule_id']);

        $doubleBooked = ResourceAssignment::where('name', $validated['name'])
            ->where('resource_type', $validated['resource_type'])
            ->whereHas('sessionSchedule', function ($q) use ($schedule) {
                $q->where('time_slot_id', $schedule->time_slot_id);
            })
            ->exists();

        if ($doubleBooked) {
            return back()->with('error', 'This resource is already assigned to another session in the same time slot.');
        }

        ResourceAssignment::create([
            ...$validated,
            'assigned_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Resource assigned successfully.');
    }

    /**
     * Update an existing resource assignment.
     */
    public function update(Request $request, ResourceAssignment $resource): RedirectResponse
    {
        $validated = $request->validate([
            'session_schedule_id' => ['required', 'exists:session_schedules,id'],
            'resource_type' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $resource->update($validated);

        return back()->with('success', 'Resource assignment updated successfully.');
    }

    /**
     * Delete a resource assignment.
     */
    public function destroy(ResourceAssignment $resource): RedirectResponse
    {
        $resource->delete();

        return back()->with('success', 'Resource assignment removed successfully.');
    }
}
