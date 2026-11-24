<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 poin 6: notifikasi WhatsApp per sekolah.
 *  - pengaturan_wa_sekolah: nonaktif secara bawaan; kunci terenkripsi; kejadian dan template pesan dapat diatur sekolah.
 *  - wa_log: satu baris per pesan. Unik per (sekolah, kejadian, referensi, nomor) sehingga kejadian yang sama tidak dikirim dua kali.
 *  - modul "notifikasi_wa" dimasukkan ke paket Pro dan Premium.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pengaturan_wa_sekolah')) {
            Schema::create('pengaturan_wa_sekolah', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->unique();
                $t->boolean('aktif')->default(false);
                $t->string('driver', 20)->default('fonnte');               // fonnte | wablas | cloud | fake
                $t->text('token')->nullable();                              // terenkripsi
                $t->text('kunci2')->nullable();                             // terenkripsi: Cloud API = Phone Number ID, Wablas = secret
                $t->string('server_url', 200)->nullable();                  // Wablas: alamat server
                $t->string('nomor_admin', 25)->nullable();                  // tujuan pengingat langganan
                $t->unsignedSmallInteger('batas_per_menit')->default(20);   // batas laju pengiriman
                $t->text('event_aktif')->nullable();                        // JSON kejadian => true/false (kosong = semua aktif)
                $t->text('templat')->nullable();                            // JSON kejadian => teks pesan buatan sekolah
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('wa_log')) {
            Schema::create('wa_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->string('kejadian', 30);
                $t->string('referensi', 120);                               // mengidentifikasi kejadian, mis. "alpa:12:2026-11-12"
                $t->string('nomor', 20);
                $t->text('pesan');
                $t->string('status', 12)->default('antri')->index();       // antri | terkirim | gagal | dilewati
                $t->unsignedTinyInteger('percobaan')->default(0);
                $t->string('galat', 300)->nullable();
                $t->string('id_pesan', 100)->nullable();
                $t->string('driver', 20)->nullable();
                $t->timestamp('terkirim_pada')->nullable();
                $t->timestamps();
                $t->unique(['sekolah_id', 'kejadian', 'referensi', 'nomor'], 'wa_log_unik');
            });
        }

        $now = now();
        $modulId = DB::table('modul')->where('kode', 'notifikasi_wa')->value('id')
            ?: DB::table('modul')->insertGetId(['kode' => 'notifikasi_wa', 'nama' => 'Notifikasi WhatsApp', 'deskripsi' => 'Pesan WhatsApp otomatis ke orang tua: alpa, tagihan, pembayaran, dan pengumuman', 'created_at' => $now, 'updated_at' => $now]);
        foreach (['pro', 'premium'] as $kode) {
            if ($paketId = DB::table('paket')->where('kode', $kode)->value('id')) {
                DB::table('paket_modul')->updateOrInsert(['paket_id' => $paketId, 'modul_id' => $modulId]);
            }
        }
    }

    public function down(): void
    {
        if ($modulId = DB::table('modul')->where('kode', 'notifikasi_wa')->value('id')) {
            DB::table('paket_modul')->where('modul_id', $modulId)->delete();
            DB::table('modul')->where('id', $modulId)->delete();
        }
        Schema::dropIfExists('wa_log');
        Schema::dropIfExists('pengaturan_wa_sekolah');
    }
};
