<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas menjadi rombongan belajar (rombel) per tahun ajaran, lengkap dengan wali kelas.
 * Tabel kelas diganti nama menjadi rombel (id tetap, jadi kelas_id di jadwal/absensi/nilai tetap valid);
 * keanggotaan siswa tercatat per tahun ajaran di rombel_siswa. siswa.kelas_id tetap ada sebagai rombel pada tahun ajaran aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('kelas', 'rombel');

        Schema::table('rombel', function (Blueprint $table) {
            $table->unsignedBigInteger('tahun_ajaran_id')->nullable()->index();
            $table->string('tingkat', 10)->nullable();
            $table->unsignedBigInteger('wali_guru_id')->nullable()->index();
        });

        // siswa boleh belum punya rombel (mis. awal tahun ajaran baru)
        Schema::table('siswa', function (Blueprint $table) {
            $table->unsignedBigInteger('kelas_id')->nullable()->change();
        });

        Schema::create('rombel_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->unsignedBigInteger('rombel_id')->index();
            $table->unsignedBigInteger('siswa_id')->index();
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->timestamps();

            $table->unique(['siswa_id', 'tahun_ajaran_id']); // satu rombel per siswa per tahun ajaran
        });

        foreach (DB::table('sekolah')->pluck('id') as $sekolahId) {
            $taId = DB::table('semester')->where('sekolah_id', $sekolahId)->where('aktif', true)->value('tahun_ajaran_id')
                ?? DB::table('tahun_ajaran')->where('sekolah_id', $sekolahId)->orderByDesc('nama')->value('id');

            if (! $taId) {
                $awal = now()->month >= 7 ? now()->year : now()->year - 1;
                $taId = DB::table('tahun_ajaran')->insertGetId(['sekolah_id' => $sekolahId, 'nama' => $awal.'/'.($awal + 1), 'mulai' => "$awal-07-01", 'selesai' => ($awal + 1).'-06-30', 'created_at' => now(), 'updated_at' => now()]);
            }

            DB::table('rombel')->where('sekolah_id', $sekolahId)->update(['tahun_ajaran_id' => $taId]);

            foreach (DB::table('siswa')->where('sekolah_id', $sekolahId)->whereNotNull('kelas_id')->get(['id', 'kelas_id']) as $s) {
                DB::table('rombel_siswa')->insert(['sekolah_id' => $sekolahId, 'rombel_id' => $s->kelas_id, 'siswa_id' => $s->id, 'tahun_ajaran_id' => $taId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rombel_siswa');
        Schema::table('rombel', function (Blueprint $table) {
            $table->dropIndex(['tahun_ajaran_id']);
            $table->dropIndex(['wali_guru_id']);
            $table->dropColumn(['tahun_ajaran_id', 'tingkat', 'wali_guru_id']);
        });
        Schema::rename('rombel', 'kelas');
    }
};
