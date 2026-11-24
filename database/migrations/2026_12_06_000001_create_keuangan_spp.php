<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 poin 5a: keuangan sekolah dasar (SPP dan biaya lain).
 *  - jenis_biaya: SPP, uang gedung, seragam, dst. (bulanan atau sekali bayar).
 *  - tagihan: tagihan per siswa. terbayar dan status dihitung ulang dari pembayaran yang tidak dibatalkan.
 *  - pembayaran_spp: setiap pembayaran (tunai/transfer/online) dengan nomor kuitansi per sekolah. Pembayaran tidak dihapus, hanya dibatalkan.
 *  - modul "keuangan" dimasukkan ke paket Pro dan Premium.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jenis_biaya')) {
            Schema::create('jenis_biaya', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->string('nama', 100);
                $t->unsignedBigInteger('nominal')->default(0);
                $t->string('jenis', 10)->default('bulanan'); // bulanan | sekali
                $t->boolean('aktif')->default(true);
                $t->timestamps();
                $t->unique(['sekolah_id', 'nama']);
            });
        }

        if (! Schema::hasTable('tagihan')) {
            Schema::create('tagihan', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->unsignedBigInteger('siswa_id')->index();
                $t->unsignedBigInteger('jenis_biaya_id')->nullable()->index();
                $t->unsignedBigInteger('tahun_ajaran_id')->nullable()->index();
                $t->string('nama', 150);                      // salinan nama biaya saat dibuat, mis. "SPP Juli 2026"
                $t->string('periode', 20);                    // "2026-07" untuk bulanan, "TA12" untuk sekali bayar (mencegah tagihan ganda)
                $t->unsignedBigInteger('jumlah');
                $t->unsignedBigInteger('terbayar')->default(0);
                $t->string('status', 10)->default('belum')->index(); // belum | sebagian | lunas | batal
                $t->date('jatuh_tempo')->nullable()->index();
                $t->string('catatan', 300)->nullable();
                $t->timestamps();
                $t->unique(['sekolah_id', 'siswa_id', 'jenis_biaya_id', 'periode'], 'tagihan_unik');
            });
        }

        if (! Schema::hasTable('pembayaran_spp')) {
            Schema::create('pembayaran_spp', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->string('nomor', 30);                      // nomor kuitansi, mis. KW-202611-0001
                $t->unsignedBigInteger('tagihan_id')->index();
                $t->string('transaksi_id', 64)->nullable()->index(); // mengelompokkan beberapa tagihan yang dibayar sekaligus (online)
                $t->unsignedBigInteger('jumlah');
                $t->string('metode', 15)->default('tunai');  // tunai | transfer | online
                $t->date('tanggal');
                $t->string('referensi', 120)->nullable();
                $t->string('catatan', 300)->nullable();
                $t->unsignedBigInteger('diterima_oleh')->nullable();
                $t->timestamp('dibatalkan_pada')->nullable();
                $t->string('alasan_batal', 200)->nullable();
                $t->timestamps();
                $t->unique(['sekolah_id', 'nomor']);
            });
        }

        $now = now();
        $modulId = DB::table('modul')->where('kode', 'keuangan')->value('id')
            ?: DB::table('modul')->insertGetId(['kode' => 'keuangan', 'nama' => 'Keuangan & SPP', 'deskripsi' => 'Tagihan SPP, pembayaran, kuitansi, dan panel bendahara', 'created_at' => $now, 'updated_at' => $now]);
        foreach (['pro', 'premium'] as $kode) {
            if ($paketId = DB::table('paket')->where('kode', $kode)->value('id')) {
                DB::table('paket_modul')->updateOrInsert(['paket_id' => $paketId, 'modul_id' => $modulId]);
            }
        }
    }

    public function down(): void
    {
        if ($modulId = DB::table('modul')->where('kode', 'keuangan')->value('id')) {
            DB::table('paket_modul')->where('modul_id', $modulId)->delete();
            DB::table('modul')->where('id', $modulId)->delete();
        }
        Schema::dropIfExists('pembayaran_spp');
        Schema::dropIfExists('tagihan');
        Schema::dropIfExists('jenis_biaya');
    }
};
