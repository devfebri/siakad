<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\LogAktivitas;
use App\Modul;
use App\Paket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Panel platform: paket langganan, harga 1/6/12 bulan, dan modul yang termasuk di tiap paket. */
class PaketController extends Controller
{
    public function index(): View
    {
        return view('platform.paket.index', [
            'paket' => Paket::with(['harga', 'modul'])->withCount('modul')->orderBy('urutan')->orderBy('id')->get(),
            'modul' => Modul::orderBy('id')->get(),
            'durasi' => Paket::DURASI,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['kode' => mb_strtolower(trim((string) $request->input('kode')))]);
        $data = $this->validasi($request, null);

        $paket = DB::transaction(fn () => $this->simpan(new Paket(), $data));
        LogAktivitas::catat('paket.buat', "Paket {$paket->nama} dibuat.");

        return response()->json(['message' => "Paket {$paket->nama} dibuat.", 'redirect' => route('platform/paket')], 201);
    }

    public function update(Request $request, Paket $paket): JsonResponse
    {
        $data = $this->validasi($request, $paket);

        DB::transaction(fn () => $this->simpan($paket, $data));
        LogAktivitas::catat('paket.ubah', "Paket {$paket->nama} diubah (harga/modul).");

        return response()->json(['message' => "Paket {$paket->nama} disimpan."]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Paket $paket): array
    {
        $aturan = [
            'nama' => ['required', 'string', 'min:2', 'max:80'],
            'deskripsi' => ['nullable', 'string', 'max:300'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'harga' => ['required', 'array'],
            'modul' => ['nullable', 'array'],
            'modul.*' => ['string', Rule::exists('modul', 'kode')],
        ];
        foreach (Paket::DURASI as $bulan) {
            $aturan["harga.$bulan"] = ['required', 'integer', 'min:0', 'max:1000000000'];
        }
        if (! $paket) {
            $aturan['kode'] = ['required', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9_-]+$/', Rule::unique('paket', 'kode')];
        }

        return $request->validate($aturan, [
            'harga.*.required' => 'Harga untuk setiap durasi wajib diisi (isi 0 bila gratis).',
            'harga.*.integer' => 'Harga harus berupa angka rupiah tanpa titik.',
            'kode.regex' => 'Kode hanya boleh huruf kecil, angka, garis bawah, dan strip.',
        ], ['nama' => 'nama paket', 'kode' => 'kode paket']);
    }

    private function simpan(Paket $paket, array $data): Paket
    {
        $paket->fill([
            'nama' => trim(strip_tags($data['nama'])),
            'deskripsi' => $data['deskripsi'] ?? null,
            'urutan' => $data['urutan'] ?? $paket->urutan ?? 0,
            'aktif' => request()->boolean('aktif'),
        ] + (isset($data['kode']) ? ['kode' => $data['kode']] : []))->save();

        foreach (Paket::DURASI as $bulan) {
            $paket->harga()->updateOrCreate(['durasi_bulan' => $bulan], ['harga' => (int) $data['harga'][$bulan]]);
        }

        $paket->modul()->sync(Modul::whereIn('kode', $data['modul'] ?? [])->pluck('id')->all());

        return $paket;
    }
}
