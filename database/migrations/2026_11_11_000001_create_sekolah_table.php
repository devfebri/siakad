<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sekolah')) {
            return;
        }

        // Sekolah penyewa (tenant). Tabel ini sendiri tidak terikat sekolah_id.
        Schema::create('sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->string('npsn', 20)->nullable();
            $table->string('jenjang', 20)->default('SMA');
            $table->string('subdomain', 60)->unique();
            $table->string('domain_kustom', 150)->nullable()->unique();
            $table->string('status', 15)->default('aktif')->index(); // aktif | nonaktif
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sekolah');
    }
};
