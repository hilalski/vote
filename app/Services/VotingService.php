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
            $now = now('UTC');
            $round = VotingRound::query()
                ->where('status', VotingRound::STATUS_OPEN)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>', $now)
                ->orderBy('round_number')
                ->first();

            if ($round === null) {
                throw new VotingUnavailableException('Tidak ada putaran voting yang sedang dibuka.');
            }

            if (! User::query()->whereKey($voter->id)->where('is_active', true)->exists()) {
                throw new VotingUnavailableException('Akun Anda tidak aktif untuk voting saat ini.');
            }

            $voteSelection = DB::table('round_eligible_voters as eligible_voters')
                ->join('voting_rounds as rounds', 'rounds.id', '=', 'eligible_voters.round_id')
                ->join('round_candidates as candidates', function ($join) use ($candidateId): void {
                    $join->on('candidates.round_id', '=', 'eligible_voters.round_id')
                        ->where('candidates.user_id', '=', $candidateId);
                })
                ->join('users as voters', 'voters.id', '=', 'eligible_voters.user_id')
                ->join('users as candidate_users', 'candidate_users.id', '=', 'candidates.user_id')
                ->where('eligible_voters.round_id', $round->id)
                ->where('eligible_voters.user_id', $voter->id)
                ->where('voters.is_active', true)
                ->where('candidate_users.cant_be_voted', false)
                ->where('rounds.status', VotingRound::STATUS_OPEN)
                ->where('rounds.starts_at', '<=', $now)
                ->where('rounds.ends_at', '>', $now)
                ->selectRaw('eligible_voters.round_id, ?, ?, ?', [$voter->id, $candidateId, $now]);

            $inserted = DB::table('votes')->insertUsing(
                ['round_id', 'voter_id', 'candidate_id', 'created_at'],
                $voteSelection,
            );

            if (! $inserted) {
                throw new VotingUnavailableException('Pilihan tidak tersedia untuk putaran voting ini.');
            }

            return Vote::query()
                ->where('round_id', $round->id)
                ->where('voter_id', $voter->id)
                ->firstOrFail();
        } catch (QueryException $exception) {
            if (isset($round) && Vote::query()->where('round_id', $round->id)->where('voter_id', $voter->id)->exists()) {
                throw new AlreadyVotedException('Anda sudah memberikan suara pada putaran ini.');
            }

            throw $exception;
        }
    }
}
