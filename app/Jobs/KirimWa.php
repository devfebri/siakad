<?php

namespace App\Jobs;

use App\Sekolah;
use App\Support\AksesSekolah;
use App\Wa\Wa;
use App\PengaturanWa;
use App\WaLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Kirim satu pesan WhatsApp. Dibatasi lajunya per sekolah (batas per menit dari pengaturan; sisanya menunggu giliran),
 * gangguan sementara dicoba lagi sampai 3 kali, kesalahan permanen (nomor/kunci salah) langsung ditandai gagal.
 * Pesan yang sudah terkirim atau gagal tidak diproses lagi.
 */
class KirimWa implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 0; // dibatasi waktu (retryUntil) dan percobaan di handle(), bukan jumlah release akibat batas laju

    public const MAKS_PERCOBAAN = 3;
    public const JEDA = [60, 300, 900];

    public function __construct(public int $logId, public int $sekolahId, public int $batasPerMenit = 20)
    {
    }

    public function middleware(): array
    {
        return [(new RateLimited('wa'))->releaseAfter(30)];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function handle(): void
    {
        $log = WaLog::withoutGlobalScopes()->find($this->logId);
        if (! $log || $log->status !== 'antri') {
            return;
        }

        $pg = PengaturanWa::withoutGlobalScopes()->where('sekolah_id', $log->sekolah_id)->first();
        $sekolah = Sekolah::find($log->sekolah_id);
        $pengirim = $pg?->aktif && $sekolah?->isAktif() && AksesSekolah::modulUntuk($sekolah, Wa::MODUL) ? $pg->pengirim() : null;
        if (! $pengirim) {
            $log->update(['status' => 'dilewati', 'galat' => 'WhatsApp dimatikan atau belum diatur saat pesan akan dikirim.']);

            return;
        }

        $percobaan = $log->percobaan + 1; // hanya percobaan kirim sungguhan (bukan penundaan akibat batas laju)
        $hasil = $pengirim->kirim($log->nomor, $log->pesan);

        if ($hasil->ok) {
            $log->update(['status' => 'terkirim', 'percobaan' => $percobaan, 'galat' => null, 'id_pesan' => $hasil->idPesan, 'terkirim_pada' => now()]);

            return;
        }

        if ($hasil->ulang && $percobaan < self::MAKS_PERCOBAAN) {
            $log->update(['percobaan' => $percobaan, 'galat' => $hasil->galat]);
            $this->release(self::JEDA[$percobaan - 1] ?? 900);

            return;
        }

        $log->update(['status' => 'gagal', 'percobaan' => $percobaan, 'galat' => $hasil->galat]);
    }
}
