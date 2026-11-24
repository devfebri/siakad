<?php

namespace App\Http\Controllers;

use App\Kelas;
use App\LogAktivitas;
use App\Support\ProsesTahunan;
use App\UsulanKenaikan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Wali kelas mengusulkan naik/tinggal/lulus per siswa di rombelnya; admin memprosesnya di Proses Tahunan. */
class UsulanKenaikanController extends Controller
{
    public function index(): View
    {
        $aktif = ProsesTahunan::tahunAktif();
        $guru = auth()->user()->guru;
        $rombel = $guru && $aktif ? Kelas::where('tahun_ajaran_id', $aktif->id)->where('wali_guru_id', $guru->id)->orderBy('nama_kelas')->get() : collect();
        $usulan = UsulanKenaikan::where('tahun_ajaran_id', $aktif?->id)->get()->keyBy('siswa_id');

        return view('wali.usulan', [
            'aktif' => $aktif,
            'rombel' => $rombel->map(fn (Kelas $k) => [
                'kelas' => $k,
                'akhir' => ProsesTahunan::tingkatAkhir($k),
                'siswa' => $k->anggota()->where('siswa.status', 'aktif')->orderBy('nama_siswa')->get(['siswa.id', 'nisn', 'nama_siswa']),
            ]),
            'usulan' => $usulan,
        ]);
    }

    public function simpan(Request $request, Kelas $kelas): JsonResponse
    {
        $aktif = ProsesTahunan::tahunAktif();
        abort_unless($aktif && $kelas->tahun_ajaran_id === $aktif->id && $kelas->wali_guru_id === auth()->user()->guru?->id, 403, 'Anda bukan wali kelas rombel ini.');

        $akhir = ProsesTahunan::tingkatAkhir($kelas);
        $data = $request->validate([
            'usulan' => ['required', 'array'],
            'usulan.*' => ['nullable', Rule::in($akhir ? ['lulus', 'tidak_lulus', 'tinggal'] : ['naik', 'tinggal'])],
            'catatan' => ['nullable', 'array'],
            'catatan.*' => ['nullable', 'string', 'max:300'],
        ], ['usulan.*.in' => 'Usulan tidak sesuai tingkat rombel ini.']);

        $anggota = $kelas->anggota()->where('siswa.status', 'aktif')->pluck('siswa.id')->flip();
        $disimpan = 0;

        DB::transaction(function () use ($data, $kelas, $aktif, $anggota, &$disimpan) {
            foreach ($data['usulan'] as $siswaId => $nilai) {
                if (! $anggota->has((int) $siswaId)) {
                    continue;
                }
                $kunci = ['siswa_id' => $siswaId, 'tahun_ajaran_id' => $aktif->id];
                if ($nilai === null || $nilai === '') {
                    UsulanKenaikan::where($kunci)->delete();

                    continue;
                }
                UsulanKenaikan::updateOrCreate($kunci, ['rombel_id' => $kelas->id, 'usulan' => $nilai, 'catatan' => $data['catatan'][$siswaId] ?? null, 'diusulkan_oleh' => auth()->id()]);
                $disimpan++;
            }
            LogAktivitas::catat('usulan.simpan', "Usulan kenaikan rombel {$kelas->nama_kelas}: {$disimpan} siswa.", null, ['rombel_id' => $kelas->id]);
        });

        return response()->json(['message' => "{$disimpan} usulan disimpan."]);
    }
}
