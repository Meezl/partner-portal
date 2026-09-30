<?php

namespace App\Models;

use App\Enums\SeatingArrangement;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conference_id', 'name', 'building', 'floor', 'capacity', 'theatre_capacity', 'round_capacity', 'format_suitability', 'equipment', 'is_active'])]
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

    /**
     * How many people this room seats in the given arrangement, or null when
     * the venue does not set the room up that way (the matrix's "N/A").
     */
    public function capacityFor(SeatingArrangement $seating): ?int
    {
        $capacity = $this->{$seating->capacityColumn()};

        // theatre_capacity was backfilled from `capacity`; fall back to it for
        // rooms created before the two columns existed.
        if ($capacity === null && $seating === SeatingArrangement::Theatre) {
            $capacity = $this->capacity;
        }

        return $capacity > 0 ? (int) $capacity : null;
    }

    /**
     * Whether the room seats this many people in the given arrangement. A room
     * the venue does not set up that way never fits.
     */
    public function seats(?int $participants, SeatingArrangement $seating): bool
    {
        $capacity = $this->capacityFor($seating);

        if ($capacity === null) {
            return false;
        }

        return $participants === null || $participants <= $capacity;
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
