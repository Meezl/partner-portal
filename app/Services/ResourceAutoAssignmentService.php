<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Conference;
use App\Models\ResourceAssignment;
use App\Models\SessionSchedule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Auto-assign 1 session lead + 2 rapporteurs to every scheduled session in
 * a conference, per the "Assignment matrix" tab of Rapporteur names and
 * contacts_2025.xlsx:
 *
 *   1. one session lead per session and two rapporteurs per session
 *   2. repeat for all sessions and all leads/rapporteurs (round-robin)
 *   3. assignments are editable afterwards — the run is idempotent for
 *      unassigned slots and never overwrites existing manual picks.
 *
 * Constraints enforced:
 *   - No one is assigned twice to the same time slot.
 *   - Pool is used least-loaded-first so the workload spreads evenly.
 */
class ResourceAutoAssignmentService
{
    public function run(Conference $conference, ?int $assignedBy = null): array
    {
        $sessionLeads = User::where('role', UserRole::SessionLead)->where('is_active', true)->get();
        $rapporteurs  = User::where('role', UserRole::Rapporteur)->where('is_active', true)->get();

        $schedules = SessionSchedule::with(['resourceAssignments', 'session'])
            ->whereHas('session', fn ($q) => $q->where('conference_id', $conference->id))
            ->orderBy('time_slot_id')
            ->get();

        if ($schedules->isEmpty() || ($sessionLeads->isEmpty() && $rapporteurs->isEmpty())) {
            return [
                'sessions_processed' => 0,
                'leads_assigned' => 0,
                'rapporteurs_assigned' => 0,
                'skipped' => [],
            ];
        }

        // Load counter (# assignments per user_id) so we always pick the least-loaded person next.
        $load = collect([...$sessionLeads, ...$rapporteurs])
            ->mapWithKeys(fn ($u) => [$u->id => (int) ResourceAssignment::where('user_id', $u->id)->count()]);

        // Time-slot occupancy index — { time_slot_id => [ user_id, ... ] }
        $timeSlotUsage = ResourceAssignment::query()
            ->join('session_schedules', 'session_schedules.id', '=', 'resource_assignments.session_schedule_id')
            ->whereNotNull('resource_assignments.user_id')
            ->select('resource_assignments.user_id', 'session_schedules.time_slot_id')
            ->get()
            ->groupBy('time_slot_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->all());

        $leadsAssigned = 0;
        $rapporteursAssigned = 0;
        $skipped = [];

        DB::transaction(function () use ($schedules, $sessionLeads, $rapporteurs, &$load, &$timeSlotUsage, $assignedBy, &$leadsAssigned, &$rapporteursAssigned, &$skipped) {
            foreach ($schedules as $schedule) {
                $existing = $schedule->resourceAssignments;
                $slotId = $schedule->time_slot_id;
                $usedInSlot = collect($timeSlotUsage->get($slotId, []));

                // ---- Session lead (1) ----
                $hasLead = $existing->firstWhere('resource_type', 'session_lead');
                if (! $hasLead) {
                    $pick = $this->pickLeastLoaded($sessionLeads, $usedInSlot, $load);
                    if ($pick) {
                        $this->createAssignment($schedule, $pick, 'session_lead', $assignedBy);
                        $load[$pick->id] = ($load[$pick->id] ?? 0) + 1;
                        $usedInSlot->push($pick->id);
                        $leadsAssigned++;
                    } else {
                        $skipped[] = "Session #{$schedule->conference_session_id}: no lead available for time slot #{$slotId}";
                    }
                }

                // ---- Rapporteurs (2 total) ----
                $rapporteurCount = $existing->where('resource_type', 'rapporteur')->count();
                for ($i = $rapporteurCount; $i < 2; $i++) {
                    $pick = $this->pickLeastLoaded($rapporteurs, $usedInSlot, $load);
                    if ($pick) {
                        $this->createAssignment($schedule, $pick, 'rapporteur', $assignedBy);
                        $load[$pick->id] = ($load[$pick->id] ?? 0) + 1;
                        $usedInSlot->push($pick->id);
                        $rapporteursAssigned++;
                    } else {
                        $skipped[] = "Session #{$schedule->conference_session_id}: only {$i}/2 rapporteurs assignable for time slot #{$slotId}";
                        break;
                    }
                }

                $timeSlotUsage[$slotId] = $usedInSlot->all();
            }
        });

        return [
            'sessions_processed' => $schedules->count(),
            'leads_assigned' => $leadsAssigned,
            'rapporteurs_assigned' => $rapporteursAssigned,
            'skipped' => $skipped,
        ];
    }

    private function pickLeastLoaded(Collection $pool, Collection $usedInSlot, Collection $load): ?User
    {
        return $pool
            ->reject(fn (User $u) => $usedInSlot->contains($u->id))
            ->sortBy(fn (User $u) => $load[$u->id] ?? 0)
            ->first();
    }

    private function createAssignment(SessionSchedule $schedule, User $user, string $type, ?int $assignedBy): void
    {
        ResourceAssignment::create([
            'session_schedule_id' => $schedule->id,
            'user_id' => $user->id,
            'resource_type' => $type,
            'name' => $user->name,
            'email' => $user->email,
            'assigned_by' => $assignedBy,
        ]);
    }
}
