<?php

namespace App\Models;

use App\Enums\PackageTier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['conference_id', 'name', 'slug', 'tier', 'price', 'currency', 'max_partners', 'description', 'benefits', 'thought_leadership', 'visibility', 'session_slots', 'exhibition_space', 'complimentary_registrations', 'is_active', 'sort_order'])]
class SponsorshipPackage extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => PackageTier::class,
            'price' => 'decimal:2',
            'benefits' => 'array',
            'thought_leadership' => 'array',
            'visibility' => 'array',
            'complimentary_registrations' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'partner_package');
    }
}
