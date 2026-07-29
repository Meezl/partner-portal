<?php

namespace Database\Factories;

use App\Enums\PartnerStatus;
use App\Models\Conference;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Partner $partner) {
            if ($partner->user && $partner->user->partner_id !== $partner->id) {
                $partner->user->forceFill(['partner_id' => $partner->id])->save();
            }
        });
    }

    public function definition(): array
    {
        $organization = $this->faker->unique()->company();

        return [
            'conference_id' => Conference::factory(),
            'user_id' => User::factory()->partner(),
            'organization_name' => $organization,
            'slug' => Str::slug($organization),
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'physical_address' => $this->faker->address(),
            'billing_address' => $this->faker->address(),
            'tax_details' => 'PIN-'.$this->faker->numerify('#######'),
            'customer_code' => null,
            'logo_path' => null,
            'description' => $this->faker->sentence(),
            'social_media' => ['linkedin' => 'https://linkedin.com/company/example'],
            'number_of_participants' => 4,
            'exhibition_preferences' => 'Corner booth preferred',
            'status' => PartnerStatus::Draft,
            'onboarding_progress' => [
                'organization' => 0,
                'sessions' => 0,
                'communications' => 0,
                'contacts' => 0,
            ],
            'submitted_at' => null,
            'confirmed_at' => null,
            'locked_at' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'email' => $user->email,
            'contact_person' => $user->name,
        ]);
    }
}
