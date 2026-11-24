<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pengaturan situs (key-value), program unggulan, dan testimoni untuk halaman depan. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 100)->unique();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('program_unggulan')) {
            Schema::create('program_unggulan', function (Blueprint $table) {
                $table->id();
                $table->string('judul', 100);
                $table->string('deskripsi', 300)->nullable();
                $table->string('ikon', 40)->default('book');
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('testimoni')) {
            Schema::create('testimoni', function (Blueprint $table) {
                $table->id();
                $table->string('nama', 100);
                $table->string('peran', 120)->nullable();
                $table->text('isi');
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('testimoni');
        Schema::dropIfExists('program_unggulan');
        Schema::dropIfExists('site_settings');
    }
};
