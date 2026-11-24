<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 SaaS poin 4: tabel panel Super Admin (tidak terikat sekolah_id):
 * modul, paket (+harga 1/6/12 bulan, + modul per paket), langganan sekolah, invoice sewa, dan log aktivitas.
 */
return new class extends Migration
{
    /** Modul yang dapat diatur per paket. Fitur inti (data siswa/guru/kelas/mapel/jadwal, pengaturan) selalu aktif untuk semua paket. */
    public const MODUL = [
        'absensi' => ['Absensi', 'Absensi harian dan rekap kehadiran'],
        'nilai_rapor' => ['Nilai & Rapor', 'Penilaian, bobot, dan rapor cetak'],
        'konten_web' => ['Konten Website', 'Berita, galeri, pengumuman, agenda, dan program unggulan'],
        'ppdb' => ['PPDB Online', 'Pendaftaran peserta didik baru dan verifikasi'],
        'konsultasi_online' => ['Konsultasi Online', 'Live chat, chatbot FAQ, dan riwayat konsultasi'],
        'unduhan' => ['Unduhan', 'Berkas yang dapat diunduh pengunjung'],
        'portal_orang_tua' => ['Portal Orang Tua', 'Orang tua memantau kehadiran dan nilai anak'],
    ];

    /** Paket bawaan: [kode => [nama, deskripsi, [harga 1, 6, 12 bulan], modul]]. Harga contoh, dapat diubah di panel platform. */
    public const PAKET = [
        'basic' => ['Basic', 'Akademik inti, absensi, nilai, dan konten website.', [100000, 540000, 960000], ['absensi', 'nilai_rapor', 'konten_web']],
        'pro' => ['Pro', 'Basic ditambah PPDB, Konsultasi Online, dan Unduhan.', [200000, 1080000, 1920000], ['absensi', 'nilai_rapor', 'konten_web', 'ppdb', 'konsultasi_online', 'unduhan']],
        'premium' => ['Premium', 'Semua modul termasuk Portal Orang Tua.', [350000, 1890000, 3360000], ['absensi', 'nilai_rapor', 'konten_web', 'ppdb', 'konsultasi_online', 'unduhan', 'portal_orang_tua']],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modul')) {
            Schema::create('modul', function (Blueprint $t) {
                $t->id();
                $t->string('kode', 40)->unique();
                $t->string('nama', 80);
                $t->string('deskripsi', 200)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('paket')) {
            Schema::create('paket', function (Blueprint $t) {
                $t->id();
                $t->string('kode', 40)->unique();
                $t->string('nama', 80);
                $t->string('deskripsi', 300)->nullable();
                $t->unsignedSmallInteger('urutan')->default(0);
                $t->boolean('aktif')->default(true);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('paket_harga')) {
            Schema::create('paket_harga', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('paket_id');
                $t->unsignedTinyInteger('durasi_bulan'); // 1, 6, 12
                $t->unsignedBigInteger('harga');
                $t->timestamps();
                $t->unique(['paket_id', 'durasi_bulan']);
            });
        }

        if (! Schema::hasTable('paket_modul')) {
            Schema::create('paket_modul', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('paket_id');
                $t->unsignedBigInteger('modul_id');
                $t->unique(['paket_id', 'modul_id']);
            });
        }

        if (! Schema::hasTable('langganan')) {
            Schema::create('langganan', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->unsignedBigInteger('paket_id')->index();
                $t->unsignedBigInteger('invoice_id')->nullable()->index();
                $t->unsignedTinyInteger('durasi_bulan');
                $t->date('mulai');
                $t->date('berakhir')->index();
                $t->string('status', 15)->default('aktif'); // aktif | dibatalkan
                $t->string('catatan', 300)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('invoice')) {
            Schema::create('invoice', function (Blueprint $t) {
                $t->id();
                $t->string('nomor', 30)->unique();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->unsignedBigInteger('paket_id')->index();
                $t->unsignedTinyInteger('durasi_bulan');
                $t->unsignedBigInteger('jumlah');
                $t->string('status', 15)->default('menunggu')->index(); // menunggu | lunas | batal
                $t->date('jatuh_tempo')->nullable();
                $t->dateTime('dibayar_pada')->nullable();
                $t->string('metode', 60)->nullable();
                $t->string('catatan', 300)->nullable();
                $t->unsignedBigInteger('dikonfirmasi_oleh')->nullable();
                $t->unsignedBigInteger('langganan_id')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('log_aktivitas')) {
            Schema::create('log_aktivitas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->unsignedBigInteger('sekolah_id')->nullable()->index();
                $t->string('aksi', 60)->index();
                $t->string('deskripsi', 300);
                $t->text('data')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();
            });
        }

        $this->isiBawaan();
    }

    public function down(): void
    {
        foreach (['log_aktivitas', 'invoice', 'langganan', 'paket_modul', 'paket_harga', 'paket', 'modul'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }

    private function isiBawaan(): void
    {
        $now = now();

        foreach (self::MODUL as $kode => [$nama, $deskripsi]) {
            if (! DB::table('modul')->where('kode', $kode)->exists()) {
                DB::table('modul')->insert(['kode' => $kode, 'nama' => $nama, 'deskripsi' => $deskripsi, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $urutan = 0;
        foreach (self::PAKET as $kode => [$nama, $deskripsi, $harga, $modul]) {
            $urutan++;
            if (DB::table('paket')->where('kode', $kode)->exists()) {
                continue;
            }

            $paketId = DB::table('paket')->insertGetId(['kode' => $kode, 'nama' => $nama, 'deskripsi' => $deskripsi, 'urutan' => $urutan, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach ([1, 6, 12] as $i => $bulan) {
                DB::table('paket_harga')->insert(['paket_id' => $paketId, 'durasi_bulan' => $bulan, 'harga' => $harga[$i], 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach ($modul as $mk) {
                DB::table('paket_modul')->insert(['paket_id' => $paketId, 'modul_id' => DB::table('modul')->where('kode', $mk)->value('id')]);
            }
        }
    }
};
