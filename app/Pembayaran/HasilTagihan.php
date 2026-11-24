<?php

namespace App\Pembayaran;

use Illuminate\Support\Carbon;

/** Hasil pembuatan tagihan: alamat halaman bayar dan nomor rujukan di gerbang. */
class HasilTagihan
{
    public function __construct(public string $url, public ?string $referensi = null, public ?Carbon $kedaluwarsa = null)
    {
    }
}
