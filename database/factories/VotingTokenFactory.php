<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VotingToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VotingToken>
 */
class VotingTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', fake()->uuid()),
            'expires_at' => now('UTC')->addDays(30),
            'revoked_at' => null,
            'last_used_at' => null,
        ];
    }
}
