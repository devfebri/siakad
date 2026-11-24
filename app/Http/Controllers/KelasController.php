<?php

namespace App\Http\Controllers;

use App\Guru;
use App\Kelas;
use App\Siswa;
use App\Support\Akademik;
use App\Support\DataTableResponse;
use App\Support\Periode;
use App\Support\SekolahAktif;
use App\TahunAjaran;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Rombongan belajar (kelas per tahun ajaran): daftar, wali kelas, dan anggota. Admin mengelola; guru/siswa melihat. */
class KelasController extends Controller
{
    public function index(): View
    {
        return view('kelas.index', [
            'tahunAjaran' => TahunAjaran::orderByDesc('nama')->get(['id', 'nama']),
            'aktifId' => Akademik::aktif()['tahun_ajaran_id'],
            'guru' => Guru::orderBy('nama_guru')->get(['id', 'nama_guru']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $taId = (int) $request->input('tahun_ajaran_id') ?: (Akademik::aktif()['tahun_ajaran_id'] ?? 0);

        return DataTableResponse::make(
            $request,
            Kelas::query()->where('tahun_ajaran_id', $taId)->with(['wali:id,nama_guru', 'tahunAjaran:id,nama'])->withCount('anggota'),
            searchable: ['nama_kelas', 'tingkat'],
            orderable: ['nama_kelas', 'anggota_count', 'created_at'],
            map: fn (Kelas $kelas) => [
                'id' => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
                'tingkat' => $kelas->tingkat,
                'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
                'tahun_ajaran' => $kelas->tahunAjaran?->nama ?? '-',
                'wali_guru_id' => $kelas->wali_guru_id,
                'wali' => $kelas->wali?->nama_guru ?? '-',
                'siswa_count' => $kelas->anggota_count,
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['tahun_ajaran_id'] ??= Akademik::semesterAktif()->tahun_ajaran_id;
        $this->pastikanValid($data);
        Periode::pastikanTerbuka((int) $data['tahun_ajaran_id']);

        Kelas::create($data);
        Kelas::sinkronkanPeranWali();

        return response()->json(['message' => 'Rombel berhasil dibuat.'], 201);
    }

    public function update(Request $request, Kelas $kelas): JsonResponse
    {
        $data = $this->validated($request, $kelas);
        Periode::pastikanTerbuka($kelas->tahun_ajaran_id);
        unset($data['tahun_ajaran_id']); // pindah tahun ajaran tidak diizinkan (anggota dan jadwal terikat)
        $this->pastikanValid($data + ['tahun_ajaran_id' => $kelas->tahun_ajaran_id], $kelas);

        $kelas->update($data);
        Kelas::sinkronkanPeranWali();

        return response()->json(['message' => 'Rombel berhasil diperbarui.']);
    }

    public function destroy(Kelas $kelas): JsonResponse
    {
        Periode::pastikanTerbuka($kelas->tahun_ajaran_id);
        $kelas->loadCount(['anggota', 'jadwal']);

        if ($kelas->anggota_count || $kelas->jadwal_count) {
            return response()->json([
                'message' => sprintf(
                    'Tidak dapat dihapus: rombel ini masih berisi %d siswa dan %d jadwal pelajaran.',
                    $kelas->anggota_count, $kelas->jadwal_count,
                ),
            ], 409);
        }

        $kelas->delete();
        Kelas::sinkronkanPeranWali();

        return response()->json(['message' => 'Rombel berhasil dihapus.']);
    }

    /** Salin struktur rombel (nama, tingkat, wali) dari satu tahun ajaran ke tahun ajaran lain, tanpa anggota. */
    public function salin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dari' => ['required', 'integer', SekolahAktif::ada('tahun_ajaran')],
            'ke' => ['required', 'integer', 'different:dari', SekolahAktif::ada('tahun_ajaran')],
        ], ['ke.different' => 'Tahun ajaran tujuan harus berbeda dari asal.'], ['dari' => 'tahun ajaran asal', 'ke' => 'tahun ajaran tujuan']);

        $sudah = Kelas::where('tahun_ajaran_id', $data['ke'])->pluck('nama_kelas')->all();
        $dibuat = 0;
        foreach (Kelas::where('tahun_ajaran_id', $data['dari'])->orderBy('nama_kelas')->get() as $k) {
            if (in_array($k->nama_kelas, $sudah, true)) {
                continue;
            }
            Kelas::create(['nama_kelas' => $k->nama_kelas, 'tingkat' => $k->tingkat, 'tahun_ajaran_id' => $data['ke'], 'wali_guru_id' => null]);
            $dibuat++;
        }

        return response()->json(['message' => "{$dibuat} rombel disalin (wali kelas dan anggota perlu diatur ulang)."]);
    }

    /** Tambah siswa ke rombel (menggantikan rombelnya di tahun ajaran yang sama). */
    public function tambahAnggota(Request $request, Kelas $kelas): JsonResponse
    {
        Periode::pastikanTerbuka($kelas->tahun_ajaran_id);
        $data = $request->validate(['siswa' => ['required', 'array', 'min:1'], 'siswa.*' => ['integer', SekolahAktif::ada('siswa')]]);

        DB::transaction(function () use ($data, $kelas) {
            foreach (Siswa::whereIn('id', $data['siswa'])->get() as $siswa) {
                $kelas->tempatkan($siswa);
            }
        });

        return response()->json(['message' => count($data['siswa']).' siswa ditempatkan di rombel '.$kelas->nama_kelas.'.', 'redirect' => url('admin/kelas/open/'.$kelas->id)]);
    }

    public function keluarkanAnggota(Kelas $kelas, Siswa $siswa): JsonResponse
    {
        Periode::pastikanTerbuka($kelas->tahun_ajaran_id);
        $kelas->keluarkan($siswa);

        return response()->json(['message' => $siswa->nama_siswa.' dikeluarkan dari rombel '.$kelas->nama_kelas.'.', 'redirect' => url('admin/kelas/open/'.$kelas->id)]);
    }

    private function validated(Request $request, ?Kelas $kelas = null): array
    {
        return $request->validate([
            'nama_kelas' => ['required', 'string', 'max:100'],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'wali_guru_id' => ['nullable', 'integer', SekolahAktif::ada('guru')],
            'tahun_ajaran_id' => ['nullable', 'integer', SekolahAktif::ada('tahun_ajaran')],
        ], [], ['nama_kelas' => 'nama rombel', 'wali_guru_id' => 'wali kelas', 'tahun_ajaran_id' => 'tahun ajaran']);
    }

    /** Nama unik per tahun ajaran; satu guru hanya menjadi wali satu rombel pada tahun ajaran yang sama. */
    private function pastikanValid(array $data, ?Kelas $kelas = null): void
    {
        $lain = fn () => Kelas::where('tahun_ajaran_id', $data['tahun_ajaran_id'])->when($kelas, fn ($q) => $q->whereKeyNot($kelas->id));

        if ($lain()->where('nama_kelas', $data['nama_kelas'])->exists()) {
            throw ValidationException::withMessages(['nama_kelas' => 'Nama rombel sudah dipakai pada tahun ajaran ini.']);
        }
        if (! empty($data['wali_guru_id']) && $lain()->where('wali_guru_id', $data['wali_guru_id'])->exists()) {
            throw ValidationException::withMessages(['wali_guru_id' => 'Guru ini sudah menjadi wali kelas di rombel lain pada tahun ajaran ini.']);
        }
    }

    /** Detail rombel untuk admin/guru. */
    public function open(int $id): View
    {
        return $this->detail(Kelas::findOrFail($id));
    }

    /** Siswa melihat kelasnya sendiri. */
    public function opensiswa(): View
    {
        $kelasId = auth()->user()->siswa?->kelas_id;
        abort_unless($kelasId, 404, 'Anda belum terdaftar di kelas mana pun.');

        return $this->detail(Kelas::findOrFail($kelasId));
    }

    private function detail(Kelas $kelas): View
    {
        $kelas->load(['wali:id,nama_guru', 'tahunAjaran:id,nama']);
        $anggota = $kelas->anggota()->orderBy('nama_siswa')->get(['siswa.id', 'nisn', 'nama_siswa', 'jenis_kelamin']);
        $bisaKelola = auth()->user()->panel === 'admin';

        return view('kelas.show', [
            'kelas' => $kelas,
            'siswa' => $anggota,
            // calon anggota: siswa yang belum punya rombel pada tahun ajaran ini
            'calon' => $bisaKelola
                ? Siswa::aktif()->whereNotIn('id', DB::table('rombel_siswa')->where('tahun_ajaran_id', $kelas->tahun_ajaran_id)->pluck('siswa_id'))->orderBy('nama_siswa')->get(['id', 'nisn', 'nama_siswa'])
                : collect(),
        ]);
    }
}
