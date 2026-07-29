<?php

namespace Database\Factories;

use App\Models\BrandingRequirement;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandingRequirement>
 */
class BrandingRequirementFactory extends Factory
{
    protected $model = BrandingRequirement::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'requirements' => 'Use approved AHAIC cobranding guidelines.',
            'media_contact_name' => fake()->name(),
            'media_contact_email' => fake()->safeEmail(),
            'media_contact_phone' => fake()->phoneNumber(),
            'assets' => ['branding/logo-pack.zip'],
        ];
    }
}
