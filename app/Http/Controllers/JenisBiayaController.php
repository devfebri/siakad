<?php

namespace App\Http\Controllers;

use App\JenisBiaya;
use App\Support\DataTableResponse;
use App\Support\SekolahAktif;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master jenis biaya (SPP, uang gedung, seragam, dst.) untuk admin dan bendahara. */
class JenisBiayaController extends Controller
{
    public function index(): View
    {
        return view('keuangan.biaya', ['panel' => auth()->user()->panel]);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request, JenisBiaya::query()->withCount('tagihan'),
            searchable: ['nama'], orderable: ['id', 'nama', 'nominal'],
            map: fn (JenisBiaya $b) => [
                'id' => $b->id, 'nama' => $b->nama, 'nominal' => $b->nominal, 'nominal_rp' => 'Rp '.number_format($b->nominal, 0, ',', '.'),
                'jenis' => $b->jenis, 'jenis_label' => JenisBiaya::JENIS[$b->jenis] ?? $b->jenis, 'aktif' => $b->aktif ? 1 : 0,
                'status' => $b->aktif ? 'Aktif|success' : 'Nonaktif|muted', 'tagihan' => $b->tagihan_count,
            ],
            defaultOrder: 'id',
        );
    }

    public function store(Request $request): JsonResponse
    {
        JenisBiaya::create($this->validated($request));

        return response()->json(['message' => 'Jenis biaya ditambahkan.'], 201);
    }

    public function update(Request $request, JenisBiaya $biaya): JsonResponse
    {
        $biaya->update($this->validated($request, $biaya));

        return response()->json(['message' => 'Jenis biaya diperbarui.']);
    }

    public function destroy(JenisBiaya $biaya): JsonResponse
    {
        if ($biaya->tagihan()->exists()) {
            return response()->json(['message' => 'Tidak bisa dihapus karena sudah dipakai di tagihan. Nonaktifkan saja.'], 409);
        }
        $biaya->delete();

        return response()->json(['message' => 'Jenis biaya dihapus.']);
    }

    private function validated(Request $request, ?JenisBiaya $biaya = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', SekolahAktif::unik('jenis_biaya', 'nama')->ignore($biaya?->id)],
            'nominal' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'jenis' => ['required', Rule::in(array_keys(JenisBiaya::JENIS))],
        ], [], ['nama' => 'nama biaya', 'nominal' => 'nominal']);
        $data['aktif'] = $request->has('aktif') ? $request->boolean('aktif') : true;

        return $data;
    }
}
