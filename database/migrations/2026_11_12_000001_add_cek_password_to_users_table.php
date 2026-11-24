<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'cek_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Salinan password (teks polos) agar pengelola aplikasi dapat memeriksanya. Akun lama = null sampai password diubah/direset.
            $table->text('cek_password')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'cek_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('cek_password');
            });
        }
    }
};
