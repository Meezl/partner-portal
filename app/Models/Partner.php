<?php

namespace App\Models;

use App\Enums\PartnerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['conference_id', 'user_id', 'organization_name', 'slug', 'contact_person', 'contact_title', 'email', 'phone', 'physical_address', 'physical_city', 'physical_postal_code', 'physical_country', 'billing_address', 'billing_city', 'billing_postal_code', 'billing_country', 'tax_details', 'customer_code', 'logo_path', 'logo_name', 'description', 'social_media', 'number_of_participants', 'exhibition_preferences', 'exhibition_requirements', 'status', 'onboarding_progress', 'submitted_at', 'confirmed_at', 'locked_at'])]
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
            'exhibition_requirements' => 'array',
            'onboarding_progress' => 'array',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * The composed addresses travel with the partner so invoices, the
     * agreement PDF and the admin screens all render the four parts the same
     * way instead of each stitching them together.
     *
     * @var list<string>
     */
    protected $appends = ['physical_address_formatted', 'billing_address_formatted'];

    public function getPhysicalAddressFormattedAttribute(): ?string
    {
        return $this->formatAddress(
            $this->physical_address,
            $this->physical_city,
            $this->physical_postal_code,
            $this->physical_country,
        );
    }

    public function getBillingAddressFormattedAttribute(): ?string
    {
        return $this->formatAddress(
            $this->billing_address,
            $this->billing_city,
            $this->billing_postal_code,
            $this->billing_country,
        );
    }

    /**
     * The four parts in postal order, skipping the ones a partner has not
     * filled in. Kept to a single line because both renderers — the agreement
     * PDF's "whose address is ..." sentence and the admin partner screen — set
     * it inline. Null when the whole address is empty, so callers can fall
     * back the way they did when the address was a single nullable column.
     */
    private function formatAddress(?string $address, ?string $city, ?string $postalCode, ?string $country): ?string
    {
        $parts = array_values(array_filter(
            [$address, trim(($postalCode ?? '').' '.($city ?? '')), $country],
            fn (?string $part) => filled(trim((string) $part)),
        ));

        return $parts === [] ? null : implode(', ', $parts);
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
