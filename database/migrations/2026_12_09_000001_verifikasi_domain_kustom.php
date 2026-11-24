<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tahap 3 poin 7: domain kustom harus dibuktikan milik sekolah (rekaman TXT berisi token) sebelum dipakai mengenali sekolah.
 * Domain yang sudah terdaftar sebelum fitur ini (diisi Super Admin) dianggap terverifikasi agar situs yang berjalan tidak putus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sekolah', 'domain_token')) {
            Schema::table('sekolah', function (Blueprint $t) {
                $t->string('domain_token', 40)->nullable()->after('domain_kustom');
                $t->timestamp('domain_terverifikasi_pada')->nullable()->after('domain_token');
            });
        }

        foreach (DB::table('sekolah')->get(['id', 'domain_kustom', 'domain_token']) as $s) {
            DB::table('sekolah')->where('id', $s->id)->update([
                'domain_token' => $s->domain_token ?: Str::random(40),
                'domain_terverifikasi_pada' => $s->domain_kustom ? now() : null,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sekolah', 'domain_token')) {
            Schema::table('sekolah', fn (Blueprint $t) => $t->dropColumn(['domain_token', 'domain_terverifikasi_pada']));
        }
    }
};
