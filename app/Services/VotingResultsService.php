<?php

namespace App\Services;

use App\Models\User;
use App\Models\VotingRound;
use Illuminate\Support\Collection;

class VotingResultsService
{
    /**
     * @return Collection<int, User>
     */
    public function rankings(VotingRound $round): Collection
    {
        return User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->join('round_candidates', 'round_candidates.user_id', '=', 'users.id')
            ->leftJoin('votes', function ($join) use ($round): void {
                $join->on('votes.candidate_id', '=', 'users.id')
                    ->where('votes.round_id', '=', $round->id);
            })
            ->where('round_candidates.round_id', $round->id)
            ->selectRaw('COUNT(votes.id) as total_votes')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_votes')
            ->orderBy('users.id')
            ->get()
            ->each(function (User $candidate): void {
                $candidate->setAttribute('total_votes', (int) $candidate->getAttribute('total_votes'));
            });
    }

    /**
     * @return array{rankings: Collection<int, User>, tied_for_first: Collection<int, User>, winner_id: int|null}
     */
    public function roundTwoOutcome(VotingRound $round): array
    {
        $rankings = $this->rankings($round);

        if ($rankings->isEmpty()) {
            return ['rankings' => $rankings, 'tied_for_first' => collect(), 'winner_id' => null];
        }

        /** @var User $leader */
        $leader = $rankings->first();
        $leaderVotes = (int) $leader->getAttribute('total_votes');
        $tiedForFirst = $rankings->filter(
            fn (User $candidate): bool => (int) $candidate->getAttribute('total_votes') === $leaderVotes,
        )->values();

        return [
            'rankings' => $rankings,
            'tied_for_first' => $tiedForFirst,
            'winner_id' => $tiedForFirst->count() === 1 ? (int) $leader->id : null,
        ];
    }

    /**
     * @return array{eligible: int, voted: int, percentage: float}
     */
    public function turnout(VotingRound $round): array
    {
        $eligible = $round->eligibleVoters()->count();
        $voted = $round->votes()->count();

        return [
            'eligible' => $eligible,
            'voted' => $voted,
            'percentage' => $eligible === 0 ? 0.0 : round(($voted / $eligible) * 100, 1),
        ];
    }
}
