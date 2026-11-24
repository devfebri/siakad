<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proses tahunan: status tahun ajaran (draf/aktif/ditutup), status siswa, akun nonaktif,
 * usulan dan hasil kenaikan, serta data alumni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->string('status', 10)->default('draf')->index(); // draf | aktif | ditutup
            $table->unsignedBigInteger('dari_tahun_id')->nullable(); // diisi bila dibuka lewat Proses Tahunan
        });
        // tahun ajaran yang punya semester aktif = aktif; yang lain sudah lewat = ditutup
        $aktif = DB::table('semester')->where('aktif', true)->pluck('tahun_ajaran_id');
        DB::table('tahun_ajaran')->whereIn('id', $aktif)->update(['status' => 'aktif']);
        DB::table('tahun_ajaran')->whereNotIn('id', $aktif)->update(['status' => 'ditutup']);

        Schema::table('siswa', function (Blueprint $table) {
            $table->string('status', 15)->default('aktif')->index(); // aktif | lulus | tidak_lulus | mutasi_keluar
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('aktif')->default(true);
        });

        Schema::create('usulan_kenaikan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->unsignedBigInteger('rombel_id');
            $table->string('usulan', 12); // naik | tinggal | lulus | tidak_lulus
            $table->string('catatan', 300)->nullable();
            $table->unsignedBigInteger('diusulkan_oleh')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'tahun_ajaran_id']);
        });

        Schema::create('kenaikan', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('tahun_ajaran_id'); // tahun ajaran asal
            $table->unsignedBigInteger('tahun_ajaran_tujuan_id');
            $table->unsignedBigInteger('rombel_asal_id');
            $table->unsignedBigInteger('rombel_tujuan_id')->nullable(); // kosong untuk lulus/tidak lulus
            $table->string('hasil', 12); // naik | tinggal | lulus | tidak_lulus
            $table->string('no_ijazah', 60)->nullable();
            $table->unsignedBigInteger('diproses_oleh')->nullable();
            $table->timestamps();

            $table->unique(['siswa_id', 'tahun_ajaran_id']);
            $table->index('tahun_ajaran_tujuan_id');
        });

        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->unsignedBigInteger('siswa_id')->unique();
            $table->unsignedSmallInteger('angkatan')->index(); // tahun kelulusan
            $table->date('tanggal_lulus');
            $table->string('status', 12)->default('lulus'); // lulus | tidak_lulus
            $table->string('no_ijazah', 60)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
        Schema::dropIfExists('kenaikan');
        Schema::dropIfExists('usulan_kenaikan');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('aktif'));
        Schema::table('siswa', function (Blueprint $t) {
            $t->dropIndex(['status']);
            $t->dropColumn('status');
        });
        Schema::table('tahun_ajaran', function (Blueprint $t) {
            $t->dropIndex(['status']);
            $t->dropColumn(['status', 'dari_tahun_id']);
        });
    }
};
