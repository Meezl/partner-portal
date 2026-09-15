<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'year', 'start_date', 'end_date', 'venue', 'description', 'registration_deadline', 'onboarding_deadline', 'lock_date', 'status', 'settings'])]
class Conference extends Model
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
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'registration_deadline' => 'date:Y-m-d',
            'onboarding_deadline' => 'date:Y-m-d',
            'lock_date' => 'date:Y-m-d',
            'settings' => 'array',
        ];
    }

    /**
     * The conference dates as one range: "March 2 – 5, 2027", "February 28 –
     * March 3, 2027", or "December 30, 2026 – January 2, 2027". Only as much of
     * the end date is repeated as differs from the start.
     */
    public function dateRange(): ?string
    {
        $start = $this->start_date;
        $end = $this->end_date;

        if (! $start || ! $end) {
            return null;
        }

        if ($start->isSameDay($end)) {
            return $start->format('F j, Y');
        }

        if (! $start->isSameYear($end)) {
            return $start->format('F j, Y').' – '.$end->format('F j, Y');
        }

        if (! $start->isSameMonth($end)) {
            return $start->format('F j').' – '.$end->format('F j, Y');
        }

        return $start->format('F j').' – '.$end->format('j, Y');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(SponsorshipPackage::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ConferenceSession::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function feedbackSurveys(): HasMany
    {
        return $this->hasMany(FeedbackSurvey::class);
    }
}
