<?php

namespace App\Http\Controllers;

use App\Support\Impor;
use App\Support\SekolahAktif;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Impor guru, siswa, dan orang tua dari Excel/CSV: unduh template, periksa berkas, lalu simpan. */
class ImporController extends Controller
{
    public function index(): View
    {
        return view('impor.index', ['jenis' => array_filter(Impor::JENIS, fn ($d, $k) => Impor::tersedia($k), ARRAY_FILTER_USE_BOTH)]);
    }

    public function template(string $jenis): BinaryFileResponse
    {
        abort_unless(Impor::tersedia($jenis), 404);

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        Impor::template($jenis, $path);

        return response()->download($path, "template-impor-{$jenis}.xlsx")->deleteFileAfterSend();
    }

    /** Langkah 1: simpan berkas sementara dan laporkan hasil pemeriksaan tanpa mengubah data. */
    public function periksa(Request $request, string $jenis): JsonResponse
    {
        abort_unless(Impor::tersedia($jenis), 404);
        $request->validate(['berkas' => ['required', 'file', 'max:2048', 'mimes:xlsx,csv,txt']], [
            'berkas.mimes' => 'Berkas harus berformat .xlsx atau .csv.', 'berkas.max' => 'Ukuran berkas maksimal 2 MB.',
        ], ['berkas' => 'berkas']);

        $berkas = $request->file('berkas');
        $ekstensi = strtolower($berkas->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $token = Str::random(32);
        $berkas->storeAs($this->folder(), "{$token}.{$ekstensi}", 'local');

        try {
            $hasil = Impor::periksa($jenis, Impor::baca(Storage::disk('local')->path($this->folder()."/{$token}.{$ekstensi}"), $ekstensi));
        } catch (\RuntimeException|\Throwable $e) {
            Storage::disk('local')->delete($this->folder()."/{$token}.{$ekstensi}");

            return response()->json(['message' => $e instanceof \RuntimeException ? $e->getMessage() : 'Berkas tidak dapat dibaca. Pastikan formatnya .xlsx atau .csv sesuai template.'], 422);
        }

        return response()->json([
            'token' => $token,
            'jumlah_valid' => count($hasil['valid']),
            'jumlah_galat' => count($hasil['galat']),
            'galat' => array_slice($hasil['galat'], 0, 100),
            'contoh' => array_map(fn ($r) => ['baris' => $r['baris'], 'id' => $r['id'] !== '' ? $r['id'] : '-', 'nama' => $r['nama'], 'info' => match ($jenis) {
                'guru' => $r['mata_pelajaran'],
                'orang_tua' => $r['anak_nama'],
                default => $r['rombel'] ?: 'belum ditempatkan',
            }], array_slice($hasil['valid'], 0, 8)),
        ]);
    }

    /** Langkah 2: periksa ulang berkas yang tersimpan lalu simpan baris yang valid. */
    public function simpan(Request $request, string $jenis): JsonResponse
    {
        abort_unless(Impor::tersedia($jenis), 404);
        $token = $request->validate(['token' => ['required', 'regex:/^[A-Za-z0-9]{32}$/']])['token'];

        $ekstensi = collect(['xlsx', 'csv'])->first(fn ($e) => Storage::disk('local')->exists($this->folder()."/{$token}.{$e}"));
        if (! $ekstensi) {
            return response()->json(['message' => 'Berkas tidak ditemukan atau sudah diproses. Unggah ulang berkas Anda.'], 410);
        }
        $path = Storage::disk('local')->path($this->folder()."/{$token}.{$ekstensi}");

        try {
            $hasil = Impor::periksa($jenis, Impor::baca($path, $ekstensi));
            $simpan = $hasil['valid'] ? Impor::simpan($jenis, $hasil['valid']) : ['tersimpan' => 0, 'mapel_baru' => []];
        } finally {
            Storage::disk('local')->delete($this->folder()."/{$token}.{$ekstensi}");
        }

        $sandi = Impor::sandiAwal($jenis);
        $pesan = "{$simpan['tersimpan']} ".Impor::JENIS[$jenis]['label']." diimpor (password awal: {$sandi}).";
        if ($hasil['galat']) {
            $pesan .= ' '.count($hasil['galat']).' baris dilewati karena bermasalah.';
        }

        return response()->json(['message' => $pesan, 'tersimpan' => $simpan['tersimpan'], 'dilewati' => count($hasil['galat']), 'mapel_baru' => $simpan['mapel_baru']]);
    }

    private function folder(): string
    {
        return 'impor/'.SekolahAktif::instance()->id();
    }
}
