<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guru')) {
            Schema::create('guru', function (Blueprint $table) {
                $table->id();
                $table->string('nip_guru', 100);
                $table->string('nama_guru', 200);
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('mapel_id')->index();
                $table->string('jenis_kelamin', 100);
                $table->string('no_hp', 100);
                $table->dateTime('tanggal_lahir');
                $table->string('avatar', 110)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guru');
    }
};
