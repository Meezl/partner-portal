<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conference_session_id', 'room_id', 'time_slot_id', 'assigned_by', 'confirmed_by_partner_at', 'status', 'notes'])]
class SessionSchedule extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_by_partner_at' => 'datetime',
        ];
    }

    /** Whether the partner has said the allocated room and time work for them. */
    public function confirmedByPartner(): bool
    {
        return $this->confirmed_by_partner_at !== null;
    }

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

    /**
     * Where this booking puts the session, for telling a partner what they
     * have or what they are losing — "MH1 on Mon 1 Mar, 11:30–13:00".
     *
     * Null when the booking has no room or time to describe.
     */
    public function placementLabel(): ?string
    {
        $this->loadMissing(['room', 'timeSlot']);

        if (! $this->room || ! $this->timeSlot) {
            return null;
        }

        return $this->room->name.' on '.$this->timeSlot->date->format('D j M')
            .', '.substr((string) $this->timeSlot->start_time, 0, 5)
            .'–'.substr((string) $this->timeSlot->end_time, 0, 5);
    }

    public function resources(): HasMany
    {
        return $this->resourceAssignments();
    }
}
