<?php

namespace App\Support;

use App\ChatSetting;
use Illuminate\Support\Carbon;

/**
 * Jam layanan konsultasi online: hari kerja, jam buka/tutup, dan hari libur (diatur admin di
 * Konsultasi Online > Pengaturan). Waktu mengikuti zona waktu layanan (WIB bawaan, dapat diganti), bukan zona waktu server.
 */
class ChatHours
{
    public const DEFAULT_BUKA = '08:00';
    public const DEFAULT_TUTUP = '16:00';
    public const DEFAULT_HARI = [1, 2, 3, 4, 5]; // Senin–Jumat (ISO: 1 = Senin ... 7 = Minggu)
    /** Zona waktu layanan yang dapat dipilih admin (jam layanan tidak ikut zona waktu server). */
    public const ZONA = ['Asia/Jakarta' => 'WIB (Jakarta)', 'Asia/Makassar' => 'WITA (Makassar)', 'Asia/Jayapura' => 'WIT (Jayapura)'];
    public const DEFAULT_ZONA = 'Asia/Jakarta';
    public const NAMA_HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public static function zona(): string
    {
        $zona = (string) ChatSetting::get('zona', self::DEFAULT_ZONA);

        return array_key_exists($zona, self::ZONA) ? $zona : self::DEFAULT_ZONA;
    }

    /** Waktu sekarang (atau waktu tertentu) dalam zona waktu layanan. */
    public static function sekarang(?Carbon $waktu = null): Carbon
    {
        return ($waktu ?? now())->copy()->setTimezone(self::zona());
    }

    /** @return array{buka: string, tutup: string, hari: array<int,int>, libur: array<int, array{tanggal: string, keterangan: string}>} */
    public static function pengaturan(): array
    {
        $hari = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) ChatSetting::get('hari_kerja', implode(',', self::DEFAULT_HARI)))),
            fn (int $h) => $h >= 1 && $h <= 7,
        )));
        sort($hari);

        $libur = json_decode((string) ChatSetting::get('hari_libur', '[]'), true);

        return [
            'buka' => self::jam((string) ChatSetting::get('jam_buka', self::DEFAULT_BUKA), self::DEFAULT_BUKA),
            'tutup' => self::jam((string) ChatSetting::get('jam_tutup', self::DEFAULT_TUTUP), self::DEFAULT_TUTUP),
            'hari' => $hari,
            'libur' => collect(is_array($libur) ? $libur : [])
                ->filter(fn ($l) => is_array($l) && ! empty($l['tanggal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $l['tanggal']))
                ->map(fn ($l) => ['tanggal' => $l['tanggal'], 'keterangan' => (string) ($l['keterangan'] ?? '')])
                ->sortBy('tanggal')->values()->all(),
        ];
    }

    /** Apakah layanan petugas sedang buka pada waktu tertentu (bawaan: sekarang). */
    public static function buka(?Carbon $waktu = null): bool
    {
        $waktu = self::sekarang($waktu);
        $p = self::pengaturan();

        if (! in_array($waktu->dayOfWeekIso, $p['hari'], true) || self::libur($waktu) !== null) {
            return false;
        }

        $jam = $waktu->format('H:i');

        return $jam >= $p['buka'] && $jam < $p['tutup'];
    }

    /** Keterangan hari libur pada tanggal itu, atau null bila bukan hari libur. */
    public static function libur(Carbon $tanggal): ?string
    {
        $tanggal = self::sekarang($tanggal);
        foreach (self::pengaturan()['libur'] as $l) {
            if ($l['tanggal'] === $tanggal->toDateString()) {
                return $l['keterangan'] !== '' ? $l['keterangan'] : 'Hari libur';
            }
        }

        return null;
    }

    /** Teks jam layanan, mis. "Senin–Jumat, 08.00–16.00 WIB". */
    public static function label(): string
    {
        $p = self::pengaturan();
        $zona = self::sekarang()->format('T');
        $zona = preg_match('/^[A-Z]{2,5}$/', $zona) ? $zona : '';

        return trim(self::labelHari($p['hari']).', '.str_replace(':', '.', $p['buka']).'–'.str_replace(':', '.', $p['tutup']).' '.$zona);
    }

    /** @param array<int,int> $hari */
    private static function labelHari(array $hari): string
    {
        if (! $hari) {
            return 'Tidak ada hari layanan';
        }

        $berurutan = count($hari) > 2 && $hari === range($hari[0], end($hari));

        return $berurutan
            ? self::NAMA_HARI[$hari[0]].'–'.self::NAMA_HARI[end($hari)]
            : implode(', ', array_map(fn ($h) => self::NAMA_HARI[$h], $hari));
    }

    private static function jam(string $nilai, string $bawaan): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $nilai) ? $nilai : $bawaan;
    }
}
