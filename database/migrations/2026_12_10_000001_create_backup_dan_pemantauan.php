<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 poin 8: pemantauan dan backup per sekolah.
 *  - users.login_terakhir_at: untuk "login terakhir" di pemantauan.
 *  - sekolah.dinonaktifkan_pada / diarsipkan_pada: arsip otomatis (backup) bagi sekolah yang nonaktif 90 hari.
 *  - backup_sekolah: setiap arsip zip data satu sekolah (manual, terjadwal, atau arsip).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'login_terakhir_at')) {
            Schema::table('users', fn (Blueprint $t) => $t->timestamp('login_terakhir_at')->nullable()->after('remember_token'));
        }

        if (! Schema::hasColumn('sekolah', 'dinonaktifkan_pada')) {
            Schema::table('sekolah', function (Blueprint $t) {
                $t->timestamp('dinonaktifkan_pada')->nullable()->after('status');
                $t->timestamp('diarsipkan_pada')->nullable()->after('dinonaktifkan_pada');
            });
            // sekolah yang sudah nonaktif: hitung 90 hari sejak terakhir diubah
            DB::table('sekolah')->where('status', 'nonaktif')->update(['dinonaktifkan_pada' => DB::raw('updated_at')]);
        }

        if (! Schema::hasTable('backup_sekolah')) {
            Schema::create('backup_sekolah', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sekolah_id')->index();
                $t->string('jenis', 12)->default('manual');           // manual | terjadwal | arsip
                $t->string('status', 12)->default('berjalan')->index(); // berjalan | selesai | gagal
                $t->string('berkas', 255)->nullable();                // path pada disk "local" (storage/app/private)
                $t->unsignedBigInteger('ukuran')->default(0);
                $t->string('checksum', 64)->nullable();               // sha256 isi zip
                $t->unsignedInteger('jumlah_tabel')->default(0);
                $t->unsignedInteger('jumlah_baris')->default(0);
                $t->unsignedInteger('jumlah_berkas')->default(0);
                $t->unsignedBigInteger('dibuat_oleh')->nullable();
                $t->string('galat', 300)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_sekolah');
        if (Schema::hasColumn('sekolah', 'dinonaktifkan_pada')) {
            Schema::table('sekolah', fn (Blueprint $t) => $t->dropColumn(['dinonaktifkan_pada', 'diarsipkan_pada']));
        }
        if (Schema::hasColumn('users', 'login_terakhir_at')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('login_terakhir_at'));
        }
    }
};
