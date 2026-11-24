<?php

namespace App\Wa;

/**
 * Kontrak pengirim WhatsApp. Driver: Fonnte, Wablas, WhatsApp Cloud API, dan Fake (lokal/uji).
 * Semua panggilan keluar lewat Http client Laravel sehingga bisa di-fake di tes; tidak pernah melempar exception ke pemanggil.
 */
interface WhatsAppSender
{
    public function kode(): string;

    /** Kirim pesan teks ke nomor berformat 62xxxxxxxxxx. */
    public function kirim(string $nomor, string $pesan): HasilKirim;
}
