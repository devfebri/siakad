<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pengumuman')) {
            Schema::create('pengumuman', function (Blueprint $table) {
                $table->increments('pengumuman_id');
                $table->string('pengumuman_judul', 150);
                $table->text('pengumuman_deskripsi');
                $table->timestamp('pengumuman_tanggal')->useCurrent();
                $table->string('pengumuman_author', 100);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
