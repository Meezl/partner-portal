<?php

namespace App\Services;

use App\Models\ConferenceSession;
use App\Models\SessionSchedule;
use App\Models\SessionSlot;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the scheduling board in step with the partner-facing slot matrix.
 *
 * The two used to be independent: a session could hold a SessionSlot saying
 * "Mar 1, 14:00–15:30, AD12" while its SessionSchedule said "Mar 1, 11:30–13:00,
 * AD10". A SessionSlot carries a date, a start/end time *and* a default room,
 * so it can fully describe a board booking — the reverse is not true. The slot
 * is therefore the source of truth and the booking is derived from it.
 */
class SessionScheduleSynchroniser
{
    /**
     * Make the session's board booking match its approved slot.
     *
     * No approved slot means no derived booking, so any previously derived one
     * is removed. Returns the resulting schedule, or null when the session has
     * no slot.
     */
    public function sync(ConferenceSession $session, ?int $assignedBy = null): ?SessionSchedule
    {
        return DB::transaction(function () use ($session, $assignedBy) {
            $slot = $session->session_slot_id
                ? SessionSlot::with('defaultRoom')->find($session->session_slot_id)
                : null;

            if (! $slot || ! $slot->default_room_id) {
                // Nothing to derive from. A booking made directly on the board
                // (no slot behind it) is left alone — see boardOnly().
                if ($slot === null) {
                    $session->schedule()->delete();
                }

                return null;
            }

            $timeSlot = $this->timeSlotFor($slot);

            return SessionSchedule::updateOrCreate(
                ['conference_session_id' => $session->id],
                [
                    'room_id' => $slot->default_room_id,
                    'time_slot_id' => $timeSlot->id,
                    'assigned_by' => $assignedBy,
                    'status' => 'scheduled',
                ],
            );
        });
    }

    /**
     * Find the SessionSlot that exactly matches a board placement, if one
     * exists. Used when an admin moves a session on the board: if the new
     * room + time corresponds to a real slot we re-point the session at it, so
     * the two systems stay in agreement instead of drifting.
     */
    public function slotMatching(ConferenceSession $session, int $roomId, TimeSlot $timeSlot): ?SessionSlot
    {
        return SessionSlot::query()
            ->where('conference_id', $session->conference_id)
            ->where('default_room_id', $roomId)
            ->whereDate('date', $timeSlot->date)
            ->where('start_time', $timeSlot->start_time)
            ->where('end_time', $timeSlot->end_time)
            ->where(function ($q) use ($session) {
                $q->whereNull('claimed_by_session_id')
                    ->orWhere('claimed_by_session_id', $session->id);
            })
            ->first();
    }

    /**
     * The TimeSlot covering a slot's window, created if the conference does not
     * already define one. The matrix has a few windows (the 18:00–20:00
     * receptions) with no TimeSlot row, so this cannot assume one exists.
     */
    private function timeSlotFor(SessionSlot $slot): TimeSlot
    {
        return TimeSlot::firstOrCreate(
            [
                'conference_id' => $slot->conference_id,
                'date' => $slot->date,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
            ],
            [
                'label' => $slot->track_label ?: $slot->slot_code,
                'slot_type' => $slot->slot_category,
            ],
        );
    }
}
