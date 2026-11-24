<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materi')) {
            Schema::create('materi', function (Blueprint $table) {
                $table->id();
                $table->string('materi', 100);
                $table->unsignedBigInteger('mapel_id')->index();
                $table->string('deskripsi', 100)->default('-');
                $table->unsignedBigInteger('kelas_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('file_materi', 200)->nullable();
                $table->string('link_materi', 100)->nullable();
                $table->timestamps();
            });

            return;
        }

        // Tabel sudah ada (database lama): hanya tambahkan kolom yang belum ada.
        if (! Schema::hasColumn('materi', 'user_id')) {
            Schema::table('materi', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('materi');
    }
};
