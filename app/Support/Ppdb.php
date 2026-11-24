<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Status pendaftaran PPDB dihitung dari pengaturan (menu Pengaturan > PPDB). */
class Ppdb
{
    /**
     * @return array{state: string, label: string, buka: ?Carbon, tutup: ?Carbon, bisa_daftar: bool, tahun_ajaran: string}
     *         state: nonaktif | belum | buka | tutup
     */
    public static function status(?Carbon $now = null): array
    {
        $now ??= now();
        $buka = self::tanggal(config('school.ppdb_buka'));
        $tutup = self::tanggal(config('school.ppdb_tutup'));
        $tahun = config('school.ppdb_tahun_ajaran') ?: Akademik::aktif($now)['tahun_ajaran'];

        $state = match (true) {
            ! config('school.ppdb_aktif') => 'nonaktif',
            $buka && $now->lt($buka) => 'belum',
            $tutup && $now->gt($tutup) => 'tutup',
            default => 'buka',
        };

        return [
            'state' => $state,
            'label' => ['nonaktif' => 'Belum dibuka', 'belum' => 'Segera dibuka', 'buka' => 'Pendaftaran dibuka', 'tutup' => 'Pendaftaran ditutup'][$state],
            'buka' => $buka,
            'tutup' => $tutup,
            'bisa_daftar' => $state === 'buka',
            'tahun_ajaran' => $tahun,
        ];
    }

    /** Persyaratan: satu per baris dari pengaturan. */
    public static function persyaratan(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) config('school.ppdb_info')))
            ->map(fn ($l) => trim($l))->filter()->values()->all();
    }

    private static function tanggal(mixed $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
