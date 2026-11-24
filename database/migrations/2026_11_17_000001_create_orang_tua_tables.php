<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Orang tua/wali murid dan keterkaitannya dengan siswa (satu orang tua boleh punya banyak anak, satu siswa banyak wali). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orang_tua', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('nama', 100);
            $table->string('no_hp', 30)->nullable();
            $table->string('pekerjaan', 100)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('orang_tua_siswa', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->unsignedBigInteger('orang_tua_id');
            $table->unsignedBigInteger('siswa_id')->index();
            $table->string('hubungan', 10)->default('wali'); // ayah | ibu | wali
            $table->timestamps();

            $table->unique(['orang_tua_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orang_tua_siswa');
        Schema::dropIfExists('orang_tua');
    }
};
