<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agenda')) {
            Schema::create('agenda', function (Blueprint $table) {
                $table->increments('agenda_id');
                $table->string('agenda_nama', 200);
                $table->timestamp('agenda_tanggal')->useCurrent();
                $table->text('agenda_deskripsi');
                $table->string('agenda_mulai', 100);
                $table->string('agenda_selesai', 100);
                $table->string('agenda_tempat', 100);
                $table->string('agenda_waktu', 30);
                $table->string('agenda_keterangan', 200);
                $table->string('agenda_author', 60);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda');
    }
};
