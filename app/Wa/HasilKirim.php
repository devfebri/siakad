<?php

namespace App\Wa;

/** Hasil satu percobaan kirim. $ulang = layak dicoba lagi (gangguan jaringan/server), bukan kesalahan permanen (nomor/kunci salah). */
class HasilKirim
{
    public function __construct(public bool $ok, public ?string $idPesan = null, public ?string $galat = null, public bool $ulang = false)
    {
    }

    public static function berhasil(?string $id = null): self
    {
        return new self(true, $id);
    }

    public static function gagal(string $galat, bool $ulang = false): self
    {
        return new self(false, null, mb_substr($galat, 0, 280), $ulang);
    }
}
