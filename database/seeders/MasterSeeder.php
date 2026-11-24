<?php

namespace Database\Seeders;

use App\Kategori;
use App\Kelas;
use App\Mapel;
use App\Pengaturan;
use Illuminate\Database\Seeder;

/** Data referensi: mapel, kelas, kategori berita, dan halaman profil sekolah (pengaturan id 1-8). */
class MasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Matematika', 'Bahasa Indonesia', 'IPA', 'IPS', 'Bahasa Inggris'] as $nama) {
            Mapel::updateOrCreate(['mapel_nama' => $nama]);
        }

        foreach (['VII A', 'VII B', 'VIII A', 'IX A'] as $nama) {
            Kelas::updateOrCreate(['nama_kelas' => $nama]);
        }

        foreach (['Umum', 'Prestasi', 'Kegiatan Sekolah'] as $nama) {
            Kategori::updateOrCreate(['kategori_nama' => $nama]);
        }

        // Id 1-8 dibaca langsung oleh ProfileDepanController / PengaturanController.
        $halaman = [
            1 => 'Tentang Kami',
            2 => 'Visi dan Misi',
            3 => 'Sejarah',
            4 => 'Sosial Media',
            5 => 'Administrasi Kantor Sekolah',
            6 => 'Perpustakaan',
            7 => 'Lab Komputer',
            8 => 'Struktur Organisasi',
        ];
        foreach ($halaman as $id => $judul) {
            Pengaturan::firstOrCreate(
                ['slot' => $id],
                ['judul' => $judul, 'deskripsi' => "<p>Konten contoh untuk halaman {$judul}.</p>"],
            );
        }
    }
}
