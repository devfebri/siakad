<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Menyimpan pengerjaan tugas oleh siswa: waktu mulai/selesai, jawaban, dan nilai. */
return new class extends Migration
{
    private array $columns = ['mulai_at', 'selesai_at', 'nilai', 'nilai_maks', 'jawaban'];

    public function up(): void
    {
        if (! Schema::hasTable('tugas_siswa')) {
            return;
        }

        Schema::table('tugas_siswa', function (Blueprint $table) {
            if (! Schema::hasColumn('tugas_siswa', 'mulai_at')) {
                $table->timestamp('mulai_at')->nullable();
            }
            if (! Schema::hasColumn('tugas_siswa', 'selesai_at')) {
                $table->timestamp('selesai_at')->nullable();
            }
            if (! Schema::hasColumn('tugas_siswa', 'nilai')) {
                $table->unsignedInteger('nilai')->nullable();
            }
            if (! Schema::hasColumn('tugas_siswa', 'nilai_maks')) {
                $table->unsignedInteger('nilai_maks')->nullable();
            }
            if (! Schema::hasColumn('tugas_siswa', 'jawaban')) {
                $table->longText('jawaban')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tugas_siswa')) {
            Schema::table('tugas_siswa', function (Blueprint $table) {
                $table->dropColumn(array_filter($this->columns, fn ($c) => Schema::hasColumn('tugas_siswa', $c)));
            });
        }
    }
};
