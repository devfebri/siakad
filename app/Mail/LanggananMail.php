<?php

namespace App\Mail;

use App\Langganan;
use App\Support\SekolahAktif;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Pengingat langganan akan berakhir (H-30, H-7, H-1) ke admin sekolah. Memakai identitas sekolah pemilik langganan. */
class LanggananMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Langganan $langganan, public int $sisaHari)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $nama = SekolahAktif::jalankan($this->langganan->sekolah_id, fn () => config('school.short_name'));

        return new Envelope(subject: match (true) {
            $this->sisaHari <= 1 => "Langganan {$nama} berakhir besok",
            default => "Langganan {$nama} berakhir {$this->sisaHari} hari lagi",
        });
    }

    public function content(): Content
    {
        return SekolahAktif::jalankan($this->langganan->sekolah_id, fn () => new Content(view: 'emails.langganan', with: [
            'sekolah' => config('school.name'),
            'warna' => \App\Support\Tema::warna(),
            'logo' => config('school.logo') ? asset(config('school.logo')) : null,
            'paket' => $this->langganan->paket?->nama,
            'berakhir' => $this->langganan->berakhir,
            'sisaHari' => $this->sisaHari,
            'tenggangHari' => \App\Sekolah::TENGGANG_HARI,
            'tautan' => $this->langganan->sekolah?->url('/admin/langganan'),
        ]));
    }
}
