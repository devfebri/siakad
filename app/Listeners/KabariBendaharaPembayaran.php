<?php

namespace App\Listeners;

use App\Events\PembayaranSppDiterima;
use App\Mail\PembayaranSppMail;
use App\Peran;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Support\Facades\Mail;

/** Email ke semua bendahara sekolah (yang punya email sungguhan) saat ada pembayaran SPP online masuk. */
class KabariBendaharaPembayaran
{
    public function handle(PembayaranSppDiterima $event): void
    {
        $email = SekolahAktif::jalankan($event->transaksi->sekolah_id, fn () => User::denganPeran(Peran::BENDAHARA)->where('aktif', '!=', false)->pluck('email')->all());

        foreach (array_unique(array_filter($email, fn ($e) => $e && ! str_ends_with($e, '.invalid'))) as $alamat) {
            Mail::to($alamat)->send(new PembayaranSppMail($event->transaksi, $event->pembayaranIds));
        }
    }
}
