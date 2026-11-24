<?php

namespace App\Mail;

use App\PendaftaranSekolah;
use App\PengaturanPlatform;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email verifikasi pendaftaran sekolah mandiri: tautan sekali pakai yang berlaku 24 jam. */
class VerifikasiPendaftaranMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PendaftaranSekolah $pendaftaran, public string $tautan)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Satu langkah lagi: aktifkan sekolah Anda di '.PengaturanPlatform::get('nama_produk'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verifikasi-pendaftaran', with: [
            'produk' => PengaturanPlatform::get('nama_produk'),
            'nama' => $this->pendaftaran->data['admin']['name'],
            'sekolah' => $this->pendaftaran->data['nama'],
            'tautan' => $this->tautan,
            'jam' => \App\Support\Pendaftaran::BERLAKU_JAM,
            'warna' => \App\Support\Tema::warna(),
        ]);
    }
}
