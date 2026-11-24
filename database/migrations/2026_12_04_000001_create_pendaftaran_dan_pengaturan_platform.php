<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 poin 3:
 *  - platform_setting: pengaturan milik platform (trial, kontak, dan kunci gateway pada poin berikutnya). Tidak terikat sekolah.
 *  - pendaftaran_sekolah: pendaftaran mandiri yang menunggu verifikasi email. Sekolah baru dibuat SETELAH email terverifikasi.
 *  - langganan.trial: menandai masa percobaan gratis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_setting')) {
            Schema::create('platform_setting', function (Blueprint $t) {
                $t->id();
                $t->string('key', 80)->unique();
                $t->text('value')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('pendaftaran_sekolah')) {
            Schema::create('pendaftaran_sekolah', function (Blueprint $t) {
                $t->id();
                $t->string('token_hash', 64)->unique();   // sha256 token verifikasi; tokennya sendiri hanya ada di email
                $t->string('email', 191)->index();
                $t->string('subdomain', 60)->index();
                $t->string('npsn', 20)->nullable()->index();
                $t->text('data');                          // JSON: sekolah, paket, durasi, admin (password sudah di-hash)
                $t->boolean('pakai_trial')->default(false);
                $t->timestamp('kedaluwarsa_pada');
                $t->timestamp('diverifikasi_pada')->nullable();
                $t->unsignedBigInteger('sekolah_id')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('langganan') && ! Schema::hasColumn('langganan', 'trial')) {
            Schema::table('langganan', function (Blueprint $t) {
                $t->boolean('trial')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('langganan', 'trial')) {
            Schema::table('langganan', fn (Blueprint $t) => $t->dropColumn('trial'));
        }
        Schema::dropIfExists('pendaftaran_sekolah');
        Schema::dropIfExists('platform_setting');
    }
};
