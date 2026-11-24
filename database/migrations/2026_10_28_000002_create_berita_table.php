<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('berita')) {
            Schema::create('berita', function (Blueprint $table) {
                $table->id();
                $table->string('berita_judul', 100);
                $table->text('berita_isi');
                $table->timestamp('berita_tanggal')->useCurrent();
                $table->unsignedBigInteger('kategori_id')->index();
                $table->unsignedInteger('berita_views')->default(0);
                $table->string('berita_gambar', 100)->nullable();
                $table->string('berita_img_slider', 200)->nullable();
                $table->string('author', 200);
                $table->string('berita_slug', 200)->index();
                $table->timestamps();
            });

            return;
        }

        // Tabel sudah ada (database lama): hanya tambahkan kolom yang belum ada.
        if (! Schema::hasColumn('berita', 'berita_img_slider')) {
            Schema::table('berita', function (Blueprint $table) {
                $table->string('berita_img_slider', 200)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('berita');
    }
};
