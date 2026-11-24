<?php

namespace App\Http\Controllers;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\Semester;
use App\Setting;
use App\Siswa;
use App\Support\Akademik;
use App\TahunAjaran;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pengaturan awal sekolah baru: identitas, tahun ajaran, mata pelajaran dan rombel, lalu impor guru dan siswa. */
class SetupController extends Controller
{
    public const BATAS_BARIS = 100;

    public function index(): View
    {
        $aktif = Semester::query()->where('aktif', true)->with('tahunAjaran')->first();

        $langkah = [
            'identitas' => ['judul' => 'Identitas sekolah', 'selesai' => (bool) Setting::get('nama_sekolah')],
            'akademik' => ['judul' => 'Tahun ajaran & semester', 'selesai' => (bool) $aktif],
            'struktur' => ['judul' => 'Mata pelajaran & rombel', 'selesai' => Mapel::exists() && Kelas::tahunAktif()->exists()],
            'guru' => ['judul' => 'Data guru', 'selesai' => Guru::exists()],
            'siswa' => ['judul' => 'Data siswa', 'selesai' => Siswa::exists()],
        ];

        return view('setup.index', [
            'langkah' => $langkah,
            'tuntas' => collect($langkah)->every(fn ($l) => $l['selesai']),
            'selesai' => (bool) Setting::get('setup_selesai'),
            'aktif' => $aktif,
            'akademik' => Akademik::aktif(),
            'jumlah' => ['mapel' => Mapel::count(), 'rombel' => Kelas::tahunAktif()->count(), 'guru' => Guru::count(), 'siswa' => Siswa::count()],
        ]);
    }

    public function identitas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_sekolah' => ['required', 'string', 'max:150'],
            'nama_singkat' => ['required', 'string', 'max:40'],
            'npsn' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'telepon' => ['nullable', 'string', 'max:40'],
        ], [], ['nama_sekolah' => 'nama sekolah', 'nama_singkat' => 'nama singkat']);

        Setting::put($data);

        return $this->selesai('Identitas sekolah disimpan.');
    }

    public function akademik(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:Ganjil,Genap'],
            'kkm' => ['required', 'integer', 'between:0,100'],
        ], ['tahun_ajaran.regex' => 'Format tahun ajaran harus seperti 2026/2027.'], ['tahun_ajaran' => 'tahun ajaran', 'kkm' => 'KKM']);

        [$awal, $akhir] = array_map('intval', explode('/', $data['tahun_ajaran']));
        if ($akhir !== $awal + 1) {
            throw ValidationException::withMessages(['tahun_ajaran' => 'Tahun ajaran harus berurutan, mis. 2026/2027.']);
        }

        DB::transaction(function () use ($data) {
            $ta = TahunAjaran::where('nama', $data['tahun_ajaran'])->first() ?? TahunAjaran::buat($data['tahun_ajaran']);
            $ta->semester()->update(['kkm' => $data['kkm']]);

            $taLama = Semester::query()->where('aktif', true)->value('tahun_ajaran_id');
            $ta->semester()->where('jenis', $data['semester'])->firstOrFail()->aktifkan();
            if ($taLama !== null && $taLama !== $ta->id) {
                Kelas::sinkronkanSiswaAktif();
            }
        });

        return $this->selesai("Semester {$data['semester']} TA {$data['tahun_ajaran']} aktif.");
    }

    /** Tambah mata pelajaran dan rombel (satu nama per baris). Nama yang sudah ada dilewati. */
    public function struktur(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mapel' => ['nullable', 'string', 'max:5000'],
            'rombel' => ['nullable', 'string', 'max:5000'],
        ]);

        $daftar = fn (?string $teks, int $maks) => collect(preg_split('/\R/', (string) $teks))->map(fn ($n) => trim($n))->filter()->unique()->take(self::BATAS_BARIS)
            ->each(function ($n) use ($maks) {
                if (mb_strlen($n) > $maks) {
                    throw ValidationException::withMessages(['mapel' => "Nama terlalu panjang (maks {$maks} karakter): ".mb_substr($n, 0, 30).'…']);
                }
            });

        $mapel = $daftar($data['mapel'] ?? null, 200);
        $rombel = $daftar($data['rombel'] ?? null, 100);
        if ($mapel->isEmpty() && $rombel->isEmpty()) {
            throw ValidationException::withMessages(['mapel' => 'Isi minimal satu mata pelajaran atau rombel.']);
        }

        $baru = DB::transaction(function () use ($mapel, $rombel) {
            $m = $mapel->filter(fn ($n) => ! Mapel::where('mapel_nama', $n)->exists())->each(fn ($n) => Mapel::create(['mapel_nama' => $n]))->count();
            $taId = Akademik::semesterAktif()->tahun_ajaran_id;
            $r = $rombel->filter(fn ($n) => ! Kelas::where('tahun_ajaran_id', $taId)->where('nama_kelas', $n)->exists())->each(fn ($n) => Kelas::create(['nama_kelas' => $n, 'tahun_ajaran_id' => $taId]))->count();

            return [$m, $r];
        });

        return $this->selesai("{$baru[0]} mata pelajaran dan {$baru[1]} rombel ditambahkan.");
    }

    public function tandaiSelesai(): JsonResponse
    {
        Setting::put(['setup_selesai' => '1']);

        return response()->json(['message' => 'Pengaturan awal ditandai selesai.', 'redirect' => route('admin/dashboard')]);
    }

    private function selesai(string $pesan): JsonResponse
    {
        return response()->json(['message' => $pesan, 'redirect' => route('admin/setup')]);
    }
}
