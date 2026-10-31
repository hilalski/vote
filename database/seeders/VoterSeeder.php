<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VoterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $voters = [
            ['email' => 'oemar.wibisono@bps.go.id', 'name' => 'Oemar Syarief Wibisono, S.ST., M.Kom.'],
            ['email' => 'fadhlika.putro@bps.go.id', 'name' => 'Fadhlika Anggit Santoso Putro, S.Tr.Stat.'],
            ['email' => 'sandy.pradana@bps.go.id', 'name' => 'Sandy Pradana, S.ST.'],
            ['email' => 'yanuarnhilal@bps.go.id', 'name' => 'Yanuar Nurul Hilal, S.Tr.Stat.'],
        ];

        foreach ($voters as $voter) {
            $user = User::query()->firstOrCreate(
                ['email' => $voter['email']],
                [
                    'name' => $voter['name'],
                    'is_active' => true,
                    'password' => Hash::make(Str::random(64)),
                ],
            );

            $user->forceFill([
                'name' => $voter['name'],
                'is_active' => true,
            ])->save();
        }
    }
}
