<?php

namespace Database\Seeders;

use App\Sekolah;
use App\Support\SekolahAktif;
use Illuminate\Database\Seeder;

/**
 * Data uji untuk pengetesan manual per role (admin, guru, siswa).
 *
 * Aman dijalankan berulang (updateOrCreate) dan tidak menghapus data.
 * Hanya untuk lokal/staging: ditolak di production kecuali memakai --force.
 *
 * Akun (semua password: "password"):
 *   username: admin, guru, guru2,
 *   siswa, siswa2, siswa3 (turunan dari email; login memakai username)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! $this->command?->option('force')) {
            $this->command?->error('Seeder data uji ditolak di production. Pakai --force bila memang disengaja.');

            return;
        }

        // Semua data contoh milik 1 sekolah: dipakai yang sudah ada, atau dibuat (subdomain SEKOLAH_DEFAULT / "default").
        $sekolah = Sekolah::firstOrCreate(
            ['subdomain' => config('sekolah.default_subdomain') ?: 'default'],
            ['nama' => config('school.name') ?: 'Sekolah Contoh', 'jenjang' => 'SMA', 'status' => 'aktif'],
        );
        $sebelumnya = SekolahAktif::instance()->get();
        SekolahAktif::instance()->set($sekolah);
        \App\Support\ChatDefault::pasang($sekolah);

        $this->call([
            MasterSeeder::class,
            UserSeeder::class,
            KontenSeeder::class,
            DataContohSeeder::class,
            AkademikSeeder::class,
            ProsesTahunanSeeder::class,
            SitusSeeder::class,
        ]);

        SekolahAktif::instance()->set($sebelumnya);
    }
}
