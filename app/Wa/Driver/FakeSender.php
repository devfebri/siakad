<?php

namespace App\Wa\Driver;

use App\Wa\HasilKirim;
use App\Wa\WhatsAppSender;

/** Pengirim palsu untuk lokal dan tes: tidak menghubungi layanan apa pun, hanya mengingat pesan yang "terkirim". */
class FakeSender implements WhatsAppSender
{
    /** @var array<int, array{nomor: string, pesan: string}> */
    public static array $terkirim = [];

    /** Nomor yang selalu gagal (untuk menguji penanganan galat). */
    public static array $nomorGagal = [];

    public function kode(): string
    {
        return 'fake';
    }

    public function kirim(string $nomor, string $pesan): HasilKirim
    {
        if (in_array($nomor, self::$nomorGagal, true)) {
            return HasilKirim::gagal('Nomor tidak terdaftar di WhatsApp (simulasi).');
        }

        self::$terkirim[] = ['nomor' => $nomor, 'pesan' => $pesan];

        return HasilKirim::berhasil('FAKE-'.count(self::$terkirim));
    }

    public static function reset(): void
    {
        self::$terkirim = [];
        self::$nomorGagal = [];
    }
}
