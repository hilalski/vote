<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vote;
use App\Models\VotingRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => VotingRound::factory(),
            'voter_id' => User::factory(),
            'candidate_id' => User::factory(),
        ];
    }
}
