<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\FinalizationBlockedException;
use App\Exceptions\VotingUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinalizeVotingRoundRequest;
use App\Http\Requests\GenerateAllVotingTokensRequest;
use App\Http\Requests\ImportActiveVotersRequest;
use App\Http\Requests\ResolveVotingTieRequest;
use App\Http\Requests\SendVotingInvitationsRequest;
use App\Http\Requests\UpdateVotingScheduleRequest;
use App\Models\User;
use App\Models\Vote;
use App\Models\VotingRound;
use App\Models\VotingToken;
use App\Services\ActiveVoterImportService;
use App\Services\VotingResultsService;
use App\Services\VotingRoundService;
use App\Services\VotingTokenService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VotingAdminController extends Controller
{
    public function index(VotingResultsService $results): View
    {
        $rounds = VotingRound::query()->orderBy('round_number')->get();
        $turnout = $rounds->mapWithKeys(
            fn (VotingRound $round): array => [$round->id => $results->turnout($round)],
        );
        $activeUsers = User::query()
            ->where('is_active', true)
            ->where('is_admin', false)
            ->with(['votingTokens' => fn ($query) => $query->latest('id')])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.voting.dashboard', compact('rounds', 'turnout', 'activeUsers'));
    }

    public function show(Request $request, VotingRound $round, VotingResultsService $results): View
    {
        $search = $request->string('search')->trim()->toString();
        $resultsVisible = in_array($round->status, [VotingRound::STATUS_CLOSED, VotingRound::STATUS_FINALIZED], true);
        $votesQuery = Vote::query()
            ->with('voter:id,name,email')
            ->where('round_id', $round->id)
            ->when($resultsVisible, fn ($query) => $query->with('candidate:id,name,email'))
            ->when($search !== '', function ($query) use ($search, $resultsVisible): void {
                $query->where(function ($query) use ($search, $resultsVisible): void {
                    $query->whereHas('voter', function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                    });

                    if ($resultsVisible) {
                        $query->orWhereHas('candidate', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                        });
                    }
                });
            });
        $votes = $votesQuery
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();
        $showClosedResults = $resultsVisible && $request->session()->pull('show_closed_results', false);

        return view('admin.voting.round', [
            'round' => $round,
            'turnout' => $results->turnout($round),
            'resultsVisible' => $resultsVisible,
            'rankings' => $resultsVisible ? $results->rankings($round) : collect(),
            'candidates' => $resultsVisible ? collect() : $round->candidates()->select('users.id', 'users.name', 'users.email')->orderBy('users.name')->get(),
            'outcome' => $resultsVisible && $round->round_number === 2 ? $results->roundTwoOutcome($round) : null,
            'votes' => $votes,
            'search' => $search,
            'showClosedResults' => $showClosedResults,
        ]);
    }

    public function exportRankings(VotingRound $round, VotingResultsService $results): StreamedResponse
    {
        $this->ensureResultsAreVisible($round);
        $rankings = $results->rankings($round);

        return response()->streamDownload(function () use ($rankings): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Peringkat', 'Kandidat', 'Jumlah Suara']);

            foreach ($rankings->values() as $index => $candidate) {
                fputcsv($output, [$index + 1, $candidate->name, $candidate->total_votes]);
            }

            fclose($output);
        }, "peringkat-putaran-{$round->round_number}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportAudit(VotingRound $round): StreamedResponse
    {
        $this->ensureResultsAreVisible($round);

        return response()->streamDownload(function () use ($round): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Waktu (WIB)', 'Pemilih', 'Kandidat Dipilih']);

            Vote::query()
                ->with(['voter:id,name', 'candidate:id,name'])
                ->where('round_id', $round->id)
                ->orderBy('id')
                ->chunkById(500, function ($votes) use ($output): void {
                    foreach ($votes as $vote) {
                        fputcsv($output, [
                            $vote->created_at->timezone(config('voting.timezone'))->format('d-m-Y H:i:s'),
                            $vote->voter->name,
                            $vote->candidate->name,
                        ]);
                    }
                });

            fclose($output);
        }, "audit-pilihan-putaran-{$round->round_number}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function schedule(UpdateVotingScheduleRequest $request, VotingRound $round, VotingRoundService $rounds): RedirectResponse
    {
        try {
            $startsAt = Carbon::createFromFormat('Y-m-d\\TH:i', $request->string('starts_at')->toString(), config('voting.timezone'))->utc();
            $endsAt = Carbon::createFromFormat('Y-m-d\\TH:i', $request->string('ends_at')->toString(), config('voting.timezone'))->utc();
            $rounds->schedule($round, $startsAt, $endsAt, $request->user());
        } catch (\Throwable $exception) {
            return back()->with('error', $this->messageFor($exception));
        }

        return back()->with('success', 'Jadwal putaran disimpan dalam UTC dan ditampilkan dalam waktu Jakarta.');
    }

    public function open(VotingRound $round, VotingRoundService $rounds, Request $request): RedirectResponse
    {
        try {
            $rounds->open($round, $request->user());
        } catch (\Throwable $exception) {
            return back()->with('error', $this->messageFor($exception));
        }

        return back()->with('success', 'Putaran voting telah dibuka dan snapshot terkait dikunci.');
    }

    public function close(VotingRound $round, VotingRoundService $rounds, Request $request): RedirectResponse
    {
        try {
            $closedRound = $rounds->close($round, $request->user());
        } catch (\Throwable $exception) {
            return back()->with('error', $this->messageFor($exception));
        }

        return redirect()
            ->route('admin.voting.rounds.show', $closedRound)
            ->with('success', 'Putaran voting telah ditutup.')
            ->with('show_closed_results', true);
    }

    public function finalize(FinalizeVotingRoundRequest $request, VotingRound $round, VotingRoundService $rounds): RedirectResponse
    {
        try {
            if ($round->round_number === 1) {
                $rounds->finalizeRoundOne(
                    $round,
                    $request->user(),
                    $request->collect('finalist_ids')->map(fn (mixed $id): int => (int) $id)->all(),
                    $request->input('explanation'),
                );
            } else {
                $rounds->finalizeRoundTwo($round, $request->user());
            }
        } catch (\Throwable $exception) {
            return back()->with('error', $this->messageFor($exception));
        }

        return back()->with('success', 'Finalisasi berhasil dicatat.');
    }

    public function resolveTie(ResolveVotingTieRequest $request, VotingRound $round, VotingRoundService $rounds): RedirectResponse
    {
        try {
            $rounds->resolveRoundTwoTie(
                $round,
                $request->user(),
                $request->integer('winner_id'),
                $request->string('explanation')->toString(),
            );
        } catch (\Throwable $exception) {
            return back()->with('error', $this->messageFor($exception));
        }

        return back()->with('success', 'Resolusi seri Putaran 2 telah direkam di audit log.');
    }

    public function reissueToken(User $user, VotingTokenService $tokens, Request $request): RedirectResponse
    {
        if (! $user->is_active) {
            return back()->with('error', 'Token tidak dapat diterbitkan untuk pengguna tidak aktif.');
        }

        $tokens->issue($user, now('UTC')->addDays(config('voting.token_lifetime_days')), $request->user());

        return back()->with('success', 'Token baru dibuat. Token lama telah dicabut; gunakan ekspor CSV atau salin token dari tabel.');
    }

    public function importActiveVoters(ImportActiveVotersRequest $request, ActiveVoterImportService $importer): RedirectResponse
    {
        $result = $importer->import($request->file('voters_file'));

        return back()->with(
            'success',
            "Import selesai: {$result['created']} pemilih baru dan {$result['updated']} pemilih diperbarui. Token yang sudah ada tidak diubah.",
        );
    }

    public function sendInvitations(SendVotingInvitationsRequest $request, VotingTokenService $tokens): RedirectResponse
    {
        $sendToAll = $request->boolean('send_all');
        $users = User::query()
            ->where('is_active', true)
            ->where('is_admin', false)
            ->when(! $sendToAll, fn ($query) => $query->whereIn('id', $request->collect('user_ids')->all()))
            ->get();

        if ($users->isEmpty()) {
            return back()->with('error', 'Tidak ada pemilih aktif yang dapat menerima undangan.');
        }

        $sent = 0;
        $notReady = 0;
        foreach ($users as $user) {
            if ($tokens->sendExistingToken($user, $request->user())) {
                $sent++;
            } else {
                $notReady++;
            }
        }

        $message = "Email token dikirim ke {$sent} dari {$users->count()} pemilih aktif.";
        if ($notReady > 0) {
            $message .= " {$notReady} pemilih tidak dikirimi karena belum memiliki token aktif.";
        }

        return back()->with('success', $message);
    }

    public function generateAllTokens(GenerateAllVotingTokensRequest $request, VotingTokenService $tokens): StreamedResponse
    {
        $users = User::query()->where('is_active', true)->where('is_admin', false)->orderBy('name')->get();
        $rows = [];

        foreach ($users as $user) {
            $issued = $tokens->issue($user, now('UTC')->addDays(config('voting.token_lifetime_days')), $request->user());
            $rows[] = [$user->name, $user->email, $issued['token']];
        }

        return $this->tokenCsvDownload($rows, 'token-baru-semua-pemilih.csv');
    }

    public function exportTokens(): StreamedResponse
    {
        $rows = VotingToken::query()
            ->with('user:id,name,email')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'))
            ->whereNotNull('token_ciphertext')
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('is_admin', false))
            ->orderBy('user_id')
            ->get()
            ->map(fn (VotingToken $token): array => [$token->user->name, $token->user->email, $token->token_ciphertext])
            ->all();

        return $this->tokenCsvDownload($rows, 'token-aktif-pemilih.csv');
    }

    private function messageFor(\Throwable $exception): string
    {
        if ($exception instanceof VotingUnavailableException || $exception instanceof FinalizationBlockedException) {
            return $exception->getMessage();
        }

        report($exception);

        return 'Operasi tidak dapat diselesaikan. Periksa kembali data dan coba lagi.';
    }

    private function ensureResultsAreVisible(VotingRound $round): void
    {
        abort_unless(
            in_array($round->status, [VotingRound::STATUS_CLOSED, VotingRound::STATUS_FINALIZED], true),
            403,
            'Hasil dan audit pilihan tersedia setelah putaran ditutup.',
        );
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $rows
     */
    private function tokenCsvDownload(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Nama', 'Email', 'Token']);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
