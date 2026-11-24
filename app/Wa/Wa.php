<?php

namespace App\Wa;

use App\Jobs\KirimWa;
use App\PengaturanWa;
use App\Sekolah;
use App\Support\AksesSekolah;
use App\Support\SekolahAktif;
use App\WaLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Pintu masuk notifikasi WhatsApp. Semua kejadian memanggil Wa::kabari(): pesan hanya dibuat bila sekolah
 * menyalakan WhatsApp, paketnya mencakup modul, dan kejadian itu dihidupkan. Kejadian yang sama (kejadian + referensi + nomor)
 * tidak pernah dibuat dua kali, sehingga memicu ulang tidak mengirim ulang.
 */
class Wa
{
    public const MODUL = 'notifikasi_wa';

    /** Pengaturan sekolah bila WhatsApp menyala, selain itu null (hasil disimpan sebentar agar kejadian massal tidak membebani database). */
    public static function pengaturanAktif(int $sekolahId): ?PengaturanWa
    {
        $ada = Cache::remember(PengaturanWa::cacheKey($sekolahId), 120, fn () => PengaturanWa::withoutGlobalScopes()->where('sekolah_id', $sekolahId)->where('aktif', true)->exists());

        return $ada ? PengaturanWa::withoutGlobalScopes()->where('sekolah_id', $sekolahId)->first() : null;
    }

    /** Ganti placeholder {nama} dengan nilainya; placeholder tak dikenal dibiarkan. */
    public static function render(string $templat, array $data): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $data) ? (string) $data[$m[1]] : $m[0], $templat);
    }

    /**
     * Buat dan antrikan pesan. Mengembalikan log pesan, atau null bila tidak dikirim (WhatsApp mati, kejadian mati, atau sudah pernah).
     *
     * @param array<string, scalar|null> $data nilai placeholder
     */
    public static function kabari(string $kejadian, string $referensi, ?string $nomorMentah, array $data, ?int $sekolahId = null): ?WaLog
    {
        $sekolahId ??= SekolahAktif::instance()->id();
        if (! $sekolahId || ! config("wa-templat.$kejadian") || ! ($pg = self::pengaturanAktif($sekolahId)) || ! $pg->kejadianAktif($kejadian)) {
            return null;
        }
        if (! ($sekolah = Sekolah::find($sekolahId)) || ! $sekolah->isAktif() || ! AksesSekolah::modulUntuk($sekolah, self::MODUL)) {
            return null;
        }

        $nomor = NomorWa::normalkan($nomorMentah);
        if (! $nomor) {
            return null; // tidak ada nomor sah: tidak ada yang bisa dikirimi
        }

        $pesan = self::render($pg->templat($kejadian), $data + ['nama_sekolah' => $sekolah->nama]);

        try {
            $log = WaLog::withoutGlobalScopes()->create([
                'sekolah_id' => $sekolahId, 'kejadian' => $kejadian, 'referensi' => mb_substr($referensi, 0, 120), 'nomor' => $nomor, 'pesan' => $pesan, 'status' => 'antri', 'driver' => $pg->driver,
            ]);
        } catch (QueryException $e) {
            if (self::bentrokUnik($e)) {
                return null; // kejadian yang sama sudah pernah dikabarkan
            }
            throw $e;
        }

        KirimWa::dispatch($log->id, $sekolahId, (int) $pg->batas_per_menit);

        return $log;
    }

    private static function bentrokUnik(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['23000', '23505', '19'], true) || str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
