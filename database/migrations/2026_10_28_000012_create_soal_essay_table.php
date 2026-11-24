<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('soal_essay')) {
            Schema::create('soal_essay', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tugas_id')->index();
                $table->text('soal');
                $table->string('gambar')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_essay');
    }
};
