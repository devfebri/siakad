<?php

namespace App\Events;

use App\TransaksiSpp;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pembayaran SPP online berhasil dan tagihannya sudah dilunasi/dicicil.
 * Dipakai untuk memberi tahu bendahara (email) dan, nanti, notifikasi WhatsApp.
 *
 * @param array<int, int> $pembayaranIds id PembayaranSpp (kuitansi) yang dibuat oleh transaksi ini
 */
class PembayaranSppDiterima
{
    use Dispatchable;

    public function __construct(public TransaksiSpp $transaksi, public array $pembayaranIds)
    {
    }
}
