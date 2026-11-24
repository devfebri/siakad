<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 1 SaaS poin 5: aturan langganan.
 *  - sekolah.tanpa_batas: sekolah tanpa batas langganan (internal/demo/instalasi lama). Sekolah yang sudah ada saat migrasi
 *    diberi tanda ini agar tidak langsung terkunci; super admin dapat mematikannya di panel platform.
 *  - langganan.pengingat_*: penanda pengingat H-30/H-7/H-1 yang sudah dikirim agar tidak dobel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sekolah') && ! Schema::hasColumn('sekolah', 'tanpa_batas')) {
            Schema::table('sekolah', function (Blueprint $t) {
                $t->boolean('tanpa_batas')->default(false)->after('status');
            });
            DB::table('sekolah')->update(['tanpa_batas' => true]);
        }

        if (Schema::hasTable('langganan') && ! Schema::hasColumn('langganan', 'pengingat_h30_at')) {
            Schema::table('langganan', function (Blueprint $t) {
                $t->timestamp('pengingat_h30_at')->nullable();
                $t->timestamp('pengingat_h7_at')->nullable();
                $t->timestamp('pengingat_h1_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('langganan', 'pengingat_h30_at')) {
            Schema::table('langganan', fn (Blueprint $t) => $t->dropColumn(['pengingat_h30_at', 'pengingat_h7_at', 'pengingat_h1_at']));
        }
        if (Schema::hasColumn('sekolah', 'tanpa_batas')) {
            Schema::table('sekolah', fn (Blueprint $t) => $t->dropColumn('tanpa_batas'));
        }
    }
};
