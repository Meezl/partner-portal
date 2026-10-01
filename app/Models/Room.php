<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conference_id', 'name', 'building', 'floor', 'capacity', 'format_suitability', 'equipment', 'is_active'])]
class Room extends Model
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
            'format_suitability' => 'array',
            'equipment' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function conference(): BelongsTo
    {
        return $this->belongsTo(Conference::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SessionSchedule::class);
    }

    public function sessionSchedules(): HasMany
    {
        return $this->schedules();
    }
}
