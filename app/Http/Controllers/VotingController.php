<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyVotedException;
use App\Exceptions\VotingUnavailableException;
use App\Http\Requests\AuthenticateVotingTokenRequest;
use App\Http\Requests\SubmitVoteRequest;
use App\Models\User;
use App\Models\Vote;
use App\Models\VotingRound;
use App\Services\VotingService;
use App\Services\VotingTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VotingController extends Controller
{
    public function index(Request $request): View
    {
        $voter = $this->voterFromSession($request);
        $round = VotingRound::query()
            ->where('status', VotingRound::STATUS_OPEN)
            ->where('starts_at', '<=', now('UTC'))
            ->where('ends_at', '>', now('UTC'))
            ->orderBy('round_number')
            ->first();
        $vote = $voter === null || $round === null
            ? null
            : Vote::query()->with('candidate:id,name')->where('round_id', $round->id)->where('voter_id', $voter->id)->first();
        $isEligible = $voter !== null && $round !== null && $round->eligibleVoters()->whereKey($voter->id)->exists();
        $candidates = $round !== null && $isEligible && $vote === null
            ? $round->candidates()->select('users.id', 'users.name')->orderBy('users.name')->get()
            : collect();

        return view('voting.index', [
            'voter' => $voter,
            'round' => $round,
            'vote' => $vote,
            'isEligible' => $isEligible,
            'candidates' => $candidates,
            'statusMessage' => $this->statusMessage($voter, $round, $vote, $isEligible),
        ]);
    }

    public function authenticate(AuthenticateVotingTokenRequest $request, VotingTokenService $tokens): RedirectResponse
    {
        $voter = $tokens->authenticate($request->string('token')->toString());

        if ($voter === null) {
            return redirect()->route('voting.index')
                ->with('error', 'Token tidak valid, kedaluwarsa, atau sudah dicabut.');
        }

        $request->session()->regenerate();
        $request->session()->put('voting_voter_id', $voter->id);
        $request->session()->put('voting_authenticated', true);

        return redirect()->route('voting.index');
    }

    public function submit(SubmitVoteRequest $request, VotingService $voting): RedirectResponse
    {
        /** @var User $voter */
        $voter = $request->attributes->get('voting_voter');

        try {
            $voting->cast($voter, $request->integer('candidate_id'));
        } catch (AlreadyVotedException $exception) {
            return redirect()->route('voting.index')->with('error', $exception->getMessage());
        } catch (VotingUnavailableException $exception) {
            return redirect()->route('voting.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('voting.index')
            ->with('success', 'Suara Anda berhasil dicatat');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['voting_voter_id', 'voting_authenticated']);
        $request->session()->regenerateToken();

        return redirect()->route('voting.index')->with('success', 'Anda telah keluar dari sesi voting.');
    }

    private function voterFromSession(Request $request): ?User
    {
        $voterId = $request->session()->get('voting_voter_id');

        return $voterId === null
            ? null
            : User::query()->whereKey($voterId)->where('is_active', true)->first();
    }

    private function statusMessage(?User $voter, ?VotingRound $round, ?Vote $vote, bool $isEligible): string
    {
        if ($voter === null) {
            return 'Masukkan token pribadi Anda untuk melihat status voting.';
        }

        if ($round === null) {
            $roundOne = VotingRound::query()->where('round_number', 1)->first();

            return $roundOne?->status === VotingRound::STATUS_FINALIZED
                ? 'Putaran 1 telah selesai. Putaran 2 belum dibuka.'
                : 'Belum ada putaran voting yang dibuka saat ini.';
        }

        if (! $isEligible) {
            return 'Anda tidak tercatat dalam snapshot pemilih untuk putaran ini.';
        }

        if ($vote !== null) {
            return 'Anda sudah memberikan suara pada putaran ini.';
        }

        return 'Pilih satu kandidat lalu konfirmasi pilihan Anda.';
    }
}
