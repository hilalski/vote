<?php

namespace Database\Seeders;

use App\Models\VotingRound;
use Illuminate\Database\Seeder;

class VotingRoundSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $firstStart = now('UTC')->addDay()->startOfHour();

        VotingRound::query()->firstOrCreate(
            ['round_number' => 1],
            [
                'name' => 'Penjaringan',
                'status' => VotingRound::STATUS_DRAFT,
                'starts_at' => $firstStart,
                'ends_at' => $firstStart->copy()->addDays(3),
            ],
        );

        VotingRound::query()->firstOrCreate(
            ['round_number' => 2],
            [
                'name' => 'Final',
                'status' => VotingRound::STATUS_DRAFT,
                'starts_at' => $firstStart->copy()->addDays(7),
                'ends_at' => $firstStart->copy()->addDays(10),
            ],
        );
    }
}
