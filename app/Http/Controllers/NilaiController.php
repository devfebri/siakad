<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Kelas;
use App\Mapel;
use App\Nilai;
use App\Siswa;
use App\Semester;
use App\Support\Akademik;
use App\TahunAjaran;
use App\Support\Rapor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Input nilai per kelas/mapel/jenis dan rapor siswa. Guru hanya menilai mata pelajarannya sendiri. */
class NilaiController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $mapel = Mapel::orderBy('mapel_nama')->get(['id', 'mapel_nama']);
        if ($user->panel === 'guru') {
            $mapel = $mapel->where('id', $user->guru?->mapel_id)->values();
        }

        return view('nilai.index', [
            'kelas' => Kelas::tahunAktif()->orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            'mapel' => $mapel,
            'jenis' => Nilai::jenis(),
            'akademik' => Akademik::aktif(),
        ]);
    }

    /** Siswa satu kelas beserta nilai yang sudah ada untuk mapel dan jenis tertentu (periode aktif). */
    public function siswa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')],
            'mapel_id' => ['required', 'integer', SekolahAktif::ada('mapel')],
            'jenis' => ['required', Rule::in(array_keys(Nilai::jenis()))],
        ]);
        $this->pastikanBolehMenilai((int) $data['mapel_id']);

        $periode = Akademik::aktif();
        $ada = Nilai::where('mapel_id', $data['mapel_id'])->where('jenis', $data['jenis'])
            ->where('semester_id', $periode['semester_id'] ?? 0)
            ->whereIn('siswa_id', Siswa::where('kelas_id', $data['kelas_id'])->pluck('id'))->pluck('nilai', 'siswa_id');

        return response()->json(['siswa' => Siswa::where('kelas_id', $data['kelas_id'])->orderBy('nama_siswa')->get(['id', 'nisn', 'nama_siswa'])
            ->map(fn (Siswa $s) => ['id' => $s->id, 'nisn' => $s->nisn, 'nama' => $s->nama_siswa, 'nilai' => $ada[$s->id] ?? null])]);
    }

    /** Simpan nilai satu kelas sekaligus. Nilai kosong menghapus nilai yang ada. */
    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')],
            'mapel_id' => ['required', 'integer', SekolahAktif::ada('mapel')],
            'jenis' => ['required', Rule::in(array_keys(Nilai::jenis()))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.siswa_id' => ['required', 'integer', 'distinct'],
            'items.*.nilai' => ['nullable', 'numeric', 'between:0,100'],
        ], ['items.*.nilai.between' => 'Nilai harus antara 0 dan 100.'], ['items.*.nilai' => 'nilai']);
        $this->pastikanBolehMenilai((int) $data['mapel_id']);

        $semesterId = Akademik::semesterAktif()->id;
        $valid = Siswa::where('kelas_id', $data['kelas_id'])->pluck('id')->flip();
        $tersimpan = 0;
        $dihapus = 0;

        DB::transaction(function () use ($data, $semesterId, $valid, &$tersimpan, &$dihapus) {
            foreach ($data['items'] as $i) {
                if (! $valid->has((int) $i['siswa_id'])) {
                    continue;
                }

                $kunci = ['siswa_id' => $i['siswa_id'], 'mapel_id' => $data['mapel_id'], 'jenis' => $data['jenis'], 'semester_id' => $semesterId];

                if ($i['nilai'] === null || $i['nilai'] === '') {
                    $dihapus += Nilai::where($kunci)->delete();

                    continue;
                }

                Nilai::updateOrCreate($kunci, ['kelas_id' => $data['kelas_id'], 'nilai' => $i['nilai'], 'dicatat_oleh' => auth()->id()]);
                $tersimpan++;
            }
        });

        return response()->json(['message' => "{$tersimpan} nilai disimpan".($dihapus ? ", {$dihapus} dihapus" : '').'.', 'tersimpan' => $tersimpan]);
    }

    /** Daftar siswa satu kelas (untuk memilih rapor). */
    public function daftarSiswa(Request $request): JsonResponse
    {
        $kelasId = (int) $request->validate(['kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')]])['kelas_id'];

        return response()->json(['siswa' => Siswa::where('kelas_id', $kelasId)->orderBy('nama_siswa')->get(['id', 'nisn', 'nama_siswa'])
            ->map(fn (Siswa $s) => ['id' => $s->id, 'nama' => $s->nama_siswa.' · '.$s->nisn])]);
    }

    /** Rapor siswa (admin/guru memilih siswa). */
    public function rapor(Request $request, Siswa $siswa): View
    {
        return $this->tampilkanRapor($request, $siswa->loadMissing('kelas', 'user'));
    }

    /** Rapor milik siswa yang login. */
    public function raporSaya(Request $request): View
    {
        $siswa = auth()->user()->siswa;
        abort_unless($siswa, 404);

        return $this->tampilkanRapor($request, $siswa->loadMissing('kelas', 'user'));
    }

    public function tampilkanRapor(Request $request, Siswa $siswa): View
    {
        $akademik = Akademik::aktif();
        $tahun = $request->query('tahun_ajaran', $akademik['tahun_ajaran']);
        $jenis = $request->query('semester', $akademik['semester']);
        abort_unless(preg_match('/^\d{4}\/\d{4}$/', (string) $tahun) && in_array($jenis, Semester::JENIS, true), 404);

        // semester yang belum ada di tabel (mis. periode aktif otomatis) tampil sebagai rapor kosong
        $semester = Semester::cari($tahun, $jenis) ?? new Semester(['jenis' => $jenis, 'kkm' => $akademik['kkm']]);
        $semester->setRelation('tahunAjaran', $semester->tahunAjaran ?? new TahunAjaran(['nama' => $tahun]));

        return view('nilai.rapor', [
            'siswa' => $siswa,
            'rapor' => Rapor::susun($siswa, $semester),
            'pilihanTahun' => Rapor::pilihanTahun(),
            'jenis' => Nilai::jenis(),
        ]);
    }

    private function pastikanBolehMenilai(int $mapelId): void
    {
        $user = auth()->user();
        abort_unless($user->punyaPeran('admin') || ($user->guru && $user->guru->mapel_id === $mapelId), 403, 'Anda hanya dapat menilai mata pelajaran Anda sendiri.');
    }
}
