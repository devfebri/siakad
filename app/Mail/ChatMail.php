<?php

namespace App\Mail;

use App\ChatSession;
use App\Support\SekolahAktif;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email konsultasi online bertema sekolah (logo + warna). Satu kelas untuk semua jenis:
 *   dimulai   - konfirmasi chat dimulai (ke pengunjung)
 *   balasan   - balasan petugas saat pengunjung offline (ke pengunjung)
 *   transkrip - ringkasan/transkrip setelah sesi selesai (ke pengunjung)
 *   masuk     - ada pesan masuk saat tidak ada petugas online (ke petugas)
 * Dikirim lewat antrian (ShouldQueue); dijalankan cron schedule:run -> queue:work --stop-when-empty.
 */
class ChatMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public const JENIS = ['dimulai', 'balasan', 'transkrip', 'masuk', 'diteruskan'];

    /** @param array<string, mixed> $data data tambahan (mis. isi balasan) */
    public function __construct(public ChatSession $sesi, public string $jenis, public array $data = [])
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $sekolah = SekolahAktif::jalankan($this->sesi->sekolah_id, fn () => config('school.short_name'));

        return new Envelope(subject: match ($this->jenis) {
            'dimulai' => "Konsultasi Online {$sekolah}: percakapan Anda dimulai",
            'balasan' => "Balasan baru dari petugas {$sekolah}",
            'diteruskan' => "Pertanyaan Anda kami teruskan ke petugas {$sekolah}",
            'transkrip' => "Ringkasan percakapan Anda dengan {$sekolah}",
            default => "[Konsultasi Online] Pesan baru dari {$this->sesi->nama}",
        });
    }

    public function content(): Content
    {
        // Data dihitung dalam konteks sekolah pemilik chat agar nama, logo, dan warna sekolah yang benar (bukan bawaan .env).
        return SekolahAktif::jalankan($this->sesi->sekolah_id, fn () => $this->susunKonten());
    }

    private function susunKonten(): Content
    {
        $pesan = $this->jenis === 'transkrip'
            ? $this->sesi->messages()->with('user:id,name')->whereIn('sender', ['visitor', 'agent', 'bot'])->orderBy('id')->get()
            : collect();

        return new Content(view: 'emails.chat', with: [
            'sesi' => $this->sesi,
            'jenis' => $this->jenis,
            'data' => $this->data,
            'pesan' => $pesan,
            'sekolah' => config('school.name'),
            'warna' => \App\Support\Tema::warna(),
            'logo' => config('school.logo') ? asset(config('school.logo')) : null,
            'tautanAdmin' => route('admin/chat'),
            'riwayat' => route('chat.riwayat', $this->sesi->riwayat_token),
        ]);
    }
}
