<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_quick_replies')) {
            Schema::create('chat_quick_replies', function (Blueprint $table) {
                $table->id();
                $table->string('judul', 60);
                $table->text('isi');
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        // Template balasan cepat bawaan (dapat diubah admin di Konsultasi Online > Pengaturan).
        if (DB::table('chat_quick_replies')->count() === 0) {
            $now = now();
            $data = [
                ['Salam', 'Halo, terima kasih telah menghubungi kami. Ada yang bisa kami bantu?'],
                ['Mohon menunggu', 'Baik, mohon ditunggu sebentar, kami cek dulu informasinya.'],
                ['Jam layanan', 'Layanan kami buka Senin–Jumat, pukul 07.30–15.30 WIB.'],
                ['Info PPDB', 'Informasi PPDB lengkap (jadwal, jalur, dan persyaratan) ada di halaman PPDB pada menu utama website.'],
                ['Penutup', 'Apakah masih ada yang ingin ditanyakan? Jika tidak, percakapan akan kami akhiri. Terima kasih.'],
            ];
            foreach ($data as $i => [$judul, $isi]) {
                DB::table('chat_quick_replies')->insert(['judul' => $judul, 'isi' => $isi, 'urutan' => $i + 1, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_quick_replies');
    }
};
