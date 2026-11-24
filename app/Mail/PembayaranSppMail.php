<?php

namespace App\Mail;

use App\PembayaranSpp;
use App\Support\SekolahAktif;
use App\TransaksiSpp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Pemberitahuan ke bendahara: pembayaran SPP online diterima. Memakai identitas sekolah pemilik transaksi. */
class PembayaranSppMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TransaksiSpp $transaksi, public array $pembayaranIds)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pembayaran SPP online diterima: Rp '.number_format($this->transaksi->subtotal, 0, ',', '.'));
    }

    public function content(): Content
    {
        return SekolahAktif::jalankan($this->transaksi->sekolah_id, function () {
            $pembayaran = PembayaranSpp::whereIn('id', $this->pembayaranIds)->with('tagihan.siswa:id,nama_siswa')->get();

            return new Content(view: 'emails.spp-diterima', with: [
                'sekolah' => config('school.name'),
                'warna' => \App\Support\Tema::warna(),
                'transaksi' => $this->transaksi,
                'pembayaran' => $pembayaran,
                'tautan' => $this->transaksi->sekolah?->url('/bendahara/keuangan/pembayaran'),
            ]);
        });
    }
}
