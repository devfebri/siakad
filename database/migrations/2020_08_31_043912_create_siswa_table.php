<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('siswa')) {
            Schema::create('siswa', function (Blueprint $table) {
                $table->id();
                $table->string('nisn', 100);
                $table->string('nama_siswa', 100);
                $table->string('agama', 100);
                $table->string('jenis_kelamin', 100);
                $table->date('tanggal_lahir');
                $table->string('avatar', 200)->nullable();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('kelas_id')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
