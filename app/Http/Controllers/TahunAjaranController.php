<?php

namespace App\Http\Controllers;

use App\BobotNilai;
use App\Kelas;
use App\Semester;
use App\Support\Akademik;
use App\Support\ProsesTahunan;
use App\Support\SekolahAktif;
use App\TahunAjaran;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Tahun ajaran, semester (aktif, tanggal, KKM), dan bobot nilai. Hanya admin sekolah. */
class TahunAjaranController extends Controller
{
    public function index(): View
    {
        Akademik::semesterAktif(); // sekolah baru langsung punya tahun ajaran dan semester aktif

        return view('tahun-ajaran.index', [
            'daftar' => TahunAjaran::with('semester')->orderByDesc('nama')->get(),
            'bobot' => BobotNilai::peta(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'regex:/^\d{4}\/\d{4}$/', SekolahAktif::unik('tahun_ajaran', 'nama')],
        ], ['nama.regex' => 'Format tahun ajaran harus seperti 2026/2027.', 'nama.unique' => 'Tahun ajaran ini sudah ada.'], ['nama' => 'tahun ajaran']);

        [$awal, $akhir] = array_map('intval', explode('/', $data['nama']));
        if ($akhir !== $awal + 1) {
            throw ValidationException::withMessages(['nama' => 'Tahun ajaran harus berurutan, mis. 2026/2027.']);
        }

        TahunAjaran::buat($data['nama']);

        return $this->selesai('Tahun ajaran '.$data['nama'].' ditambahkan beserta semester Ganjil dan Genap.', 201);
    }

    public function update(Request $request, TahunAjaran $tahunAjaran): JsonResponse
    {
        $data = $request->validate(['mulai' => ['required', 'date'], 'selesai' => ['required', 'date', 'after:mulai']], ['selesai.after' => 'Tanggal selesai harus setelah tanggal mulai.']);
        $tahunAjaran->update($data);

        return $this->selesai('Tahun ajaran diperbarui.');
    }

    public function destroy(TahunAjaran $tahunAjaran): JsonResponse
    {
        $tahunAjaran->load('semester');
        if ($tahunAjaran->semester->contains('aktif', true)) {
            return response()->json(['message' => 'Tahun ajaran yang sedang aktif tidak dapat dihapus. Aktifkan semester lain dulu.'], 409);
        }
        if ($tahunAjaran->semester->contains(fn (Semester $s) => $s->dipakai())) {
            return response()->json(['message' => 'Tahun ajaran ini sudah dipakai oleh jadwal, absensi, atau nilai sehingga tidak dapat dihapus.'], 409);
        }

        $tahunAjaran->semester()->delete();
        $tahunAjaran->delete();

        return $this->selesai('Tahun ajaran dihapus.');
    }

    public function updateSemester(Request $request, Semester $semester): JsonResponse
    {
        $data = $request->validate([
            'mulai' => ['required', 'date'], 'selesai' => ['required', 'date', 'after:mulai'], 'kkm' => ['required', 'integer', 'between:0,100'],
        ], ['selesai.after' => 'Tanggal selesai harus setelah tanggal mulai.'], ['kkm' => 'KKM']);

        $semester->update($data);

        return $this->selesai("Semester {$semester->jenis} diperbarui.");
    }

    public function aktifkan(Semester $semester): JsonResponse
    {
        // pergantian tahun ajaran: siswa mengikuti rombel tahun baru; tahun ajaran draf hasil Proses Tahunan harus diproses lewat halamannya
        ProsesTahunan::aktifkan($semester);

        return $this->selesai("Semester {$semester->jenis} TA {$semester->tahunAjaran->nama} sekarang aktif.");
    }

    public function updateBobot(Request $request): JsonResponse
    {
        $jenis = array_keys(BobotNilai::peta());
        $data = $request->validate(
            collect($jenis)->mapWithKeys(fn ($j) => ["bobot.$j" => ['required', 'integer', 'between:0,100']])->all() + ['bobot' => ['required', 'array']],
            [], ['bobot.*' => 'bobot'],
        );

        if (array_sum($data['bobot']) !== 100) {
            throw ValidationException::withMessages(['bobot.'.$jenis[0] => 'Total bobot harus 100 (sekarang '.array_sum($data['bobot']).').']);
        }

        foreach ($data['bobot'] as $kode => $nilai) {
            BobotNilai::where('jenis', $kode)->update(['bobot' => $nilai]);
        }

        return $this->selesai('Bobot nilai disimpan.');
    }

    private function selesai(string $pesan, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $pesan, 'redirect' => route('admin/tahun-ajaran')], $status);
    }
}
