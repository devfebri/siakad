<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Kategori;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriController extends Controller
{
    public function index(): View
    {
        return view('kategori.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Kategori::query()->withCount('berita'),
            searchable: ['kategori_nama'],
            orderable: ['kategori_nama', 'berita_count', 'created_at'],
            map: fn (Kategori $k) => [
                'id' => $k->id,
                'kategori_nama' => $k->kategori_nama,
                'berita_count' => $k->berita_count,
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        Kategori::create($this->validated($request));

        return response()->json(['message' => 'Kategori berhasil dibuat.'], 201);
    }

    public function update(Request $request, Kategori $kategori): JsonResponse
    {
        $kategori->update($this->validated($request, $kategori));

        return response()->json(['message' => 'Kategori berhasil diperbarui.']);
    }

    public function destroy(Kategori $kategori): JsonResponse
    {
        $jumlah = $kategori->berita()->count();

        if ($jumlah > 0) {
            return response()->json([
                'message' => "Tidak dapat dihapus: masih ada {$jumlah} berita di kategori ini. Pindahkan atau hapus beritanya dulu.",
            ], 409);
        }

        $kategori->delete();

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }

    private function validated(Request $request, ?Kategori $kategori = null): array
    {
        return $request->validate([
            'kategori_nama' => ['required', 'string', 'max:100', SekolahAktif::unik('kategori', 'kategori_nama')->ignore($kategori?->id)],
        ], [], ['kategori_nama' => 'nama kategori']);
    }
}
