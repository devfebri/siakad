<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('testimoni') && ! Schema::hasColumn('testimoni', 'foto')) {
            Schema::table('testimoni', function (Blueprint $table) {
                $table->string('foto', 100)->nullable()->after('peran');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('testimoni', 'foto')) {
            Schema::table('testimoni', fn (Blueprint $table) => $table->dropColumn('foto'));
        }
    }
};
