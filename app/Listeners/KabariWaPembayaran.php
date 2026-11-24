<?php

namespace App\Listeners;

use App\Events\PembayaranSppDiterima;
use App\PembayaranSpp;
use App\Support\SekolahAktif;
use App\Wa\Kabar;

/** Pembayaran SPP online berhasil: satu pesan WhatsApp ke tiap orang tua (gabungan semua tagihan dalam transaksi). */
class KabariWaPembayaran
{
    public function handle(PembayaranSppDiterima $event): void
    {
        $pembayaran = SekolahAktif::jalankan($event->transaksi->sekolah_id, fn () => PembayaranSpp::whereIn('id', $event->pembayaranIds)->get());

        Kabar::pembayaran($pembayaran, 'bayar:'.$event->transaksi->order_id);
    }
}
