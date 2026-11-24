<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 poin 1: API data induk untuk aplikasi E-Learning.
 *  - sso_klien: satu klien E-Learning per sekolah (client_id, redirect, webhook). Rahasia klien OAuth (di-hash) ada di oauth_clients (Passport).
 *  - webhook_log: riwayat pengiriman webhook per percobaan.
 *  - api_request_log: jumlah request API per klien per hari (untuk monitoring).
 *  - modul "integrasi_elearning" dimasukkan ke paket Premium.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sso_klien')) {
            Schema::create('sso_klien', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->unique();
                $t->string('nama', 100)->default('E-Learning');
                $t->uuid('client_id')->unique();          // = oauth_clients.id
                $t->text('redirect_url')->nullable();
                $t->text('webhook_url')->nullable();
                $t->text('webhook_secret')->nullable();   // terenkripsi (cast encrypted), dipakai menandatangani HMAC
                $t->boolean('aktif')->default(false);
                $t->timestamp('terakhir_dipakai')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('webhook_log')) {
            Schema::create('webhook_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->unsignedBigInteger('sso_klien_id')->index();
                $t->uuid('pengiriman_id')->index();        // sama untuk semua percobaan satu event
                $t->string('event', 60);
                $t->unsignedTinyInteger('percobaan')->default(1);
                $t->string('status', 12);                  // berhasil | gagal | dibatalkan
                $t->unsignedSmallInteger('http_status')->nullable();
                $t->string('pesan', 300)->nullable();
                $t->unsignedInteger('durasi_ms')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('api_request_log')) {
            Schema::create('api_request_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->date('tanggal');
                $t->unsignedInteger('jumlah')->default(0);
                $t->timestamps();
                $t->unique(['sekolah_id', 'tanggal']);
            });
        }

        $now = now();
        $modulId = DB::table('modul')->where('kode', 'integrasi_elearning')->value('id');
        if (! $modulId) {
            $modulId = DB::table('modul')->insertGetId([
                'kode' => 'integrasi_elearning', 'nama' => 'Integrasi E-Learning',
                'deskripsi' => 'API data induk, webhook, dan login tunggal (SSO) ke aplikasi E-Learning', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if ($premium = DB::table('paket')->where('kode', 'premium')->value('id')) {
            DB::table('paket_modul')->updateOrInsert(['paket_id' => $premium, 'modul_id' => $modulId]);
        }
    }

    public function down(): void
    {
        $modulId = DB::table('modul')->where('kode', 'integrasi_elearning')->value('id');
        if ($modulId) {
            DB::table('paket_modul')->where('modul_id', $modulId)->delete();
            DB::table('modul')->where('id', $modulId)->delete();
        }
        Schema::dropIfExists('api_request_log');
        Schema::dropIfExists('webhook_log');
        Schema::dropIfExists('sso_klien');
    }
};
