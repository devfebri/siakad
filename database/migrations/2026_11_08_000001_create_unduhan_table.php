<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unduhan')) {
            return;
        }

        // Berkas yang bisa diunduh pengunjung di menu "Unduhan" situs publik (formulir, panduan, brosur, dll).
        Schema::create('unduhan', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 150);
            $table->string('kategori', 60)->default('Umum')->index();
            $table->text('deskripsi')->nullable();
            $table->string('berkas', 120);      // nama berkas di public/storage/unduhan
            $table->string('nama_asli', 150);   // nama saat diunduh
            $table->string('mime', 100);
            $table->unsignedInteger('ukuran');  // byte
            $table->unsignedInteger('jumlah_unduh')->default(0);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unduhan');
    }
};
