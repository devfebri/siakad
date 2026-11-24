<?php

namespace App\Support;

use App\Semester;
use App\TahunAjaran;
use Illuminate\Support\Carbon;

/** Tahun ajaran dan semester aktif: dari tabel semester; bila belum ada yang aktif, dihitung dari tanggal. */
class Akademik
{
    /** @return array{tahun_ajaran: string, semester: string, label: string, semester_id: ?int, tahun_ajaran_id: ?int, kkm: int} */
    public static function aktif(?Carbon $now = null): array
    {
        $s = Semester::query()->where('aktif', true)->with('tahunAjaran')->first();
        if ($s) {
            return ['tahun_ajaran' => $s->tahunAjaran->nama, 'semester' => $s->jenis, 'label' => "TA {$s->tahunAjaran->nama} · Semester {$s->jenis}", 'semester_id' => $s->id, 'tahun_ajaran_id' => $s->tahun_ajaran_id, 'kkm' => $s->kkm];
        }

        [$tahun, $semester] = self::hitung($now);

        return ['tahun_ajaran' => $tahun, 'semester' => $semester, 'label' => "TA {$tahun} · Semester {$semester}", 'semester_id' => null, 'tahun_ajaran_id' => null, 'kkm' => 70];
    }

    /** Semester ganjil: Juli-Desember, genap: Januari-Juni. Tahun ajaran mulai Juli. */
    private static function hitung(?Carbon $now): array
    {
        $now ??= now();
        $ganjil = $now->month >= 7;
        $mulai = $ganjil ? $now->year : $now->year - 1;

        return [$mulai.'/'.($mulai + 1), $ganjil ? 'Ganjil' : 'Genap'];
    }

    /** Semester aktif sebagai model; dibuat (dan diaktifkan) dari tanggal bila sekolah belum punya. Dipakai saat menulis data. */
    public static function semesterAktif(): Semester
    {
        $aktif = Semester::query()->where('aktif', true)->with('tahunAjaran')->first();
        if ($aktif) {
            return $aktif;
        }

        [$tahun, $jenis] = self::hitung(null);
        $semester = Semester::cari($tahun, $jenis);
        if (! $semester) {
            $semester = (TahunAjaran::query()->where('nama', $tahun)->first() ?? TahunAjaran::buat($tahun))->semester()->where('jenis', $jenis)->first();
        }
        $semester->aktifkan();

        return $semester->load('tahunAjaran');
    }

    public static function sapaan(?Carbon $now = null): string
    {
        $jam = ($now ?? now())->hour;

        return match (true) {
            $jam < 11 => 'Selamat pagi',
            $jam < 15 => 'Selamat siang',
            $jam < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}
