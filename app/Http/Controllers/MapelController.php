<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Mapel;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MapelController extends Controller
{
    public function index(): View
    {
        return view('mapel.index');
    }

    /** Sumber data tabel (server-side): hanya satu halaman yang dikirim per permintaan. */
    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Mapel::query(),
            searchable: ['mapel_nama'],
            orderable: ['id', 'mapel_nama', 'created_at'],
            map: fn (Mapel $mapel) => [
                'id' => $mapel->id,
                'mapel_nama' => $mapel->mapel_nama,
                'created_at' => $mapel->created_at?->translatedFormat('d M Y') ?? '-',
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        Mapel::create($this->validated($request));

        return response()->json(['message' => 'Mata pelajaran berhasil dibuat.'], 201);
    }

    public function update(Request $request, Mapel $mapel): JsonResponse
    {
        $mapel->update($this->validated($request, $mapel));

        return response()->json(['message' => 'Mata pelajaran berhasil diperbarui.']);
    }

    public function destroy(Mapel $mapel): JsonResponse
    {
        $mapel->loadCount(['guru', 'jadwal', 'nilai']);

        if ($mapel->guru_count || $mapel->jadwal_count || $mapel->nilai_count) {
            return response()->json([
                'message' => sprintf(
                    'Tidak dapat dihapus: masih dipakai oleh %d guru, %d jadwal, dan %d data nilai.',
                    $mapel->guru_count, $mapel->jadwal_count, $mapel->nilai_count,
                ),
            ], 409);
        }

        $mapel->delete();

        return response()->json(['message' => 'Mata pelajaran berhasil dihapus.']);
    }

    private function validated(Request $request, ?Mapel $mapel = null): array
    {
        return $request->validate([
            'mapel_nama' => ['required', 'string', 'max:200', SekolahAktif::unik('mapel', 'mapel_nama')->ignore($mapel?->id)],
        ], [], ['mapel_nama' => 'nama mata pelajaran']);
    }
}
