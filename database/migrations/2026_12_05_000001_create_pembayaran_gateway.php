<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 poin 4: pembayaran sewa lewat gerbang.
 *  - pembayaran_gateway: satu baris per percobaan bayar sebuah invoice (order_id unik di gerbang).
 *  - callback_gateway: catatan setiap callback masuk; unik per (driver, kunci) supaya callback ganda tidak diproses dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pembayaran_gateway')) {
            Schema::create('pembayaran_gateway', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('invoice_id')->index();
                $t->string('driver', 20);
                $t->string('order_id', 64)->unique();
                $t->unsignedBigInteger('jumlah');
                $t->string('status', 15)->default('menunggu')->index(); // menunggu | berhasil | gagal | kedaluwarsa
                $t->text('url_bayar')->nullable();
                $t->string('referensi', 120)->nullable();
                $t->string('metode', 60)->nullable();
                $t->timestamp('kedaluwarsa_pada')->nullable();
                $t->timestamp('dibayar_pada')->nullable();
                $t->string('catatan', 300)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('callback_gateway')) {
            Schema::create('callback_gateway', function (Blueprint $t) {
                $t->id();
                $t->string('driver', 20);
                $t->string('kunci', 150);
                $t->string('order_id', 64)->nullable()->index();
                $t->string('status', 15)->nullable();
                $t->boolean('valid')->default(false);
                $t->string('hasil', 40)->nullable();      // diproses | duplikat | diabaikan | ditolak
                $t->text('payload')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamps();
                $t->index(['driver', 'kunci']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('callback_gateway');
        Schema::dropIfExists('pembayaran_gateway');
    }
};
