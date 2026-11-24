<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_sessions') && ! Schema::hasColumn('chat_sessions', 'balas_email')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                // Pengunjung memilih meninggalkan pesan dan dibalas lewat email (setelah menunggu > 10 menit).
                $table->boolean('balas_email')->default(false)->after('persetujuan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_sessions', 'balas_email')) {
            Schema::table('chat_sessions', fn (Blueprint $table) => $table->dropColumn('balas_email'));
        }
    }
};
