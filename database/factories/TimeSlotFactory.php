<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\TimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    protected $model = TimeSlot::class;

    public function definition(): array
    {
        $date = now()->addMonths(6)->startOfDay();

        return [
            'conference_id' => Conference::factory(),
            'date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'label' => 'Morning Session',
            'slot_type' => 'session',
        ];
    }
}
