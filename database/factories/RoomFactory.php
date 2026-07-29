<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'conference_id' => Conference::factory(),
            'name' => fake()->unique()->numerify('Room ##'),
            'building' => 'Main Wing',
            'floor' => '1',
            'capacity' => 120,
            'format_suitability' => ['panel', 'workshop'],
            'equipment' => ['projector' => 'yes', 'microphone' => '4'],
            'is_active' => true,
        ];
    }
}
