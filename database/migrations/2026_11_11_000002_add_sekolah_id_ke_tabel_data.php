<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tahap 1 SaaS: sekolah_id (index) pada semua tabel data sekolah. Data lama dimasukkan ke 1 sekolah bawaan
 * (nama dari pengaturan situs bila ada; subdomain dari SEKOLAH_DEFAULT atau "default").
 */
return new class extends Migration
{
    public const TABEL = [
        'users', 'guru', 'siswa', 'kelas', 'mapel', 'jadwal', 'absensi', 'nilai',
        'berita', 'kategori', 'gallery', 'pengumuman', 'agenda', 'timeline', 'pengaturan', 'site_settings',
        'program_unggulan', 'testimoni', 'ppdb_pendaftar', 'unduhan',
        'chat_sessions', 'chat_messages', 'chat_attachments', 'chat_faqs', 'chat_settings', 'chat_quick_replies', 'chat_agent_status',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (Schema::hasTable($tabel) && ! Schema::hasColumn($tabel, 'sekolah_id')) {
                Schema::table($tabel, function (Blueprint $t) {
                    $t->unsignedBigInteger('sekolah_id')->nullable()->index();
                });
            }
        }

        $this->isiSekolahBawaan();
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABEL) as $tabel) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, 'sekolah_id')) {
                Schema::table($tabel, function (Blueprint $t) {
                    $t->dropIndex(['sekolah_id']);
                    $t->dropColumn('sekolah_id');
                });
            }
        }
    }

    private function isiSekolahBawaan(): void
    {
        $adaData = DB::table('users')->exists();
        $id = DB::table('sekolah')->orderBy('id')->value('id');

        // Instalasi kosong (belum ada data) tidak perlu sekolah bawaan; sekolah dibuat lewat seeder atau panel platform.
        if (! $id && ! $adaData) {
            return;
        }

        if (! $id) {
            $nama = Schema::hasTable('site_settings') ? DB::table('site_settings')->where('key', 'nama_sekolah')->value('value') : null;
            $npsn = Schema::hasTable('site_settings') ? DB::table('site_settings')->where('key', 'npsn')->value('value') : null;
            $subdomain = Str::slug((string) config('sekolah.default_subdomain') ?: 'default') ?: 'default';

            $id = DB::table('sekolah')->insertGetId([
                'nama' => $nama ?: (config('school.name') ?: 'Sekolah'),
                'npsn' => $npsn,
                'jenjang' => 'SMA',
                'subdomain' => $subdomain,
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::TABEL as $tabel) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, 'sekolah_id')) {
                DB::table($tabel)->whereNull('sekolah_id')->update(['sekolah_id' => $id]);
            }
        }
    }
};
