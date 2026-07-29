<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conference_session_id', 'room_id', 'time_slot_id', 'assigned_by', 'status', 'notes'])]
class SessionSchedule extends Model
{
    use HasFactory;

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConferenceSession::class, 'conference_session_id');
    }

    public function conferenceSession(): BelongsTo
    {
        return $this->session();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function resourceAssignments(): HasMany
    {
        return $this->hasMany(ResourceAssignment::class);
    }

    public function resources(): HasMany
    {
        return $this->resourceAssignments();
    }
}
