<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jadwal pelajaran, absensi harian siswa, dan nilai (untuk rapor). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jadwal')) {
            Schema::create('jadwal', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kelas_id')->index();
                $table->unsignedBigInteger('mapel_id')->index();
                $table->unsignedBigInteger('guru_id')->nullable()->index();
                $table->unsignedTinyInteger('hari'); // 1 = Senin ... 6 = Sabtu
                $table->time('jam_mulai');
                $table->time('jam_selesai');
                $table->string('ruang', 40)->nullable();
                $table->string('tahun_ajaran', 9);
                $table->string('semester', 6);
                $table->timestamps();

                $table->index(['kelas_id', 'hari']);
                $table->index(['guru_id', 'hari']);
            });
        }

        if (! Schema::hasTable('absensi')) {
            Schema::create('absensi', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('siswa_id');
                $table->unsignedBigInteger('kelas_id')->index();
                $table->date('tanggal');
                $table->string('status', 10); // hadir | izin | sakit | alpa
                $table->string('keterangan', 150)->nullable();
                $table->unsignedBigInteger('dicatat_oleh')->nullable();
                $table->timestamps();

                $table->unique(['siswa_id', 'tanggal']);
                $table->index(['kelas_id', 'tanggal']);
                $table->index('tanggal');
            });
        }

        if (! Schema::hasTable('nilai')) {
            Schema::create('nilai', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('siswa_id')->index();
                $table->unsignedBigInteger('kelas_id')->index();
                $table->unsignedBigInteger('mapel_id')->index();
                $table->string('tahun_ajaran', 9);
                $table->string('semester', 6);
                $table->string('jenis', 12); // tugas | uh | uts | uas
                $table->decimal('nilai', 5, 2);
                $table->unsignedBigInteger('dicatat_oleh')->nullable();
                $table->timestamps();

                $table->unique(['siswa_id', 'mapel_id', 'tahun_ajaran', 'semester', 'jenis'], 'nilai_unik');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai');
        Schema::dropIfExists('absensi');
        Schema::dropIfExists('jadwal');
    }
};
