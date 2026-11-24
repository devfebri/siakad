<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 SaaS: users.role (satu peran per akun) diganti tabel peran + peran_user (satu akun boleh punya banyak peran).
 * Role lama dipindahkan: admin -> admin, guru -> guru, siswa -> siswa; lalu kolom users.role dihapus.
 */
return new class extends Migration
{
    public const PERAN = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin Sekolah',
        'kepsek' => 'Kepala Sekolah',
        'guru' => 'Guru',
        'wali_kelas' => 'Wali Kelas',
        'bendahara' => 'Bendahara',
        'siswa' => 'Siswa',
        'orang_tua' => 'Orang Tua',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('peran')) {
            Schema::create('peran', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 30)->unique();
                $table->string('nama', 60);
                $table->timestamps();
            });
        }

        foreach (self::PERAN as $kode => $nama) {
            if (! DB::table('peran')->where('kode', $kode)->exists()) {
                DB::table('peran')->insert(['kode' => $kode, 'nama' => $nama, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (! Schema::hasTable('peran_user')) {
            Schema::create('peran_user', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('peran_id')->index();
                $table->timestamps();
                $table->unique(['user_id', 'peran_id']);
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            $peranId = DB::table('peran')->pluck('id', 'kode');
            foreach (DB::table('users')->get(['id', 'role']) as $u) {
                if (isset($peranId[$u->role]) && ! DB::table('peran_user')->where(['user_id' => $u->id, 'peran_id' => $peranId[$u->role]])->exists()) {
                    DB::table('peran_user')->insert(['user_id' => $u->id, 'peran_id' => $peranId[$u->role], 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 50)->default('siswa')->after('id');
            });

            // peran terpenting yang punya padanan role lama
            foreach (['siswa', 'guru', 'admin'] as $kode) {
                $id = DB::table('peran')->where('kode', $kode)->value('id');
                if ($id) {
                    $ids = DB::table('peran_user')->where('peran_id', $id)->pluck('user_id');
                    DB::table('users')->whereIn('id', $ids)->update(['role' => $kode]);
                }
            }
        }

        Schema::dropIfExists('peran_user');
        Schema::dropIfExists('peran');
    }
};
