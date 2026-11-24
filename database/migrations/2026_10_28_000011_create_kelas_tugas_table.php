<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kelas_tugas')) {
            Schema::create('kelas_tugas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kelas_id')->index();
                $table->unsignedBigInteger('tugas_id')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_tugas');
    }
};
