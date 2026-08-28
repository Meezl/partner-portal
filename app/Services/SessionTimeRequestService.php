<?php

namespace App\Services;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Models\ChangeRequest;
use App\Models\ConferenceSession;
use App\Models\SessionSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the date/time lifecycle for a partner session.
 *
 * A partner never books a slot directly. Choosing a slot places a *hold* on it
 * and opens a Time change request; the partnerships team then approves it (the
 * hold becomes a claim and the slot lands on the session) or rejects it (the
 * hold is released and any previously approved slot stands).
 */
class SessionTimeRequestService
{
    public function __construct(private readonly SessionScheduleSynchroniser $schedules) {}

    /**
     * Slots a session may choose from: assignable, and neither claimed nor held
     * by anyone else. The session's own approved/requested slots stay visible so
     * the picker can render the current selection.
     */
    public function availableSlotsFor(int $conferenceId, ?ConferenceSession $session = null): Collection
    {
        $ownSlotIds = array_values(array_filter([
            $session?->session_slot_id,
            $session?->requested_session_slot_id,
        ]));

        return SessionSlot::with('defaultRoom:id,name')
            ->where('conference_id', $conferenceId)
            ->where('is_assignable', true)
            ->where(function ($q) use ($ownSlotIds) {
                $q->where(function ($free) {
                    $free->whereNull('claimed_by_session_id')->whereNull('held_by_session_id');
                });

                if ($ownSlotIds) {
                    $q->orWhereIn('id', $ownSlotIds);
                }
            })
            ->orderBy('day_index')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Open a time request for $slotId, holding the slot until it is reviewed.
     *
     * No-ops when the slot is already the session's approved slot. Refuses while
     * an earlier request on the same session is still pending.
     */
    public function requestSlot(ConferenceSession $session, int $slotId, User $requestedBy, ?string $reason = null): ?ChangeRequest
    {
        if ($session->session_slot_id === $slotId && ! $session->hasPendingTimeRequest()) {
            return null;
        }

        if ($session->hasPendingTimeRequest() && $session->requested_session_slot_id !== $slotId) {
            throw ValidationException::withMessages([
                'session_slot_id' => 'A time change for this session is already awaiting approval from the partnerships team.',
            ]);
        }

        if ($session->requested_session_slot_id === $slotId) {
            return null;
        }

        return DB::transaction(function () use ($session, $slotId, $requestedBy, $reason) {
            $slot = $this->lockAvailableSlot($session->conference_id, $slotId);

            $slot->update([
                'held_by_session_id' => $session->id,
                'held_at' => now(),
            ]);

            $session->update(['requested_session_slot_id' => $slot->id]);

            return ChangeRequest::create([
                'conference_session_id' => $session->id,
                'partner_id' => $session->partner_id,
                'requested_by' => $requestedBy->id,
                'type' => ChangeRequestType::Time,
                'current_value' => $session->sessionSlot?->toSnapshot(),
                'requested_value' => $slot->toSnapshot(),
                'reason' => $reason,
                'status' => ChangeRequestStatus::Pending,
            ]);
        });
    }

    /**
     * Approve a pending time request: promote the held slot to a claim, release
     * whatever the session held before, and stamp the reviewer.
     */
    public function approve(ChangeRequest $changeRequest, User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($changeRequest, $reviewer, $notes) {
            $session = $changeRequest->session;
            $slotId = $changeRequest->requested_value['session_slot_id'] ?? null;

            if ($session && $slotId) {
                // Release the slot the session previously occupied.
                if ($session->session_slot_id && $session->session_slot_id !== $slotId) {
                    SessionSlot::where('id', $session->session_slot_id)
                        ->where('claimed_by_session_id', $session->id)
                        ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);
                }

                SessionSlot::where('id', $slotId)->update([
                    'claimed_by_session_id' => $session->id,
                    'claimed_at' => now(),
                    'held_by_session_id' => null,
                    'held_at' => null,
                ]);

                $session->update([
                    'session_slot_id' => $slotId,
                    'requested_session_slot_id' => null,
                ]);

                // The board booking follows the approved slot.
                $this->schedules->sync($session->refresh(), $reviewer->id);
            }

            $changeRequest->update([
                'status' => ChangeRequestStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'resolution_notes' => $notes,
            ]);
        });
    }

    /**
     * Reject a pending time request: drop the hold and leave the session on its
     * previously approved slot (if any).
     */
    public function reject(ChangeRequest $changeRequest, User $reviewer, string $notes): void
    {
        DB::transaction(function () use ($changeRequest, $reviewer, $notes) {
            $session = $changeRequest->session;
            $slotId = $changeRequest->requested_value['session_slot_id'] ?? null;

            if ($session && $slotId) {
                SessionSlot::where('id', $slotId)
                    ->where('held_by_session_id', $session->id)
                    ->update(['held_by_session_id' => null, 'held_at' => null]);

                $session->update(['requested_session_slot_id' => null]);
            }

            $changeRequest->update([
                'status' => ChangeRequestStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'resolution_notes' => $notes,
            ]);
        });
    }

    /**
     * Set a session's slot directly, with no approval round-trip.
     *
     * The partnerships team *is* the approver, so when they change a time from
     * the review screen it takes effect immediately. Any pending partner
     * request is superseded: its hold is released and it is closed as
     * auto-resolved, so the change request queue does not keep asking about a
     * decision that has already been made.
     */
    public function assignSlotDirectly(ConferenceSession $session, ?int $slotId, User $actor): void
    {
        DB::transaction(function () use ($session, $slotId, $actor) {
            $this->supersedePendingRequest($session, $actor);

            if ($session->session_slot_id === $slotId) {
                $this->schedules->sync($session, $actor->id);

                return;
            }

            // Free whatever the session held before.
            if ($session->session_slot_id) {
                SessionSlot::where('id', $session->session_slot_id)
                    ->where('claimed_by_session_id', $session->id)
                    ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);
            }

            if ($slotId === null) {
                $session->update(['session_slot_id' => null]);
                $this->schedules->sync($session->refresh(), $actor->id);

                return;
            }

            $slot = $this->lockAvailableSlot($session->conference_id, $slotId);

            $slot->update([
                'claimed_by_session_id' => $session->id,
                'claimed_at' => now(),
                'held_by_session_id' => null,
                'held_at' => null,
            ]);

            $session->update(['session_slot_id' => $slot->id]);
            $this->schedules->sync($session->refresh(), $actor->id);
        });
    }

    /**
     * Close any open time request on the session and drop its hold.
     */
    private function supersedePendingRequest(ConferenceSession $session, User $actor): void
    {
        if (! $session->hasPendingTimeRequest()) {
            return;
        }

        SessionSlot::where('held_by_session_id', $session->id)
            ->update(['held_by_session_id' => null, 'held_at' => null]);

        ChangeRequest::where('conference_session_id', $session->id)
            ->where('type', ChangeRequestType::Time)
            ->where('status', ChangeRequestStatus::Pending)
            ->update([
                'status' => ChangeRequestStatus::AutoResolved,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'resolution_notes' => 'Superseded — the programme team set the time directly.',
            ]);

        $session->update(['requested_session_slot_id' => null]);
    }

    /**
     * Grant a session's pending slot without a review round-trip.
     *
     * Slots are first-come, first-served: once a partner submits, the slot they
     * have been holding becomes theirs and the board booking is created. Later
     * *changes* still go through the partnerships team — this only settles the
     * request that was open at submission time.
     */
    public function grantPendingOnSubmission(ConferenceSession $session): void
    {
        if (! $session->hasPendingTimeRequest()) {
            // Nothing pending, but the session may still need its booking
            // derived (e.g. a slot approved before this behaviour existed).
            $this->schedules->sync($session);

            return;
        }

        DB::transaction(function () use ($session) {
            $slotId = $session->requested_session_slot_id;

            if ($session->session_slot_id && $session->session_slot_id !== $slotId) {
                SessionSlot::where('id', $session->session_slot_id)
                    ->where('claimed_by_session_id', $session->id)
                    ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);
            }

            SessionSlot::where('id', $slotId)->update([
                'claimed_by_session_id' => $session->id,
                'claimed_at' => now(),
                'held_by_session_id' => null,
                'held_at' => null,
            ]);

            $session->update([
                'session_slot_id' => $slotId,
                'requested_session_slot_id' => null,
            ]);

            ChangeRequest::where('conference_session_id', $session->id)
                ->where('type', ChangeRequestType::Time)
                ->where('status', ChangeRequestStatus::Pending)
                ->update([
                    'status' => ChangeRequestStatus::Approved,
                    'reviewed_at' => now(),
                    'resolution_notes' => 'Granted automatically on submission — slots are first-come, first-served.',
                ]);

            $this->schedules->sync($session->refresh());
        });
    }

    /**
     * Release every hold and claim a session holds, and close its open time
     * requests. Used when a draft session is deleted.
     */
    public function releaseAll(ConferenceSession $session): void
    {
        DB::transaction(function () use ($session) {
            SessionSlot::where('claimed_by_session_id', $session->id)
                ->update(['claimed_by_session_id' => null, 'claimed_at' => null]);

            SessionSlot::where('held_by_session_id', $session->id)
                ->update(['held_by_session_id' => null, 'held_at' => null]);

            ChangeRequest::where('conference_session_id', $session->id)
                ->where('type', ChangeRequestType::Time)
                ->where('status', ChangeRequestStatus::Pending)
                ->update(['status' => ChangeRequestStatus::AutoResolved]);

            $session->update([
                'session_slot_id' => null,
                'requested_session_slot_id' => null,
            ]);

            // No slot means no derived booking.
            $this->schedules->sync($session->refresh());
        });
    }

    /**
     * Fetch a slot for update, failing validation if it is not bookable.
     */
    private function lockAvailableSlot(int $conferenceId, int $slotId): SessionSlot
    {
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

        if ($slot->claimed_by_session_id !== null || $slot->held_by_session_id !== null) {
            throw ValidationException::withMessages([
                'session_slot_id' => 'This slot was just taken by another partner. Please choose another.',
            ]);
        }

        return $slot;
    }
}
