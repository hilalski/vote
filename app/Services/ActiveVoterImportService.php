<?php

namespace App\Services;

use App\Models\User;
use App\Models\VotingRound;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ActiveVoterImportService
{
    public function __construct(private VotingTokenService $tokens) {}

    /**
     * @return array{created: int, updated: int, tokens_issued: int, added_to_current_round: int}
     */
    public function import(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $header = $rows[0] ?? [];
        $nameColumn = $this->findColumn($header, ['nama', 'name']);
        $emailColumn = $this->findColumn($header, ['email', 'e-mail']);
        $cantBeVotedColumn = $this->findColumn($header, ['cant_be_voted', 'cant be voted']);

        if ($nameColumn === null || $emailColumn === null) {
            throw ValidationException::withMessages([
                'voters_file' => 'Kolom header wajib memuat "nama" dan "email".',
            ]);
        }

        $records = [];
        $emails = [];
        foreach (array_slice($rows, 1) as $rowOffset => $row) {
            $rowNumber = $rowOffset + 2;
            $name = $this->cleanValue($row[$nameColumn] ?? null);
            $email = $this->cleanEmail($row[$emailColumn] ?? null);
            $cantBeVoted = $cantBeVotedColumn === null
                ? null
                : $this->booleanValue($row[$cantBeVotedColumn] ?? null, $rowNumber);

            if ($name === '' && $email === '') {
                continue;
            }

            if ($name === '' || mb_strlen($name) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
                throw ValidationException::withMessages([
                    'voters_file' => "Data pada baris {$rowNumber} harus memiliki nama dan email yang valid.",
                ]);
            }

            if (isset($emails[$email])) {
                throw ValidationException::withMessages([
                    'voters_file' => "Email {$email} muncul lebih dari sekali di file.",
                ]);
            }

            $emails[$email] = true;
            $records[] = compact('name', 'email', 'cantBeVoted');
        }

        if ($records === []) {
            throw ValidationException::withMessages([
                'voters_file' => 'File tidak berisi data pemilih yang dapat diimpor.',
            ]);
        }

        return DB::transaction(function () use ($records, $actor): array {
            $created = 0;
            $updated = 0;
            $newVoters = [];
            $voterIds = [];

            foreach ($records as $record) {
                $user = User::query()->where('email', $record['email'])->lockForUpdate()->first();

                if ($user === null) {
                    $user = User::create([
                        'name' => $record['name'],
                        'email' => $record['email'],
                        'password' => Hash::make(Str::random(64)),
                        'is_active' => true,
                        'is_admin' => false,
                        'cant_be_voted' => $record['cantBeVoted'] ?? false,
                    ]);
                    $created++;
                    $newVoters[] = $user;

                } else {
                    if ($user->is_admin) {
                        throw ValidationException::withMessages([
                            'voters_file' => "Email {$record['email']} terdaftar sebagai administrator dan tidak dapat diimpor sebagai pemilih.",
                        ]);
                    }

                    $attributes = [
                        'name' => $record['name'],
                        'is_active' => true,
                    ];

                    if ($record['cantBeVoted'] !== null) {
                        $attributes['cant_be_voted'] = $record['cantBeVoted'];
                    }

                    $user->update($attributes);
                    $updated++;
                }

                $voterIds[] = $user->id;
            }

            $tokensIssued = 0;
            foreach ($newVoters as $voter) {
                $this->tokens->issue(
                    $voter,
                    now('UTC')->addDays(config('voting.token_lifetime_days')),
                    $actor,
                );
                $tokensIssued++;
            }

            $currentRounds = VotingRound::query()
                ->where('status', VotingRound::STATUS_OPEN)
                ->where('starts_at', '<=', now('UTC'))
                ->where('ends_at', '>', now('UTC'))
                ->lockForUpdate()
                ->get(['id']);

            $eligibleRows = [];
            $timestamp = now('UTC');
            foreach ($currentRounds as $round) {
                foreach ($voterIds as $voterId) {
                    $eligibleRows[] = [
                        'round_id' => $round->id,
                        'user_id' => $voterId,
                        'created_at' => $timestamp,
                    ];
                }
            }

            $addedToCurrentRound = $eligibleRows === []
                ? 0
                : DB::table('round_eligible_voters')->insertOrIgnore($eligibleRows);

            return [
                'created' => $created,
                'updated' => $updated,
                'tokens_issued' => $tokensIssued,
                'added_to_current_round' => $addedToCurrentRound,
            ];
        });
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());

            return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'voters_file' => 'File Excel tidak dapat dibaca. Gunakan file dengan kolom nama dan email.',
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $header
     * @param  array<int, string>  $acceptedHeaders
     */
    private function findColumn(array $header, array $acceptedHeaders): ?int
    {
        foreach ($header as $index => $value) {
            $normalizedHeader = mb_strtolower($this->cleanValue($value));

            if (in_array($normalizedHeader, $acceptedHeaders, true)) {
                return $index;
            }
        }

        return null;
    }

    private function booleanValue(mixed $value, int $rowNumber): bool
    {
        return match (mb_strtolower($this->cleanValue($value))) {
            '', '0', 'false', 'no', 'tidak' => false,
            '1', 'true', 'yes', 'ya' => true,
            default => throw ValidationException::withMessages([
                'voters_file' => "Nilai cant_be_voted pada baris {$rowNumber} harus bernilai 0 atau 1.",
            ]),
        };
    }

    private function cleanValue(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function cleanEmail(mixed $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', (string) $value));
    }
}
