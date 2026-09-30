<?php

namespace App\Services;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Models\ChangeRequest;
use App\Models\ConferenceSession;
use App\Models\SessionSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Finds slots whose occupancy does not agree with the sessions that supposedly
 * occupy them.
 *
 * A slot is spoken for by exactly one session at a time, and that session must
 * point back at it — claimed_by_session_id ↔ session_slot_id, and
 * held_by_session_id ↔ requested_session_slot_id. When the two disagree the
 * slot reads as taken while nothing anywhere explains who has it, and the next
 * partner to pick it is told it "was just taken by another partner".
 *
 * Every problem this reports is a one-sided reference; fixing one always means
 * freeing the slot, never inventing a booking.
 */
class SlotOccupancyReconciler
{
    public function __construct(private readonly SessionScheduleSynchroniser $schedules) {}

    /**
     * Problems found, newest inventory first. Each entry is
     * ['slot' => SessionSlot, 'kind' => string, 'detail' => string].
     *
     * @return Collection<int, array{slot: SessionSlot, kind: string, detail: string}>
     */
    public function problems(?int $conferenceId = null): Collection
    {
        $slots = SessionSlot::query()
            ->when($conferenceId, fn ($query) => $query->where('conference_id', $conferenceId))
            ->where(fn ($query) => $query
                ->whereNotNull('claimed_by_session_id')
                ->orWhereNotNull('held_by_session_id'))
            ->orderBy('day_index')
            ->orderBy('sort_order')
            ->get();

        $sessionIds = $slots
            ->flatMap(fn (SessionSlot $slot) => [$slot->claimed_by_session_id, $slot->held_by_session_id])
            ->filter()
            ->unique();

        // withTrashed, because a soft-deleted session still holds its slot: the
        // row survives, so the database's nullOnDelete never fires.
        $sessions = ConferenceSession::withTrashed()
            ->whereIn('id', $sessionIds)
            ->get()
            ->keyBy('id');

        return $slots
            ->map(fn (SessionSlot $slot) => $this->problemFor($slot, $sessions))
            ->filter()
            ->values();
    }

    /**
     * Holds that have sat on a slot for longer than $days without the partner
     * submitting. These are legitimate reservations, not corruption, so they
     * are reported separately and only ever released on request.
     *
     * @return Collection<int, array{slot: SessionSlot, kind: string, detail: string}>
     */
    public function staleHolds(int $days, ?int $conferenceId = null): Collection
    {
        $cutoff = Carbon::now()->subDays($days);

        return SessionSlot::query()
            ->with('heldBySession.partner')
            ->when($conferenceId, fn ($query) => $query->where('conference_id', $conferenceId))
            ->whereNotNull('held_by_session_id')
            ->where('held_at', '<', $cutoff)
            ->orderBy('day_index')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (SessionSlot $slot) => [
                'slot' => $slot,
                'kind' => 'stale-hold',
                'detail' => sprintf(
                    'Held since %s by %s (%s), still unsubmitted.',
                    $slot->held_at?->diffForHumans() ?? 'an unknown time',
                    $slot->heldBySession?->partner?->organization_name ?? 'an unknown partner',
                    $slot->heldBySession?->title ?? 'untitled session',
                ),
            ]);
    }

    /**
     * Free every slot in $problems and close the change requests that went with
     * them. Returns the number of slots released.
     *
     * @param  Collection<int, array{slot: SessionSlot, kind: string, detail: string}>  $problems
     */
    public function resolve(Collection $problems): int
    {
        return DB::transaction(function () use ($problems) {
            $released = 0;

            foreach ($problems as $problem) {
                $this->release($problem['slot']);
                $released++;
            }

            return $released;
        });
    }

    /**
     * What is wrong with this slot's occupancy, if anything.
     *
     * @param  Collection<int, ConferenceSession>  $sessions
     * @return array{slot: SessionSlot, kind: string, detail: string}|null
     */
    private function problemFor(SessionSlot $slot, Collection $sessions): ?array
    {
        foreach ([
            ['claimed_by_session_id', 'session_slot_id', 'claim'],
            ['held_by_session_id', 'requested_session_slot_id', 'hold'],
        ] as [$slotColumn, $sessionColumn, $noun]) {
            $sessionId = $slot->{$slotColumn};

            if ($sessionId === null) {
                continue;
            }

            $session = $sessions->get($sessionId);

            if (! $session) {
                return $this->problem($slot, 'missing-session', sprintf(
                    'Slot records a %s by session #%d, which no longer exists.',
                    $noun,
                    $sessionId,
                ));
            }

            if ($session->trashed()) {
                return $this->problem($slot, 'deleted-session', sprintf(
                    'Slot records a %s by session #%d ("%s"), which was deleted.',
                    $noun,
                    $sessionId,
                    $session->title,
                ));
            }

            if ($session->{$sessionColumn} !== $slot->id) {
                return $this->problem($slot, 'one-sided-'.$noun, sprintf(
                    'Slot records a %s by session #%d ("%s"), but that session points at %s.',
                    $noun,
                    $sessionId,
                    $session->title,
                    $session->{$sessionColumn} ? 'slot #'.$session->{$sessionColumn} : 'no slot',
                ));
            }
        }

        return null;
    }

    /**
     * @return array{slot: SessionSlot, kind: string, detail: string}
     */
    private function problem(SessionSlot $slot, string $kind, string $detail): array
    {
        return ['slot' => $slot, 'kind' => $kind, 'detail' => $detail];
    }

    /**
     * Hand one slot back to the pool, tidying up behind it.
     */
    private function release(SessionSlot $slot): void
    {
        $sessionIds = array_values(array_unique(array_filter([
            $slot->claimed_by_session_id,
            $slot->held_by_session_id,
        ])));

        $slot->update([
            'claimed_by_session_id' => null,
            'claimed_at' => null,
            'held_by_session_id' => null,
            'held_at' => null,
        ]);

        ConferenceSession::withTrashed()
            ->where('session_slot_id', $slot->id)
            ->update(['session_slot_id' => null]);

        ConferenceSession::withTrashed()
            ->where('requested_session_slot_id', $slot->id)
            ->update(['requested_session_slot_id' => null]);

        foreach ($sessionIds as $sessionId) {
            ChangeRequest::where('conference_session_id', $sessionId)
                ->where('type', ChangeRequestType::Time)
                ->where('status', ChangeRequestStatus::Pending)
                ->update([
                    'status' => ChangeRequestStatus::AutoResolved,
                    'reviewed_at' => now(),
                    'resolution_notes' => 'Closed while reconciling slot occupancy.',
                ]);

            $session = ConferenceSession::withTrashed()->find($sessionId);

            if ($session) {
                $this->schedules->sync($session->refresh());
            }
        }
    }
}
