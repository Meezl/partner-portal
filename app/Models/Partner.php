<?php

namespace App\Models;

use App\Enums\PartnerStatus;
use App\Models\SponsorshipPackage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['conference_id', 'user_id', 'organization_name', 'slug', 'contact_person', 'email', 'phone', 'physical_address', 'billing_address', 'tax_details', 'customer_code', 'logo_path', 'description', 'social_media', 'number_of_participants', 'exhibition_preferences', 'status', 'onboarding_progress', 'submitted_at', 'confirmed_at', 'locked_at'])]
class Partner extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PartnerStatus::class,
            'social_media' => 'array',
            'onboarding_progress' => 'array',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(SponsorshipPackage::class, 'partner_package');
    }

    public function getSponsorshipPackageAttribute(): ?SponsorshipPackage
    {
        if ($this->relationLoaded('packages')) {
            return $this->packages->first();
        }

        return $this->packages()->first();
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ConferenceSession::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartnerContact::class);
    }

    public function brandingRequirement(): HasOne
    {
        return $this->hasOne(BrandingRequirement::class);
    }

    public function brandingRequirements(): HasOne
    {
        return $this->brandingRequirement();
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function feedbackSurveys(): HasMany
    {
        return $this->hasMany(FeedbackSurvey::class);
    }

    public function booths(): HasMany
    {
        return $this->hasMany(Booth::class);
    }
}
