<?php

namespace App\Http\Controllers;

use App\Absensi;
use App\Kelas;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Kepala sekolah: ringkasan dan rekap hanya-baca (dashboard memakai DashboardController). */
class KepsekController extends Controller
{
    /** Rekap kehadiran per rombel untuk satu bulan. */
    public function kehadiran(Request $request): View
    {
        $bulan = $request->validate(['bulan' => ['nullable', 'date_format:Y-m']])['bulan'] ?? now()->format('Y-m');
        $awal = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfDay();

        $rekap = Absensi::whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->selectRaw('kelas_id, status, count(*) as total')->groupBy('kelas_id', 'status')->get()->groupBy('kelas_id');

        $baris = Kelas::tahunAktif()->with('wali:id,nama_guru')->withCount('anggota')->orderBy('nama_kelas')->get()->map(function (Kelas $k) use ($rekap) {
            $hitung = AbsensiController::hitung(collect($rekap->get($k->id, collect()))->flatMap(fn ($r) => array_fill(0, (int) $r->total, (object) ['status' => $r->status])));

            return ['kelas' => $k] + $hitung;
        });

        return view('kepsek.kehadiran', ['bulan' => $bulan, 'label' => $awal->translatedFormat('F Y'), 'baris' => $baris]);
    }
}
