<?php

namespace App\Services;

use App\Exceptions\FinalizationBlockedException;
use App\Exceptions\VotingUnavailableException;
use App\Models\User;
use App\Models\VotingAuditLog;
use App\Models\VotingRound;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class VotingRoundService
{
    public function __construct(private VotingResultsService $results) {}

    public function schedule(VotingRound $round, CarbonInterface $startsAt, CarbonInterface $endsAt, User $actor): VotingRound
    {
        if (! $startsAt->lessThan($endsAt)) {
            throw new VotingUnavailableException('Waktu berakhir harus setelah waktu mulai.');
        }

        return DB::transaction(function () use ($round, $startsAt, $endsAt, $actor): VotingRound {
            $lockedRound = $this->lockedRound($round);

            if ($lockedRound->status !== VotingRound::STATUS_DRAFT) {
                throw new VotingUnavailableException('Jadwal hanya dapat diubah saat putaran masih draf.');
            }

            $lockedRound->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
            $this->audit($actor, $lockedRound, 'round_scheduled', [
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ]);

            return $lockedRound->fresh();
        });
    }

    public function open(VotingRound $round, User $actor): VotingRound
    {
        return DB::transaction(function () use ($round, $actor): VotingRound {
            $lockedRound = $this->lockedRound($round);

            if ($lockedRound->status !== VotingRound::STATUS_DRAFT) {
                throw new VotingUnavailableException('Putaran hanya dapat dibuka dari status draf.');
            }

            if (! $lockedRound->starts_at->lessThan($lockedRound->ends_at)) {
                throw new VotingUnavailableException('Jadwal putaran tidak valid.');
            }

            if (VotingRound::query()
                ->where('status', VotingRound::STATUS_OPEN)
                ->whereKeyNot($lockedRound->id)
                ->lockForUpdate()
                ->exists()) {
                throw new VotingUnavailableException('Masih ada putaran lain yang terbuka.');
            }

            if ($lockedRound->round_number === 1) {
                $this->snapshotRoundOneElectorateAndCandidates($lockedRound);
            } else {
                $this->prepareRoundTwo($lockedRound);
            }

            $lockedRound->update(['status' => VotingRound::STATUS_OPEN]);
            $this->audit($actor, $lockedRound, 'round_opened');

            return $lockedRound->fresh();
        });
    }

    public function close(VotingRound $round, User $actor): VotingRound
    {
        return DB::transaction(function () use ($round, $actor): VotingRound {
            $lockedRound = $this->lockedRound($round);

            if ($lockedRound->status !== VotingRound::STATUS_OPEN) {
                throw new VotingUnavailableException('Hanya putaran terbuka yang dapat ditutup.');
            }

            $lockedRound->update(['status' => VotingRound::STATUS_CLOSED]);
            $this->audit($actor, $lockedRound, 'round_closed');

            return $lockedRound->fresh();
        });
    }

    /**
     * @param  array<int, int>  $finalistIds
     */
    public function finalizeRoundOne(VotingRound $round, User $actor, array $finalistIds = [], ?string $explanation = null): VotingRound
    {
        return DB::transaction(function () use ($round, $actor, $finalistIds, $explanation): VotingRound {
            $lockedRound = $this->lockedRound($round);

            if ($lockedRound->round_number !== 1 || $lockedRound->status !== VotingRound::STATUS_CLOSED) {
                throw new FinalizationBlockedException('Putaran 1 harus berstatus ditutup sebelum finalisasi.');
            }

            $finalistIds = array_values(array_unique(array_map('intval', $finalistIds)));
            sort($finalistIds);

            $selectedCandidateIds = $lockedRound->candidates()
                ->whereIn('users.id', $finalistIds)
                ->pluck('users.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if (count($finalistIds) !== 3 || $selectedCandidateIds !== $finalistIds) {
                throw new FinalizationBlockedException('Administrator wajib memilih tepat tiga kandidat yang tercatat pada Putaran 1.');
            }

            $roundTwo = VotingRound::query()
                ->where('round_number', 2)
                ->lockForUpdate()
                ->firstOrFail();

            if ($roundTwo->status !== VotingRound::STATUS_DRAFT || $roundTwo->candidates()->exists()) {
                throw new FinalizationBlockedException('Finalis Putaran 2 sudah terkunci.');
            }

            $timestamp = now('UTC');
            DB::table('round_candidates')->insert(array_map(
                fn (int $userId): array => ['round_id' => $roundTwo->id, 'user_id' => $userId, 'created_at' => $timestamp],
                $finalistIds,
            ));

            $eligibleRows = DB::table('round_eligible_voters')
                ->where('round_id', $lockedRound->id)
                ->pluck('user_id')
                ->map(fn (mixed $userId): array => ['round_id' => $roundTwo->id, 'user_id' => $userId, 'created_at' => $timestamp])
                ->all();

            if ($eligibleRows !== []) {
                DB::table('round_eligible_voters')->insert($eligibleRows);
            }

            $lockedRound->update([
                'status' => VotingRound::STATUS_FINALIZED,
                'finalized_at' => $timestamp,
                'finalized_by' => $actor->id,
            ]);
            $this->audit($actor, $lockedRound, 'round_one_finalized_admin_selection', [
                'finalist_ids' => $finalistIds,
                'explanation' => trim((string) $explanation) ?: null,
            ]);

            return $lockedRound->fresh();
        });
    }

    public function finalizeRoundTwo(VotingRound $round, User $actor): VotingRound
    {
        return DB::transaction(function () use ($round, $actor): VotingRound {
            $lockedRound = $this->lockedRound($round);

            if ($lockedRound->round_number !== 2 || $lockedRound->status !== VotingRound::STATUS_CLOSED) {
                throw new FinalizationBlockedException('Putaran 2 harus berstatus ditutup sebelum finalisasi.');
            }

            $outcome = $this->results->roundTwoOutcome($lockedRound);
            $timestamp = now('UTC');
            $lockedRound->update([
                'status' => VotingRound::STATUS_FINALIZED,
                'finalized_at' => $timestamp,
                'finalized_by' => $actor->id,
                'winner_id' => $outcome['winner_id'],
                'winner_resolved_at' => $outcome['winner_id'] === null ? null : $timestamp,
                'winner_resolved_by' => $outcome['winner_id'] === null ? null : $actor->id,
                'winner_resolution' => $outcome['winner_id'] === null ? null : 'Pemenang tunggal berdasarkan suara Putaran 2.',
            ]);
            $this->audit($actor, $lockedRound, $outcome['winner_id'] === null ? 'round_two_finalized_tie' : 'round_two_finalized', [
                'winner_id' => $outcome['winner_id'],
                'tied_for_first_ids' => $outcome['tied_for_first']->pluck('id')->all(),
            ]);

            return $lockedRound->fresh();
        });
    }

    public function resolveRoundTwoTie(VotingRound $round, User $actor, int $winnerId, string $explanation): VotingRound
    {
        return DB::transaction(function () use ($round, $actor, $winnerId, $explanation): VotingRound {
            $lockedRound = $this->lockedRound($round);

            if ($lockedRound->round_number !== 2 || $lockedRound->status !== VotingRound::STATUS_FINALIZED || $lockedRound->winner_id !== null) {
                throw new FinalizationBlockedException('Tidak ada seri Putaran 2 yang dapat diselesaikan.');
            }

            $outcome = $this->results->roundTwoOutcome($lockedRound);
            $tiedIds = $outcome['tied_for_first']->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            if (count($tiedIds) < 2 || ! in_array($winnerId, $tiedIds, true) || trim($explanation) === '') {
                throw new FinalizationBlockedException('Pemenang harus salah satu kandidat seri dan alasan keputusan wajib diisi.');
            }

            $lockedRound->update([
                'winner_id' => $winnerId,
                'winner_resolved_at' => now('UTC'),
                'winner_resolved_by' => $actor->id,
                'winner_resolution' => trim($explanation),
            ]);
            $this->audit($actor, $lockedRound, 'round_two_tie_resolved', [
                'winner_id' => $winnerId,
                'tied_for_first_ids' => $tiedIds,
                'explanation' => trim($explanation),
            ]);

            return $lockedRound->fresh();
        });
    }

    private function lockedRound(VotingRound $round): VotingRound
    {
        return VotingRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();
    }

    private function snapshotRoundOneElectorateAndCandidates(VotingRound $round): void
    {
        if ($round->eligibleVoters()->exists() || $round->candidates()->exists()) {
            throw new VotingUnavailableException('Snapshot Putaran 1 sudah ada dan tidak dapat diubah.');
        }

        $timestamp = now('UTC');
        $userIds = User::query()
            ->where('is_active', true)
            ->where('is_admin', false)
            ->orderBy('id')
            ->pluck('id');
        $eligibleRows = $userIds->map(fn (mixed $userId): array => [
            'round_id' => $round->id,
            'user_id' => $userId,
            'created_at' => $timestamp,
        ])->all();

        if ($eligibleRows === []) {
            throw new VotingUnavailableException('Tidak ada pemilih aktif untuk snapshot Putaran 1.');
        }

        DB::table('round_eligible_voters')->insert($eligibleRows);
        DB::table('round_candidates')->insert($eligibleRows);
    }

    private function prepareRoundTwo(VotingRound $round): void
    {
        $roundOne = VotingRound::query()
            ->where('round_number', 1)
            ->lockForUpdate()
            ->firstOrFail();

        if ($roundOne->status !== VotingRound::STATUS_FINALIZED) {
            throw new VotingUnavailableException('Putaran 1 harus difinalisasi sebelum Putaran 2 dibuka.');
        }

        if ($round->candidates()->count() !== 3) {
            throw new VotingUnavailableException('Putaran 2 memerlukan tepat tiga finalis yang sudah terkunci.');
        }

        if (! $round->eligibleVoters()->exists()) {
            throw new VotingUnavailableException('Elektorat Putaran 2 belum disalin dari snapshot Putaran 1.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(User $actor, VotingRound $round, string $eventType, array $metadata = []): void
    {
        VotingAuditLog::create([
            'actor_id' => $actor->id,
            'round_id' => $round->id,
            'event_type' => $eventType,
            'metadata' => $metadata,
        ]);
    }
}
