<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerContact>
 */
class PartnerContactFactory extends Factory
{
    protected $model = PartnerContact::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'role' => 'additional',
            'organization' => fake()->company(),
        ];
    }
}
