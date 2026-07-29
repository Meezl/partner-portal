<?php

namespace Database\Factories;

use App\Models\ResourceAssignment;
use App\Models\SessionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceAssignment>
 */
class ResourceAssignmentFactory extends Factory
{
    protected $model = ResourceAssignment::class;

    public function definition(): array
    {
        return [
            'session_schedule_id' => SessionSchedule::factory(),
            'user_id' => null,
            'resource_type' => 'moderator',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'assigned_by' => null,
        ];
    }
}
