<?php

namespace App\Http\Controllers;

use App\JenisBiaya;
use App\Kelas;
use App\PembayaranSpp;
use App\Support\DataTableResponse;
use App\Support\Keuangan;
use App\Support\SekolahAktif;
use App\Support\SppPdf;
use App\Tagihan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Keuangan sekolah untuk admin dan bendahara (rute yang sama di dua panel): ringkasan, tagihan, pembayaran, dan kuitansi.
 * Alamat dibangun dari panel pengguna ($panel), jadi tampilan yang sama dipakai di /admin/keuangan dan /bendahara/keuangan.
 */
class KeuanganController extends Controller
{
    private function panel(): string
    {
        return auth()->user()->panel;
    }

    private static function rupiah(int $n): string
    {
        return 'Rp '.number_format($n, 0, ',', '.');
    }

    // ------------------------------------------------------------ ringkasan

    public function ringkasan(): View
    {
        return view('keuangan.ringkasan', [
            'panel' => $this->panel(),
            'r' => Keuangan::ringkasan(),
            'terbaru' => PembayaranSpp::aktif()->with('tagihan.siswa:id,nama_siswa')->latest('id')->limit(8)->get(),
            'menunggak' => Tagihan::belumLunas()->where('jatuh_tempo', '<', today()->toDateString())->with('siswa:id,nama_siswa,kelas_id', 'siswa.kelas:id,nama_kelas')
                ->orderBy('jatuh_tempo')->limit(8)->get(),
        ]);
    }

    // ------------------------------------------------------------ tagihan

    public function tagihan(): View
    {
        return view('keuangan.tagihan', [
            'panel' => $this->panel(),
            'kelas' => Kelas::where('tahun_ajaran_id', \App\Support\Akademik::semesterAktif()->tahun_ajaran_id)->orderBy('nama_kelas')->get(['id', 'nama_kelas']),
            'biaya' => JenisBiaya::where('aktif', true)->orderBy('nama')->get(),
            'metode' => PembayaranSpp::METODE,
        ]);
    }

    public function tagihanData(Request $request): JsonResponse
    {
        $query = Tagihan::query()->with(['siswa:id,nama_siswa,nisn,kelas_id', 'siswa.kelas:id,nama_kelas']);
        if (array_key_exists($status = $request->input('status'), Tagihan::STATUS)) {
            $query->where('status', $status);
        }
        if ($request->input('terlambat')) {
            $query->belumLunas()->where('jatuh_tempo', '<', today()->toDateString());
        }
        if ($kelas = (int) $request->input('kelas_id')) {
            $query->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelas));
        }

        return DataTableResponse::make(
            $request, $query,
            searchable: ['nama'], orderable: ['id', 'nama', 'jumlah', 'jatuh_tempo', 'status'],
            map: fn (Tagihan $t) => [
                'id' => $t->id,
                'siswa' => $t->siswa?->nama_siswa ?? '-',
                'kelas' => $t->siswa?->kelas?->nama_kelas ?? '-',
                'nama' => $t->nama,
                'jumlah' => self::rupiah($t->jumlah),
                'terbayar' => self::rupiah($t->terbayar),
                'sisa' => self::rupiah($t->sisa),
                'sisa_raw' => $t->sisa,
                'jatuh_tempo' => $t->jatuh_tempo?->translatedFormat('d M Y') ?? '-',
                'status_raw' => $t->status,
                'status' => ($t->terlambat() ? 'Terlambat' : Tagihan::STATUS[$t->status]).'|'.($t->terlambat() ? 'danger' : Tagihan::WARNA[$t->status]),
            ],
            defaultOrder: 'id',
            searchRelations: ['siswa' => ['nama_siswa', 'nisn']],
        );
    }

    /** Buat tagihan untuk banyak siswa sekaligus. */
    public function tagihanMassal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis_biaya_id' => ['required', 'integer', SekolahAktif::ada('jenis_biaya')],
            'kelas_id' => ['nullable', 'integer', SekolahAktif::ada('rombel')],
            'bulan_mulai' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'jumlah_bulan' => ['nullable', 'integer', 'between:1,12'],
            'tanggal_jatuh_tempo' => ['required', 'integer', 'between:1,28'],
            'nominal' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
        ], ['bulan_mulai.regex' => 'Pilih bulan yang benar.'], ['jenis_biaya_id' => 'jenis biaya', 'bulan_mulai' => 'bulan mulai', 'jumlah_bulan' => 'jumlah bulan', 'tanggal_jatuh_tempo' => 'tanggal jatuh tempo']);

        $hasil = Keuangan::buatMassal(JenisBiaya::findOrFail($data['jenis_biaya_id']), array_filter([(int) ($data['kelas_id'] ?? 0)]), $data['bulan_mulai'], (int) ($data['jumlah_bulan'] ?? 1), (int) $data['tanggal_jatuh_tempo'], isset($data['nominal']) ? (int) $data['nominal'] : null);

        return response()->json(['message' => $hasil['dibuat'] === 0 && $hasil['dilewati'] === 0
            ? 'Tidak ada siswa aktif yang bisa ditagih. Pastikan siswa sudah masuk kelas.'
            : "{$hasil['dibuat']} tagihan dibuat".($hasil['dilewati'] ? " ({$hasil['dilewati']} sudah ada, dilewati)." : '.')], 201);
    }

    public function bayar(Request $request, Tagihan $tagihan): JsonResponse
    {
        $data = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'metode' => ['required', Rule::in(array_keys(PembayaranSpp::METODE))],
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:300'],
        ], [], ['jumlah' => 'jumlah bayar']);

        $p = Keuangan::bayar($tagihan, (int) $data['jumlah'], $data['metode'], $data['tanggal'] ?? null, $data['catatan'] ?? null);

        return response()->json([
            'message' => "Pembayaran {$p->nomor} sebesar ".self::rupiah($p->jumlah).' dicatat.',
            'kuitansi' => route($this->panel().'/keuangan.kuitansi', $p),
        ]);
    }

    public function batalTagihan(Tagihan $tagihan): JsonResponse
    {
        Keuangan::batalkanTagihan($tagihan);

        return response()->json(['message' => 'Tagihan dibatalkan.']);
    }

    // ------------------------------------------------------------ pembayaran

    public function pembayaran(): View
    {
        return view('keuangan.pembayaran', ['panel' => $this->panel(), 'metode' => PembayaranSpp::METODE]);
    }

    public function pembayaranData(Request $request): JsonResponse
    {
        $query = PembayaranSpp::query()->with(['tagihan:id,nama,siswa_id', 'tagihan.siswa:id,nama_siswa', 'penerima:id,name']);
        if (array_key_exists($metode = $request->input('metode'), PembayaranSpp::METODE)) {
            $query->where('metode', $metode);
        }
        if ($request->input('batal')) {
            $query->whereNotNull('dibatalkan_pada');
        }

        return DataTableResponse::make(
            $request, $query,
            searchable: ['nomor'], orderable: ['id', 'nomor', 'jumlah', 'tanggal'],
            map: fn (PembayaranSpp $p) => [
                'id' => $p->id,
                'nomor' => $p->nomor,
                'tanggal' => $p->tanggal?->translatedFormat('d M Y') ?? '-',
                'siswa' => $p->tagihan?->siswa?->nama_siswa ?? '-',
                'tagihan' => $p->tagihan?->nama ?? '-',
                'jumlah' => self::rupiah($p->jumlah),
                'metode' => PembayaranSpp::METODE[$p->metode] ?? $p->metode,
                'penerima' => $p->penerima?->name ?? '-',
                'batal' => $p->dibatalkan() ? 1 : 0,
                'status' => $p->dibatalkan() ? 'Dibatalkan|muted' : 'Sah|success',
            ],
            defaultOrder: 'id',
            searchRelations: ['tagihan.siswa' => ['nama_siswa']],
        );
    }

    public function batalBayar(Request $request, PembayaranSpp $pembayaran): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:200']], [], ['alasan' => 'alasan pembatalan']);
        Keuangan::batalkanPembayaran($pembayaran, $data['alasan']);

        return response()->json(['message' => "Pembayaran {$pembayaran->nomor} dibatalkan."]);
    }

    public function kuitansi(PembayaranSpp $pembayaran): Response
    {
        return SppPdf::kuitansi($pembayaran)->download(SppPdf::namaBerkas($pembayaran));
    }
}
