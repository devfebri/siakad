<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tahap 3 poin 2: pengaturan SSO per sekolah (alamat E-Learning yang dibuka tombol "Buka E-Learning" dan saklar tampil). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sso_klien') && ! Schema::hasColumn('sso_klien', 'sso_aktif')) {
            Schema::table('sso_klien', function (Blueprint $t) {
                $t->text('elearning_url')->nullable()->after('redirect_url');
                $t->boolean('sso_aktif')->default(false)->after('aktif');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sso_klien', 'sso_aktif')) {
            Schema::table('sso_klien', function (Blueprint $t) {
                $t->dropColumn(['elearning_url', 'sso_aktif']);
            });
        }
    }
};
