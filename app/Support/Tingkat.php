<?php

namespace App\Support;

use App\Sekolah;

/** Tingkat kelas sebagai angka (1–12): dari kolom tingkat rombel, atau dari awal nama rombel ("VII A", "10 IPA 1", "X-2"). */
class Tingkat
{
    private const ROMAWI = ['XII' => 12, 'XI' => 11, 'X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7, 'VI' => 6, 'V' => 5, 'IV' => 4, 'III' => 3, 'II' => 2, 'I' => 1];

    public static function angka(?string $tingkat, ?string $namaRombel = null): ?int
    {
        foreach ([$tingkat, $namaRombel] as $teks) {
            $teks = strtoupper(trim((string) $teks));
            if ($teks === '') {
                continue;
            }
            if (preg_match('/^(\d{1,2})(?![0-9])/', $teks, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
                return (int) $m[1];
            }
            foreach (self::ROMAWI as $r => $n) {
                if (preg_match('/^'.$r.'(?![A-Z])/', $teks)) {
                    return $n;
                }
            }
        }

        return null;
    }

    /** Tingkat paling akhir menurut jenjang sekolah (kelulusan terjadi setelahnya). */
    public static function akhir(?Sekolah $sekolah = null): int
    {
        $sekolah ??= SekolahAktif::instance()->get();

        return match ($sekolah?->jenjang) {
            'SD', 'MI' => 6,
            'SMP', 'MTs' => 9,
            default => 12,
        };
    }

    /** Sisa nama rombel setelah tingkat ("VII A" → "A"), dipakai mencocokkan paralel yang sama saat naik. */
    public static function paralel(string $namaRombel): string
    {
        $nama = trim($namaRombel);
        $sisa = preg_match('/^\d{1,2}/', $nama)
            ? preg_replace('/^\d{1,2}[\s\-_.]*/', '', $nama)
            : preg_replace('/^(XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)(?![A-Za-z0-9])[\s\-_.]*/i', '', $nama);

        return strtoupper(trim($sisa));
    }
}
