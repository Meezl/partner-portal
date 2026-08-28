<?php

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use App\Enums\ChangeRequestType;
use App\Enums\SessionFormat;
use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['partner_id', 'conference_id', 'title', 'description', 'format', 'organizers', 'co_hosts', 'target_audience', 'expected_participants', 'is_open', 'special_requirements', 'session_lead_id', 'communications_lead_id', 'session_slot_id', 'requested_session_slot_id', 'status', 'submitted_at'])]
class ConferenceSession extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'conference_sessions';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => SessionFormat::class,
            'status' => SessionStatus::class,
            'organizers' => 'array',
            'co_hosts' => 'array',
            'special_requirements' => 'array',
            'is_open' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function sessionLead(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class, 'session_lead_id');
    }

    public function communicationsLead(): BelongsTo
    {
        return $this->belongsTo(PartnerContact::class, 'communications_lead_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SessionContact::class);
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(SessionSchedule::class);
    }

    public function sessionSchedule(): HasOne
    {
        return $this->schedule();
    }

    public function sessionSlot(): BelongsTo
    {
        return $this->belongsTo(SessionSlot::class);
    }

    /**
     * The slot this session has asked for, pending partnerships-team review.
     */
    public function requestedSessionSlot(): BelongsTo
    {
        return $this->belongsTo(SessionSlot::class, 'requested_session_slot_id');
    }

    /**
     * The open time change request, if the partner is awaiting a decision.
     */
    public function pendingTimeRequest(): HasOne
    {
        return $this->hasOne(ChangeRequest::class)
            ->where('type', ChangeRequestType::Time)
            ->where('status', ChangeRequestStatus::Pending)
            ->latestOfMany();
    }

    public function hasPendingTimeRequest(): bool
    {
        return $this->requested_session_slot_id !== null;
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }
}
