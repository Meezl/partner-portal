<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conference_id', 'slot_code', 'slot_category', 'track_label',
    'day_index', 'date', 'time_label', 'start_time', 'end_time',
    'default_room_id', 'default_format', 'capacity_hint',
    'is_assignable', 'claimed_by_session_id', 'claimed_at',
    'held_by_session_id', 'held_at', 'sort_order',
])]
class SessionSlot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_assignable' => 'boolean',
            'claimed_at' => 'datetime',
            'held_at' => 'datetime',
            'start_time' => 'string',
            'end_time' => 'string',
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function defaultRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'default_room_id');
    }

    public function claimedBySession(): BelongsTo
    {
        return $this->belongsTo(ConferenceSession::class, 'claimed_by_session_id');
    }

    public function heldBySession(): BelongsTo
    {
        return $this->belongsTo(ConferenceSession::class, 'held_by_session_id');
    }

    public function isAvailable(): bool
    {
        return $this->is_assignable
            && $this->claimed_by_session_id === null
            && $this->held_by_session_id === null;
    }

    /**
     * Human-readable date + time for this slot, e.g. "Tue, 3 Mar 2026 · 11:00-12:30".
     */
    public function scheduleLabel(): string
    {
        $date = $this->date?->format('D, j M Y');

        return $date ? "{$date} · {$this->time_label}" : $this->time_label;
    }

    /**
     * Snapshot stored on a change request so the decision record survives
     * later edits to the slot inventory.
     */
    public function toSnapshot(): array
    {
        return [
            'session_slot_id' => $this->id,
            'slot_code' => $this->slot_code,
            'track_label' => $this->track_label,
            'day_index' => $this->day_index,
            'date' => $this->date?->toDateString(),
            'time_label' => $this->time_label,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'label' => $this->scheduleLabel(),
        ];
    }
}
