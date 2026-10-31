<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateVotingAdmin extends Command
{
    protected $signature = 'voting:admin {email : Email admin} {--name= : Nama untuk pengguna baru}';

    protected $description = 'Promosikan atau buat admin voting dengan kata sandi yang Anda pilih';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $password = $this->secret('Kata sandi admin baru');

        if ($password === false || mb_strlen($password) < 6) {
            $this->error('Kata sandi harus terdiri dari minimal 6 karakter.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $user->exists ? $user->name : ($this->option('name') ?: $email),
            'is_active' => true,
            'is_admin' => true,
        ]);
        $user->password = Hash::make($password);
        $user->save();

        $this->info("Admin voting aktif untuk {$user->email}.");

        return self::SUCCESS;
    }
}
