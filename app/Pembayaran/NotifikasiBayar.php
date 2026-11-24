<?php

namespace App\Pembayaran;

/** Callback gerbang yang sudah diverifikasi dan diseragamkan. status: berhasil | menunggu | gagal | kedaluwarsa. */
class NotifikasiBayar
{
    public const STATUS = ['berhasil', 'menunggu', 'gagal', 'kedaluwarsa'];

    /**
     * @param string $kunci kunci unik kejadian (untuk membuang callback ganda), mis. "idtransaksi|status"
     * @param array<string, mixed> $mentah isi asli callback (tanpa rahasia) untuk catatan
     */
    public function __construct(
        public string $driver,
        public string $orderId,
        public string $status,
        public int $jumlah,
        public string $kunci,
        public ?string $metode = null,
        public array $mentah = [],
    ) {
    }
}
