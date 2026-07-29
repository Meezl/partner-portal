<?php

namespace Database\Factories;

use App\Models\Conference;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conference>
 */
class ConferenceFactory extends Factory
{
    protected $model = Conference::class;

    public function definition(): array
    {
        $name = 'AHAIC '.$this->faker->unique()->year();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'year' => (int) $this->faker->year(),
            'start_date' => now()->addMonths(6)->startOfDay(),
            'end_date' => now()->addMonths(6)->addDays(4)->startOfDay(),
            'venue' => 'KICC, Nairobi',
            'description' => $this->faker->sentence(),
            'registration_deadline' => now()->addMonths(5),
            'onboarding_deadline' => now()->addMonths(5)->addWeeks(2),
            'lock_date' => now()->addMonths(5)->addWeeks(3),
            'status' => 'draft',
            'settings' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
