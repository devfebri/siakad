<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('soal_pilgan')) {
            Schema::create('soal_pilgan', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tugas_id')->index();
                $table->text('soal');
                $table->string('a');
                $table->string('b');
                $table->string('c');
                $table->string('d');
                $table->string('kunci', 10);
                $table->unsignedInteger('score')->default(0);
                $table->string('gambar')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_pilgan');
    }
};
