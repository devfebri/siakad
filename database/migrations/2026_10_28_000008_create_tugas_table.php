<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tugas')) {
            Schema::create('tugas', function (Blueprint $table) {
                $table->id();
                $table->string('judul', 100);
                $table->unsignedBigInteger('mapel_id')->index();
                $table->unsignedBigInteger('guru_id')->nullable()->index();
                $table->string('waktu', 100)->nullable();
                $table->string('jenis_tugas', 200);
                $table->timestamps();
            });

            return;
        }

        // Tabel sudah ada (database lama): hanya tambahkan kolom yang belum ada.
        if (! Schema::hasColumn('tugas', 'guru_id')) {
            Schema::table('tugas', function (Blueprint $table) {
                $table->unsignedBigInteger('guru_id')->nullable()->index();
            });
        }

        // Kolom lama `pembuat` (NOT NULL tanpa default) tidak ada di model dan membuat insert gagal.
        if (Schema::hasColumn('tugas', 'pembuat')) {
            Schema::table('tugas', function (Blueprint $table) {
                $table->string('pembuat', 200)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas');
    }
};
