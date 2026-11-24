<?php

namespace App\Support;

use App\ChatFaq;
use App\ChatQuickReply;
use App\Sekolah;

/**
 * Data bawaan Konsultasi Online untuk sebuah sekolah: 5 FAQ chatbot dan 5 template balasan cepat.
 * Dipanggil saat sekolah baru dibuat (seeder, panel platform). Aman dipanggil berulang: tidak menimpa yang sudah ada.
 */
class ChatDefault
{
    public const FAQ = [
        ['Jam & alamat sekolah', 'jam, buka, tutup, alamat, lokasi, dimana, di mana, kantor, layanan', "{nama_sekolah}\nAlamat: {alamat}\nJam layanan: {jam_layanan}\nTelepon: {telepon}"],
        ['Cara mendaftar PPDB', 'ppdb, daftar, pendaftaran, penerimaan, siswa baru, syarat, jalur, zonasi', "Pendaftaran siswa baru dilakukan online. Syarat, jalur, dan jadwal lengkap ada di halaman PPDB:\n{ppdb_url}"],
        ['Surat & administrasi', 'surat, legalisir, ijazah, keterangan, administrasi, mutasi, pindah, rapor, transkrip', 'Untuk surat keterangan, legalisir ijazah, atau mutasi, silakan datang ke ruang tata usaha pada jam layanan ({jam_layanan}) dengan membawa berkas pendukung. Informasi lain bisa ditanyakan ke petugas.'],
        ['Jadwal kegiatan', 'jadwal, kegiatan, agenda, acara, kalender, ujian, libur', "Agenda dan kegiatan sekolah terbaru ada di halaman Agenda:\n{agenda_url}"],
        ['Menghubungi petugas', 'petugas, admin, bantuan, bicara, kontak, telepon, whatsapp, hubungi, email', 'Anda bisa menghubungi kami lewat telepon {telepon}, WhatsApp {whatsapp}, atau email {email}. Kontak lengkap: {kontak_url}'],
    ];

    public const BALASAN_CEPAT = [
        ['Salam', 'Halo, terima kasih telah menghubungi kami. Ada yang bisa kami bantu?'],
        ['Mohon menunggu', 'Baik, mohon ditunggu sebentar, kami cek dulu informasinya.'],
        ['Jam layanan', 'Layanan kami buka Senin–Jumat, pukul 07.30–15.30 WIB.'],
        ['Info PPDB', 'Informasi PPDB lengkap (jadwal, jalur, dan persyaratan) ada di halaman PPDB pada menu utama website.'],
        ['Penutup', 'Apakah masih ada yang ingin ditanyakan? Jika tidak, percakapan akan kami akhiri. Terima kasih.'],
    ];

    public static function pasang(Sekolah $sekolah): void
    {
        SekolahAktif::jalankan($sekolah, function () {
            if (! ChatFaq::exists()) {
                foreach (self::FAQ as $i => [$tanya, $kunci, $jawab]) {
                    ChatFaq::create(['pertanyaan' => $tanya, 'kata_kunci' => $kunci, 'jawaban' => $jawab, 'urutan' => $i + 1, 'aktif' => true]);
                }
            }
            if (! ChatQuickReply::exists()) {
                foreach (self::BALASAN_CEPAT as $i => [$judul, $isi]) {
                    ChatQuickReply::create(['judul' => $judul, 'isi' => $isi, 'urutan' => $i + 1, 'aktif' => true]);
                }
            }
        });
    }
}
