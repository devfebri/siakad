<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Sekolah yang sudah punya data guru/siswa dianggap sudah selesai pengaturan awalnya (pengingat di dashboard tidak perlu muncul). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('sekolah')->pluck('id') as $id) {
            $berisi = DB::table('siswa')->where('sekolah_id', $id)->exists() || DB::table('guru')->where('sekolah_id', $id)->exists();
            $sudah = DB::table('site_settings')->where('sekolah_id', $id)->where('key', 'setup_selesai')->exists();

            if ($berisi && ! $sudah) {
                DB::table('site_settings')->insert(['sekolah_id' => $id, 'key' => 'setup_selesai', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'setup_selesai')->delete();
    }
};
