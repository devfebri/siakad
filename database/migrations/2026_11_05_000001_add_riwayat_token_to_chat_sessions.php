<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_sessions') || Schema::hasColumn('chat_sessions', 'riwayat_token')) {
            return;
        }

        // Token terpisah untuk tautan riwayat di email, supaya token sesi chat (rahasia pengunjung) tidak pernah masuk URL.
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->string('riwayat_token', 40)->nullable()->unique()->after('token');
        });

        foreach (DB::table('chat_sessions')->whereNull('riwayat_token')->pluck('id') as $id) {
            DB::table('chat_sessions')->where('id', $id)->update(['riwayat_token' => Str::random(40)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_sessions', 'riwayat_token')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                $table->dropUnique(['riwayat_token']);
                $table->dropColumn('riwayat_token');
            });
        }
    }
};
