<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 poin 5: pembayaran SPP online oleh orang tua. Kunci gerbang MILIK SEKOLAH (dana masuk ke rekening sekolah).
 *  - pengaturan_pembayaran_sekolah: driver, kunci terenkripsi, biaya admin, dan token alamat callback per sekolah.
 *  - transaksi_spp + transaksi_spp_item: satu transaksi di gerbang bisa membayar beberapa tagihan sekaligus.
 *  - callback_gateway.sekolah_id: callback SPP dicatat per sekolah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pengaturan_pembayaran_sekolah')) {
            Schema::create('pengaturan_pembayaran_sekolah', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->unique();
                $t->string('driver', 20)->default('nonaktif');            // nonaktif | midtrans | xendit | fake
                $t->boolean('sandbox')->default(true);
                $t->text('midtrans_server_key')->nullable();               // terenkripsi
                $t->text('xendit_secret_key')->nullable();                 // terenkripsi
                $t->text('xendit_callback_token')->nullable();             // terenkripsi
                $t->string('biaya_admin_ditanggung', 10)->default('sekolah'); // sekolah | ortu
                $t->unsignedInteger('biaya_admin_tetap')->default(0);      // rupiah per transaksi
                $t->decimal('biaya_admin_persen', 5, 2)->default(0);       // persen dari jumlah tagihan
                $t->string('webhook_token', 64)->unique();                 // bagian alamat callback, supaya sekolah dikenali tanpa sesi
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('transaksi_spp')) {
            Schema::create('transaksi_spp', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->unsignedBigInteger('user_id')->nullable()->index();   // orang tua yang membayar
                $t->string('driver', 20);
                $t->string('order_id', 64)->unique();
                $t->unsignedBigInteger('subtotal');                        // jumlah tagihan yang dibayar
                $t->unsignedBigInteger('biaya_admin')->default(0);
                $t->string('biaya_ditanggung', 10)->default('sekolah');
                $t->unsignedBigInteger('total');                           // yang ditagihkan ke pembayar di gerbang
                $t->string('status', 15)->default('menunggu')->index();   // menunggu | berhasil | gagal | kedaluwarsa
                $t->text('url_bayar')->nullable();
                $t->string('referensi', 120)->nullable();
                $t->string('metode', 60)->nullable();
                $t->timestamp('kedaluwarsa_pada')->nullable();
                $t->timestamp('dibayar_pada')->nullable();
                $t->string('catatan', 300)->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('transaksi_spp_item')) {
            Schema::create('transaksi_spp_item', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('transaksi_spp_id')->index();
                $t->unsignedBigInteger('tagihan_id')->index();
                $t->unsignedBigInteger('jumlah');                          // sisa tagihan saat transaksi dibuat
                $t->timestamps();
                $t->unique(['transaksi_spp_id', 'tagihan_id']);
            });
        }

        if (Schema::hasTable('callback_gateway') && ! Schema::hasColumn('callback_gateway', 'sekolah_id')) {
            Schema::table('callback_gateway', function (Blueprint $t) {
                $t->unsignedBigInteger('sekolah_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('callback_gateway', 'sekolah_id')) {
            Schema::table('callback_gateway', fn (Blueprint $t) => $t->dropColumn('sekolah_id'));
        }
        Schema::dropIfExists('transaksi_spp_item');
        Schema::dropIfExists('transaksi_spp');
        Schema::dropIfExists('pengaturan_pembayaran_sekolah');
    }
};
