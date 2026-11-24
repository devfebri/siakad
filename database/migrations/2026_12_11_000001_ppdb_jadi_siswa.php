<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftar PPDB yang diterima dapat dijadikan siswa (dan orang tuanya):
 *  - agama: ditanyakan di formulir (opsional bagi pendaftar lama; dilengkapi admin saat konversi).
 *  - siswa_id / dijadikan_siswa_pada: menandai pendaftar yang sudah dikonversi supaya tidak dikonversi dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ppdb_pendaftar', 'siswa_id')) {
            Schema::table('ppdb_pendaftar', function (Blueprint $t) {
                $t->string('agama', 20)->nullable()->after('jenis_kelamin');
                $t->unsignedBigInteger('siswa_id')->nullable()->index()->after('status');
                $t->timestamp('dijadikan_siswa_pada')->nullable()->after('siswa_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ppdb_pendaftar', 'siswa_id')) {
            Schema::table('ppdb_pendaftar', fn (Blueprint $t) => $t->dropColumn(['agama', 'siswa_id', 'dijadikan_siswa_pada']));
        }
    }
};
