<?php

namespace Database\Factories;

use App\Enums\SessionFormat;
use App\Enums\SessionStatus;
use App\Models\Conference;
use App\Models\ConferenceSession;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConferenceSession>
 */
class ConferenceSessionFactory extends Factory
{
    protected $model = ConferenceSession::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'conference_id' => Conference::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'format' => SessionFormat::Panel,
            'organizers' => [fake()->company()],
            'co_hosts' => [fake()->company()],
            'target_audience' => 'Health leaders and policy makers',
            'expected_participants' => 80,
            'is_open' => true,
            'special_requirements' => [
                'av_equipment' => true,
                'translation' => false,
                'seating_type' => 'theater',
                'catering' => false,
            ],
            'session_lead_id' => null,
            'communications_lead_id' => null,
            'status' => SessionStatus::Draft,
            'submitted_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => SessionStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
