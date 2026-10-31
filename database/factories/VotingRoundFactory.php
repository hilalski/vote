<?php

namespace Database\Factories;

use App\Models\VotingRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VotingRound>
 */
class VotingRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_number' => fake()->unique()->numberBetween(1, 200),
            'name' => fake()->words(2, true),
            'status' => VotingRound::STATUS_DRAFT,
            'starts_at' => now('UTC')->subHour(),
            'ends_at' => now('UTC')->addDay(),
        ];
    }
}
