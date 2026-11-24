<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pendaftar PPDB (penerimaan peserta didik baru). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ppdb_pendaftar')) {
            return;
        }

        Schema::create('ppdb_pendaftar', function (Blueprint $table) {
            $table->id();
            $table->string('no_pendaftaran', 30)->unique();
            $table->string('tahun_ajaran', 9)->index();
            $table->string('nama', 120);
            $table->string('jenis_kelamin', 12);
            $table->string('tempat_lahir', 80);
            $table->date('tanggal_lahir');
            $table->string('nisn', 20)->nullable();
            $table->string('asal_sekolah', 120);
            $table->text('alamat');
            $table->string('nama_ortu', 120);
            $table->string('telepon', 25);
            $table->string('email', 150)->nullable();
            $table->string('jalur', 30);
            $table->string('peminatan', 30)->nullable();
            $table->decimal('nilai_rata', 5, 2)->nullable();
            $table->string('berkas')->nullable();
            $table->string('status', 12)->default('menunggu')->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppdb_pendaftar');
    }
};
