<?php

namespace Database\Seeders;

use App\Agenda;
use App\Berita;
use App\Kategori;
use App\Pengumuman;
use App\Timeline;
use Illuminate\Database\Seeder;

/** Konten halaman depan: berita, pengumuman, agenda, galeri, dan timeline dashboard. */
class KontenSeeder extends Seeder
{
    public function run(): void
    {
        $kategori = Kategori::orderBy('id')->pluck('id');

        $berita = [
            ['Siswa SMPN 1 Muaro Jambi Juara Olimpiade Sains', $kategori[1]],
            ['Upacara Hari Kemerdekaan di Halaman Sekolah', $kategori[2]],
            ['Pengumuman Jadwal Penilaian Akhir Semester', $kategori[0]],
        ];
        foreach ($berita as [$judul, $kategoriId]) {
            // Slug dibuat otomatis oleh Sluggable saat record baru dibuat.
            Berita::firstOrCreate(['berita_judul' => $judul], [
                'berita_isi' => "<p>{$judul}. Ini adalah isi berita contoh untuk pengujian tampilan.</p>",
                'kategori_id' => $kategoriId,
                'author' => 'Admin Sekolah',
                'berita_views' => 0,
            ]);
        }

        Pengumuman::firstOrCreate(['pengumuman_judul' => 'Libur Semester Ganjil'], [
            'pengumuman_deskripsi' => 'Libur semester dimulai pekan depan.',
            'pengumuman_author' => 'Admin Sekolah',
        ]);

        Agenda::firstOrCreate(['agenda_nama' => 'Rapat Guru'], [
            'agenda_deskripsi' => 'Rapat koordinasi awal semester.',
            'agenda_mulai' => now()->addDays(3)->format('Y-m-d'),
            'agenda_selesai' => now()->addDays(3)->format('Y-m-d'),
            'agenda_tempat' => 'Aula Sekolah',
            'agenda_waktu' => '08:00',
            'agenda_keterangan' => '-',
            'agenda_author' => 'Admin Sekolah',
        ]);


        Timeline::firstOrCreate(['message' => 'menambahkan data contoh untuk pengujian'], ['author' => 'Admin Sekolah']);
    }
}
