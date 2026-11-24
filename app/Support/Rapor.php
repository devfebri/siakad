<?php

namespace App\Support;

use App\Absensi;
use App\Nilai;
use App\Semester;
use App\Siswa;
use App\TahunAjaran;

/** Menyusun rapor satu siswa untuk satu tahun ajaran dan semester. */
class Rapor
{
    /**
     * @return array{
     *   baris: array<int, array{mapel: string, jenis: array<string, ?float>, akhir: ?float, predikat: string, tuntas: ?bool}>,
     *   rata: ?float, kkm: int, kehadiran: array, tahun_ajaran: string, semester: string
     * }
     */
    public static function susun(Siswa $siswa, Semester $semester): array
    {
        $kkm = (int) $semester->kkm;

        $baris = Nilai::where('siswa_id', $siswa->id)->where('semester_id', $semester->id ?? 0)
            ->with('mapel:id,mapel_nama')->get()->groupBy('mapel_id')
            ->map(function ($kumpulan) use ($kkm) {
                $perJenis = $kumpulan->pluck('nilai', 'jenis')->all();
                $akhir = Nilai::nilaiAkhir($perJenis);

                return [
                    'mapel' => $kumpulan->first()->mapel?->mapel_nama ?? '-',
                    'jenis' => collect(array_keys(Nilai::jenis()))->mapWithKeys(fn ($j) => [$j => $perJenis[$j] ?? null])->all(),
                    'akhir' => $akhir,
                    'predikat' => Nilai::predikat($akhir),
                    'tuntas' => $akhir === null ? null : $akhir >= $kkm,
                ];
            })->sortBy('mapel')->values()->all();

        $akhirAkhir = collect($baris)->pluck('akhir')->filter(fn ($v) => $v !== null);

        return [
            'baris' => $baris,
            'rata' => $akhirAkhir->isNotEmpty() ? round($akhirAkhir->avg(), 2) : null,
            'kkm' => $kkm,
            'kehadiran' => self::kehadiran($siswa, $semester),
            'tahun_ajaran' => $semester->tahunAjaran->nama,
            'semester' => $semester->jenis,
        ];
    }

    /** Rekap kehadiran selama semester (menurut rentang tanggal semester). */
    public static function kehadiran(Siswa $siswa, Semester $semester): array
    {
        if (! $semester->exists) {
            return \App\Http\Controllers\AbsensiController::hitung(collect());
        }

        $data = Absensi::where('siswa_id', $siswa->id)->whereBetween('tanggal', [$semester->mulai->toDateString(), $semester->selesai->toDateString()])->get();

        return \App\Http\Controllers\AbsensiController::hitung($data);
    }

    /** Pilihan tahun ajaran: semua tahun ajaran sekolah ditambah yang aktif. */
    public static function pilihanTahun(): array
    {
        return TahunAjaran::query()->pluck('nama')
            ->push(Akademik::aktif()['tahun_ajaran'])->unique()->sortDesc()->values()->all();
    }
}
