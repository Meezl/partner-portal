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
    'is_assignable', 'claimed_by_session_id', 'claimed_at', 'sort_order',
])]
class SessionSlot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_assignable' => 'boolean',
            'claimed_at' => 'datetime',
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

    public function isAvailable(): bool
    {
        return $this->is_assignable && $this->claimed_by_session_id === null;
    }
}
