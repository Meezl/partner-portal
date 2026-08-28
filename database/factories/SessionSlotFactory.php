<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\SessionSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionSlot>
 */
class SessionSlotFactory extends Factory
{
    protected $model = SessionSlot::class;

    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        return [
            'conference_id' => Conference::factory(),
            'slot_code' => "Parallel {$sequence}",
            'slot_category' => 'parallel',
            'track_label' => 'Parallel Track 1',
            'day_index' => 1,
            'date' => '2027-03-02',
            'time_label' => '11:00-12:30',
            'start_time' => '11:00:00',
            'end_time' => '12:30:00',
            'default_format' => 'panel',
            'capacity_hint' => 120,
            'is_assignable' => true,
            'claimed_by_session_id' => null,
            'claimed_at' => null,
            'held_by_session_id' => null,
            'held_at' => null,
            'sort_order' => $sequence,
        ];
    }

    public function notAssignable(): static
    {
        return $this->state(fn () => ['is_assignable' => false]);
    }
}
