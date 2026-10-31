<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ActiveVoterImportService
{
    /**
     * @return array{created: int, updated: int}
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $header = $rows[0] ?? [];
        $nameColumn = $this->findColumn($header, ['nama', 'name']);
        $emailColumn = $this->findColumn($header, ['email', 'e-mail']);

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
            $email = mb_strtolower($this->cleanValue($row[$emailColumn] ?? null));

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
            $records[] = compact('name', 'email');
        }

        if ($records === []) {
            throw ValidationException::withMessages([
                'voters_file' => 'File tidak berisi data pemilih yang dapat diimpor.',
            ]);
        }

        return DB::transaction(function () use ($records): array {
            $created = 0;
            $updated = 0;

            foreach ($records as $record) {
                $user = User::query()->where('email', $record['email'])->lockForUpdate()->first();

                if ($user === null) {
                    User::create([
                        'name' => $record['name'],
                        'email' => $record['email'],
                        'password' => Hash::make(Str::random(64)),
                        'is_active' => true,
                        'is_admin' => false,
                    ]);
                    $created++;

                    continue;
                }

                $user->update([
                    'name' => $record['name'],
                    'is_active' => true,
                ]);
                $updated++;
            }

            return compact('created', 'updated');
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

    private function cleanValue(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
