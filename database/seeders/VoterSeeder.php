<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class VoterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $voters = $this->votersFromSpreadsheet();
        $timestamp = now('UTC');
        $password = Hash::make(Str::random(64));

        foreach (array_chunk($voters, 500) as $voterChunk) {
            User::query()->upsert(
                array_map(fn (array $voter): array => [
                    'name' => $voter['name'],
                    'email' => $voter['email'],
                    'password' => $password,
                    'is_active' => true,
                    'is_admin' => false,
                    'cant_be_voted' => $voter['cant_be_voted'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $voterChunk),
                ['email'],
                ['name', 'is_active', 'cant_be_voted', 'updated_at'],
            );
        }
    }

    /**
     * @return array<int, array{name: string, email: string, cant_be_voted: bool}>
     */
    private function votersFromSpreadsheet(): array
    {
        $source = $this->spreadsheetSource();

        try {
            $rows = $this->readRows($source);
        } catch (\Throwable $exception) {
            throw new \RuntimeException("File seed pemilih tidak dapat dibaca: {$source}", previous: $exception);
        }

        $header = $rows[0] ?? [];
        $nameColumn = $this->findColumn($header, ['nama', 'name']);
        $emailColumn = $this->findColumn($header, ['email', 'e-mail']);
        $cantBeVotedColumn = $this->findColumn($header, ['cant_be_voted', 'cant be voted']);

        if ($nameColumn === null || $emailColumn === null) {
            throw new \RuntimeException('File seed pemilih wajib memiliki header nama dan email.');
        }

        $voters = [];
        $emails = [];
        foreach (array_slice($rows, 1) as $rowOffset => $row) {
            $rowNumber = $rowOffset + 2;
            $name = $this->cleanValue($row[$nameColumn] ?? null);
            $email = $this->cleanEmail($row[$emailColumn] ?? null);
            $cantBeVoted = $cantBeVotedColumn === null
                ? false
                : $this->booleanValue($row[$cantBeVotedColumn] ?? null, $rowNumber);

            if ($name === '' && $email === '') {
                continue;
            }

            if ($name === '' || mb_strlen($name) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
                throw new \RuntimeException("Data seed pemilih pada baris {$rowNumber} tidak valid.");
            }

            if (isset($emails[$email])) {
                throw new \RuntimeException("Email {$email} muncul lebih dari sekali di file seed pemilih.");
            }

            $emails[$email] = true;
            $voters[] = [
                'name' => $name,
                'email' => $email,
                'cant_be_voted' => $cantBeVoted,
            ];
        }

        if ($voters === []) {
            throw new \RuntimeException('File seed pemilih tidak berisi data yang dapat diimpor.');
        }

        return $voters;
    }

    protected function spreadsheetSource(): string
    {
        $baseSource = database_path('seeders/voter-haisstis-1500');
        $sources = [
            $baseSource,
            "{$baseSource}.xlsx",
            "{$baseSource}.xls",
            "{$baseSource}.csv",
        ];

        foreach ($sources as $source) {
            if (is_file($source)) {
                return $source;
            }
        }

        throw new \RuntimeException('File seed pemilih tidak ditemukan. Simpan voter-haisstis-1500 di database/seeders, dengan atau tanpa ekstensi .xlsx, .xls, atau .csv.');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $source): array
    {
        foreach ([new Xlsx, new Xls, new Csv] as $reader) {
            if (! $reader->canRead($source)) {
                continue;
            }

            $reader->setReadDataOnly(true);

            return $reader->load($source)->getActiveSheet()->toArray(null, true, true, false);
        }

        throw new \RuntimeException("Format file seed pemilih tidak didukung: {$source}");
    }

    /**
     * @param  array<int, mixed>  $header
     * @param  array<int, string>  $acceptedHeaders
     */
    private function findColumn(array $header, array $acceptedHeaders): ?int
    {
        foreach ($header as $index => $value) {
            if (in_array(mb_strtolower($this->cleanValue($value)), $acceptedHeaders, true)) {
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
            default => throw new \RuntimeException("Nilai cant_be_voted pada baris {$rowNumber} harus bernilai 0 atau 1."),
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
