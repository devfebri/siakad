<?php

namespace App\Support;

use App\ChatSession;
use App\Mail\ChatMail;
use App\User;
use Illuminate\Support\Facades\Mail;

/**
 * Pengiriman email konsultasi online. Semua lewat antrian; kegagalan email tidak boleh mengganggu chat,
 * jadi setiap pengiriman dibungkus try/catch (error dicatat ke log).
 */
class ChatNotifier
{
    /** Jeda minimal antar email balasan ke pengunjung yang sama. */
    public const JEDA_BALASAN_MENIT = 5;

    public static function dimulai(ChatSession $sesi): void
    {
        self::kirim($sesi->email, new ChatMail($sesi, 'dimulai'));
    }

    /** Balasan petugas saat pengunjung tidak lagi memolling (offline) atau memilih dibalas lewat email. */
    public static function balasan(ChatSession $sesi, string $isi, ?string $petugas): void
    {
        $offline = $sesi->balas_email || ! $sesi->last_visitor_at || $sesi->last_visitor_at->lt(now()->subMinutes(ChatQueue::BATAS_AKTIF_MENIT));
        $terlaluSering = $sesi->email_balasan_at && $sesi->email_balasan_at->gt(now()->subMinutes(self::JEDA_BALASAN_MENIT));
        if (! $offline || $terlaluSering) {
            return;
        }

        $sesi->forceFill(['email_balasan_at' => now()])->saveQuietly();
        self::kirim($sesi->email, new ChatMail($sesi, 'balasan', ['isi' => $isi, 'petugas' => $petugas]));
    }

    /** Pesan pertama pengunjung saat tidak ada petugas online: beri tahu semua admin lewat email (sekali per sesi). */
    public static function pesanMasuk(ChatSession $sesi, string $isi): void
    {
        if ($sesi->email_admin_at || ChatQueue::onlineAgents()->isNotEmpty()) {
            return;
        }

        $sesi->forceFill(['email_admin_at' => now()])->saveQuietly();
        $tujuan = User::denganPeran('admin')->whereNotNull('email')->pluck('email')->unique()->all();
        foreach ($tujuan as $email) {
            self::kirim($email, new ChatMail($sesi, 'masuk', ['isi' => $isi]));
        }
    }

    /** Pertanyaan yang tidak terjawab bot: konfirmasi ke pengunjung dan beri tahu semua admin. */
    public static function diteruskan(ChatSession $sesi, string $isi): void
    {
        self::kirim($sesi->email, new ChatMail($sesi, 'diteruskan', ['isi' => $isi]));

        if ($sesi->email_admin_at) {
            return;
        }
        $sesi->forceFill(['email_admin_at' => now()])->saveQuietly();
        foreach (User::denganPeran('admin')->whereNotNull('email')->pluck('email')->unique() as $email) {
            self::kirim($email, new ChatMail($sesi, 'masuk', ['isi' => $isi]));
        }
    }

    /** Transkrip setelah sesi selesai (hanya bila pengunjung pernah menulis). */
    public static function selesai(ChatSession $sesi): void
    {
        if ($sesi->messages()->where('sender', 'visitor')->exists()) {
            self::kirim($sesi->email, new ChatMail($sesi, 'transkrip'));
        }
    }

    private static function kirim(string $tujuan, ChatMail $mail): void
    {
        try {
            Mail::to($tujuan)->queue($mail);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
