<?php

namespace App\Http\Controllers;

use App\Absensi;
use App\Kelas;
use App\Support\Akademik;
use App\Support\Rapor;
use Illuminate\Contracts\View\View;

/** Wali kelas: memantau siswa di rombel yang diampunya (kehadiran bulan ini, nilai semester aktif, orang tua). */
class WaliKelasController extends Controller
{
    public function index(): View
    {
        $guru = auth()->user()->guru;
        $semester = Akademik::semesterAktif();

        $rombel = $guru
            ? Kelas::tahunAktif()->where('wali_guru_id', $guru->id)->orderBy('nama_kelas')->get()
            : collect();

        $bulanIni = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        $data = $rombel->map(function (Kelas $k) use ($semester, $bulanIni) {
            $siswa = $k->anggota()->with('orangTua:orang_tua.id,nama')->orderBy('nama_siswa')->get();
            $absen = Absensi::whereIn('siswa_id', $siswa->pluck('id'))->whereBetween('tanggal', $bulanIni)->get()->groupBy('siswa_id');

            return [
                'kelas' => $k,
                'siswa' => $siswa->map(fn ($s) => [
                    'model' => $s,
                    'kehadiran' => AbsensiController::hitung($absen->get($s->id, collect())),
                    'rata' => Rapor::susun($s, $semester)['rata'],
                    'orangTua' => $s->orangTua->pluck('nama')->implode(', '),
                ]),
            ];
        });

        return view('wali.index', ['rombel' => $data, 'semester' => $semester]);
    }
}
