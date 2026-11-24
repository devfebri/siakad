<?php

namespace App\Pembayaran;

/** Data yang dikirim ke gerbang untuk membuat tagihan. */
class PermintaanBayar
{
    public function __construct(
        public string $orderId,
        public int $jumlah,
        public string $deskripsi,
        public string $namaPelanggan,
        public ?string $emailPelanggan,
        public string $urlKembali,
        public int $kedaluwarsaMenit = 1440,
    ) {
    }
}
