<?php

namespace App\Http\Controllers;

use App\Absensi;
use App\Guru;
use App\Jadwal;
use App\Kelas;
use App\Mapel;
use App\Nilai;
use App\Pengumuman;
use App\PpdbPendaftar;
use App\Siswa;
use App\Support\Akademik;
use App\Timeline;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        return match (auth()->user()->panel) {
            'admin' => $this->admin(),
            'kepsek' => $this->kepsek(),
            'guru' => $this->guru(),
            default => $this->siswa(),
        };
    }

    private function admin(): View
    {
        // Ringkasan dashboard admin sengaja ringan: angka dasar di-cache 60 detik per sekolah.
        $sekolah = \App\Support\SekolahAktif::instance()->get()?->id ?? 0;
        $ringkas = \Illuminate\Support\Facades\Cache::remember("dash_admin_{$sekolah}", 60, fn () => [
            'stats' => ['guru' => Guru::count(), 'siswa' => Siswa::count(), 'kelas' => Kelas::count()],
            'kehadiranHariIni' => $this->persenHadir(now()),
        ]);

        return view('dashboard.admin', $ringkas + [
            'setupSelesai' => (bool) \App\Setting::get('setup_selesai'),
            'jadwalHariIni' => $this->jadwalHariIni(),
            'pendaftarTerbaru' => \App\Support\AksesSekolah::modul('ppdb') ? PpdbPendaftar::latest('id')->limit(6)->get() : collect(),
            'pengumuman' => Pengumuman::latest('pengumuman_id')->limit(4)->get(),
        ]);
    }

    private function kepsek(): View
    {
        // Grafik kepsek butuh agregat (8 query); cukup segar dalam 60 detik.
        $sekolah = \App\Support\SekolahAktif::instance()->get()?->id ?? 0;
        $ringkas = \Illuminate\Support\Facades\Cache::remember("dash_kepsek_{$sekolah}", 60, function () {
            $periode = Akademik::aktif();

            return [
                'stats' => ['guru' => Guru::count(), 'siswa' => Siswa::count(), 'kelas' => Kelas::tahunAktif()->count(), 'mapel' => Mapel::count()],
                'kehadiranHariIni' => $this->persenHadir(now()),
                'kehadiranMingguan' => $this->kehadiranMingguan(),
                'nilaiPerKelas' => $this->nilaiPerKelas($periode),
            ];
        });

        return view('dashboard.kepsek', $ringkas + [
            'pengumuman' => Pengumuman::latest('pengumuman_id')->limit(4)->get(),
        ]);
    }

    private function guru(): View
    {
        $user = auth()->user();
        $guru = $user->guru;

        return view('dashboard.guru', [
            'stats' => [
                'siswa' => Siswa::count(),
                'kelas' => Kelas::count(),
                'mapel' => Mapel::count(),
            ],
            'jadwalHariIni' => $guru ? $this->jadwalHariIni(fn ($q) => $q->where('guru_id', $guru->id)) : collect(),
            'pengumuman' => Pengumuman::latest('pengumuman_id')->limit(3)->get(),
        ]);
    }

    private function siswa(): View
    {
        $siswa = auth()->user()->siswa;
        $kelasId = $siswa?->kelas_id;

        $bulanIni = $siswa
            ? Absensi::where('siswa_id', $siswa->id)->whereBetween('tanggal', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->get()
            : collect();

        return view('dashboard.siswa', [
            'siswa' => $siswa,
            'stats' => [
                'mapel' => Mapel::count(),
            ],
            'kehadiran' => AbsensiController::hitung($bulanIni),
            'jadwalHariIni' => $kelasId ? $this->jadwalHariIni(fn ($q) => $q->where('kelas_id', $kelasId)) : collect(),
            'pengumuman' => Pengumuman::latest('pengumuman_id')->limit(3)->get(),
        ]);
    }

    /** Persentase siswa hadir pada satu tanggal (null bila belum ada absensi tercatat). */
    private function persenHadir(Carbon $tanggal): ?float
    {
        $rekap = Absensi::whereDate('tanggal', $tanggal->toDateString())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = $rekap->sum();

        return $total ? round(($rekap['hadir'] ?? 0) / $total * 100, 1) : null;
    }

    /** @return array{labels: array<int,string>, data: array<int, ?float>} Senin–Sabtu pada pekan ini */
    private function kehadiranMingguan(): array
    {
        $senin = now()->startOfWeek();
        $labels = [];
        $data = [];

        for ($i = 0; $i < 6; $i++) {
            $hari = $senin->copy()->addDays($i);
            $labels[] = $hari->translatedFormat('D, d M');
            $data[] = $hari->isFuture() ? null : $this->persenHadir($hari);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** @return array{labels: array<int,string>, data: array<int,float>} */
    private function nilaiPerKelas(array $periode): array
    {
        $baris = Nilai::where('semester_id', $periode['semester_id'] ?? 0)
            ->selectRaw('kelas_id, avg(nilai) as rata')->groupBy('kelas_id')->pluck('rata', 'kelas_id');

        $kelas = Kelas::whereIn('id', $baris->keys())->orderBy('nama_kelas')->pluck('nama_kelas', 'id');

        return [
            'labels' => $kelas->values()->all(),
            'data' => $kelas->keys()->map(fn ($id) => round((float) $baris[$id], 1))->all(),
        ];
    }

    private function jadwalHariIni(?callable $saring = null)
    {
        $hari = (int) now()->dayOfWeekIso;
        if ($hari > 6) {
            return collect(); // Minggu libur
        }

        $periode = Akademik::aktif();
        $query = Jadwal::with(['kelas:id,nama_kelas', 'mapel:id,mapel_nama', 'guru:id,nama_guru'])
            ->where('hari', $hari)->where('semester_id', $periode['semester_id'] ?? 0)
            ->orderBy('jam_mulai');

        if ($saring) {
            $saring($query);
        }

        return $query->limit(10)->get();
    }
}
