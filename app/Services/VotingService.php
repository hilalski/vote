<?php

namespace App\Services;

use App\Exceptions\AlreadyVotedException;
use App\Exceptions\VotingUnavailableException;
use App\Models\User;
use App\Models\Vote;
use App\Models\VotingRound;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VotingService
{
    public function cast(User $voter, int $candidateId): Vote
    {
        try {
            return DB::transaction(function () use ($voter, $candidateId): Vote {
                $now = now('UTC');
                $round = VotingRound::query()
                    ->where('status', VotingRound::STATUS_OPEN)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>', $now)
                    ->lockForUpdate()
                    ->first();

                if ($round === null) {
                    throw new VotingUnavailableException('Tidak ada putaran voting yang sedang dibuka.');
                }

                if (! User::query()->whereKey($voter->id)->where('is_active', true)->exists()) {
                    throw new VotingUnavailableException('Akun Anda tidak aktif untuk voting saat ini.');
                }

                $isEligible = DB::table('round_eligible_voters')
                    ->where('round_id', $round->id)
                    ->where('user_id', $voter->id)
                    ->exists();
                $isCandidate = DB::table('round_candidates')
                    ->where('round_id', $round->id)
                    ->where('user_id', $candidateId)
                    ->exists();

                if (! $isEligible || ! $isCandidate) {
                    throw new VotingUnavailableException('Pilihan tidak tersedia untuk putaran voting ini.');
                }

                if (Vote::query()->where('round_id', $round->id)->where('voter_id', $voter->id)->exists()) {
                    throw new AlreadyVotedException('Anda sudah memberikan suara pada putaran ini.');
                }

                return Vote::create([
                    'round_id' => $round->id,
                    'voter_id' => $voter->id,
                    'candidate_id' => $candidateId,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $activeRound = VotingRound::query()->where('status', VotingRound::STATUS_OPEN)->first();

            if ($activeRound !== null && Vote::query()->where('round_id', $activeRound->id)->where('voter_id', $voter->id)->exists()) {
                throw new AlreadyVotedException('Anda sudah memberikan suara pada putaran ini.');
            }

            throw $exception;
        }
    }
}
