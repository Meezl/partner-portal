<?php

namespace Database\Factories;

use App\Enums\PackageTier;
use App\Models\Conference;
use App\Models\SponsorshipPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SponsorshipPackage>
 */
class SponsorshipPackageFactory extends Factory
{
    protected $model = SponsorshipPackage::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->word()).' Partnership';

        return [
            'conference_id' => Conference::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'tier' => PackageTier::Gold,
            'price' => 25000,
            'currency' => 'USD',
            'max_partners' => 5,
            'description' => $this->faker->sentence(),
            'benefits' => ['Main stage visibility', 'Partner booth'],
            'thought_leadership' => ['Speaking slot'],
            'visibility' => ['Website logo placement'],
            'session_slots' => 1,
            'exhibition_space' => '3x3 booth',
            'complimentary_registrations' => ['vip' => 2, 'standard' => 4, 'total' => 6],
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
