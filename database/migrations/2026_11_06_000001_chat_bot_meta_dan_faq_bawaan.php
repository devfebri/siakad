<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data tambahan pesan bot (tombol pilihan cepat) dalam JSON.
        if (Schema::hasTable('chat_messages') && ! Schema::hasColumn('chat_messages', 'meta')) {
            Schema::table('chat_messages', function (Blueprint $table) {
                $table->text('meta')->nullable()->after('body');
            });
        }

        // FAQ bawaan agar chatbot langsung berguna di setiap sekolah. Jawaban boleh memakai {nama_sekolah}, {alamat},
        // {telepon}, {email}, {whatsapp}, {jam_layanan}, {ppdb_url}, {agenda_url}, {kontak_url}; admin bebas mengubahnya.
        if (Schema::hasTable('chat_faqs') && DB::table('chat_faqs')->count() === 0) {
            $now = now();
            $data = [
                ['Jam & alamat sekolah', 'jam, buka, tutup, alamat, lokasi, dimana, di mana, kantor, layanan',
                    "{nama_sekolah}\nAlamat: {alamat}\nJam layanan: {jam_layanan}\nTelepon: {telepon}"],
                ['Cara mendaftar PPDB', 'ppdb, daftar, pendaftaran, penerimaan, siswa baru, syarat, jalur, zonasi',
                    "Pendaftaran siswa baru dilakukan online. Syarat, jalur, dan jadwal lengkap ada di halaman PPDB:\n{ppdb_url}"],
                ['Surat & administrasi', 'surat, legalisir, ijazah, keterangan, administrasi, mutasi, pindah, rapor, transkrip',
                    "Untuk surat keterangan, legalisir ijazah, atau mutasi, silakan datang ke ruang tata usaha pada jam layanan ({jam_layanan}) dengan membawa berkas pendukung. Informasi lain bisa ditanyakan ke petugas."],
                ['Jadwal kegiatan', 'jadwal, kegiatan, agenda, acara, kalender, ujian, libur',
                    "Agenda dan kegiatan sekolah terbaru ada di halaman Agenda:\n{agenda_url}"],
                ['Menghubungi petugas', 'petugas, admin, bantuan, bicara, kontak, telepon, whatsapp, hubungi, email',
                    "Anda bisa menghubungi kami lewat telepon {telepon}, WhatsApp {whatsapp}, atau email {email}. Kontak lengkap: {kontak_url}"],
            ];

            foreach ($data as $i => [$tanya, $kunci, $jawab]) {
                DB::table('chat_faqs')->insert([
                    'pertanyaan' => $tanya, 'kata_kunci' => $kunci, 'jawaban' => $jawab, 'urutan' => $i + 1, 'aktif' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chat_messages', 'meta')) {
            Schema::table('chat_messages', fn (Blueprint $table) => $table->dropColumn('meta'));
        }
    }
};
