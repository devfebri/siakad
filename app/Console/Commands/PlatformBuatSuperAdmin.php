<?php

namespace App\Console\Commands;

use App\Peran;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Membuat akun Super Admin pertama (tanpa sekolah) untuk masuk ke panel /platform. */
class PlatformBuatSuperAdmin extends Command
{
    protected $signature = 'platform:super-admin {username : Username untuk login di /platform/login}
                            {--name= : Nama lengkap}
                            {--email= : Email}
                            {--password= : Password (kosong = ditanyakan tanpa tampil di layar)}';
    protected $description = 'Buat akun Super Admin (tanpa sekolah) untuk panel platform';

    public function handle(): int
    {
        $username = mb_strtolower(trim((string) $this->argument('username')));
        $name = $this->option('name') ?: $this->ask('Nama lengkap', 'Super Admin');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (minimal 8 karakter)');

        $v = Validator::make(compact('username', 'name', 'email', 'password'), [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        // Akun tanpa sekolah: dibuat di luar konteks sekolah; username unik di antara akun super admin.
        $sudahAda = SekolahAktif::jalankan(null, fn () => User::whereNull('sekolah_id')->where('username', $username)->exists());
        if ($sudahAda) {
            $this->error("Username {$username} sudah dipakai super admin lain.");

            return self::FAILURE;
        }

        SekolahAktif::jalankan(null, function () use ($username, $name, $email, $password) {
            $user = User::create([
                'name' => $name, 'username' => $username, 'email' => $email, 'sekolah_id' => null,
                'password' => $password, 'remember_token' => Str::random(60),
            ]);
            $user->setPeran([Peran::SUPER_ADMIN]);
        });

        $this->info("Super Admin {$username} dibuat. Masuk di: ".url('/platform/login'));

        return self::SUCCESS;
    }
}
