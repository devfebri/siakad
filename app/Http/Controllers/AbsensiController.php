<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Absensi;
use App\Kelas;
use App\Siswa;
use App\Support\Periode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Absensi harian siswa: admin dan guru mencatat, siswa melihat kehadirannya sendiri. */
class AbsensiController extends Controller
{
    public function index(): View
    {
        return view('absensi.index', [
            'kelas' => Kelas::tahunAktif()->orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            'status' => Absensi::STATUS,
        ]);
    }

    /** Daftar siswa satu kelas beserta status absen pada tanggal tertentu (bawaan: hadir). */
    public function siswa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')],
            'tanggal' => ['required', 'date'],
        ]);

        $tercatat = Absensi::where('kelas_id', $data['kelas_id'])->whereDate('tanggal', $data['tanggal'])->get()->keyBy('siswa_id');

        $siswa = Siswa::where('kelas_id', $data['kelas_id'])->orderBy('nama_siswa')->get(['id', 'nisn', 'nama_siswa'])
            ->map(fn (Siswa $s) => [
                'id' => $s->id,
                'nisn' => $s->nisn,
                'nama' => $s->nama_siswa,
                'status' => $tercatat[$s->id]->status ?? 'hadir',
                'keterangan' => $tercatat[$s->id]->keterangan ?? '',
            ]);

        return response()->json(['siswa' => $siswa, 'sudah_tercatat' => $tercatat->isNotEmpty()]);
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.siswa_id' => ['required', 'integer', 'distinct'],
            'items.*.status' => ['required', Rule::in(array_keys(Absensi::STATUS))],
            'items.*.keterangan' => ['nullable', 'string', 'max:150'],
        ], [
            'tanggal.before_or_equal' => 'Tanggal absensi tidak boleh di masa depan.',
        ], ['tanggal' => 'tanggal']);

        // Hanya siswa yang memang berada di kelas tersebut yang boleh dicatat.
        Periode::pastikanTanggalTerbuka($data['tanggal']);
        $valid = Siswa::where('kelas_id', $data['kelas_id'])->pluck('id')->flip();
        $items = collect($data['items'])->filter(fn ($i) => $valid->has((int) $i['siswa_id']));

        DB::transaction(function () use ($data, $items) {
            foreach ($items as $i) {
                // whereDate: kolom tanggal bisa tersimpan dengan jam 00:00:00 di sebagian database.
                $absen = Absensi::where('siswa_id', $i['siswa_id'])->whereDate('tanggal', $data['tanggal'])->first() ?? new Absensi(['siswa_id' => $i['siswa_id'], 'tanggal' => $data['tanggal']]);
                $absen->fill(['kelas_id' => $data['kelas_id'], 'status' => $i['status'], 'keterangan' => $i['keterangan'] ?? null, 'dicatat_oleh' => auth()->id()])->save();
            }
        });

        return response()->json(['message' => $items->count().' data kehadiran berhasil disimpan.', 'jumlah' => $items->count()]);
    }

    /** Rekap bulanan satu kelas: jumlah H/I/S/A dan persentase kehadiran per siswa. */
    public function rekap(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', SekolahAktif::ada('rombel')],
            'bulan' => ['required', 'date_format:Y-m'],
        ]);

        $awal = Carbon::createFromFormat('Y-m-d', $data['bulan'].'-01')->startOfDay();
        $akhir = $awal->copy()->endOfMonth();

        $perSiswa = Absensi::where('kelas_id', $data['kelas_id'])->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get()->groupBy('siswa_id');

        $baris = Siswa::where('kelas_id', $data['kelas_id'])->orderBy('nama_siswa')->get(['id', 'nisn', 'nama_siswa'])
            ->map(function (Siswa $s) use ($perSiswa) {
                return ['id' => $s->id, 'nama' => $s->nama_siswa, 'nisn' => $s->nisn] + self::hitung($perSiswa[$s->id] ?? collect());
            });

        return response()->json(['bulan' => $awal->translatedFormat('F Y'), 'baris' => $baris]);
    }

    /** Halaman kehadiran milik siswa yang login. */
    public function saya(): View
    {
        return view('absensi.saya', ['dataUrl' => route('siswa/kehadiran.data'), 'judul' => 'Kehadiran Saya', 'subjudul' => 'Rekap kehadiran Anda per bulan.']);
    }

    public function sayaData(Request $request): JsonResponse
    {
        $siswa = auth()->user()->siswa;
        abort_unless($siswa, 404);

        return self::rekapBulan($siswa, $request);
    }

    /** Rekap kehadiran satu siswa untuk bulan ?bulan=Y-m (dipakai siswa dan orang tua). */
    public static function rekapBulan(\App\Siswa $siswa, Request $request): JsonResponse
    {
        $bulan = $request->validate(['bulan' => ['required', 'date_format:Y-m']])['bulan'];
        $awal = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfDay();

        $data = Absensi::where('siswa_id', $siswa->id)->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->orderBy('tanggal')->get();

        return response()->json([
            'bulan' => $awal->translatedFormat('F Y'),
            'ringkasan' => self::hitung($data),
            'daftar' => $data->map(fn (Absensi $a) => [
                'tanggal' => $a->tanggal->translatedFormat('l, d F Y'),
                'status' => Absensi::STATUS[$a->status]['label'] ?? $a->status,
                'badge' => Absensi::STATUS[$a->status]['badge'] ?? 'muted',
                'keterangan' => $a->keterangan,
            ])->values(),
        ]);
    }

    /** @return array{hadir:int, izin:int, sakit:int, alpa:int, total:int, persen: ?float} */
    public static function hitung($kumpulan): array
    {
        $hitung = fn (string $status) => $kumpulan->where('status', $status)->count();
        $total = $kumpulan->count();

        return [
            'hadir' => $hitung('hadir'),
            'izin' => $hitung('izin'),
            'sakit' => $hitung('sakit'),
            'alpa' => $hitung('alpa'),
            'total' => $total,
            'persen' => $total ? round($hitung('hadir') / $total * 100, 1) : null,
        ];
    }
}
