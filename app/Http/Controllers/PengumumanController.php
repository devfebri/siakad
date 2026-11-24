<?php

namespace App\Http\Controllers;

use App\Pengumuman;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PengumumanController extends Controller
{
    public function index(): View
    {
        return view('pengumuman.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Pengumuman::query(),
            searchable: ['pengumuman_judul', 'pengumuman_deskripsi', 'pengumuman_author'],
            orderable: ['pengumuman_judul', 'pengumuman_author', 'created_at'],
            map: fn (Pengumuman $p) => [
                'id' => $p->pengumuman_id,
                'pengumuman_judul' => $p->pengumuman_judul,
                'pengumuman_deskripsi' => $p->pengumuman_deskripsi,
                'ringkas' => Str::limit(strip_tags($p->pengumuman_deskripsi), 90),
                'pengumuman_author' => $p->pengumuman_author,
                'tanggal' => ($p->created_at ?? $p->pengumuman_tanggal)?->translatedFormat('d M Y') ?? '-',
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        Pengumuman::create($this->validated($request) + ['pengumuman_author' => auth()->user()->name]);

        return response()->json(['message' => 'Pengumuman berhasil dibuat.'], 201);
    }

    public function update(Request $request, Pengumuman $pengumuman): JsonResponse
    {
        $pengumuman->update($this->validated($request));

        return response()->json(['message' => 'Pengumuman berhasil diperbarui.']);
    }

    public function destroy(Pengumuman $pengumuman): JsonResponse
    {
        $pengumuman->delete();

        return response()->json(['message' => 'Pengumuman berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'pengumuman_judul' => ['required', 'string', 'max:150'],
            'pengumuman_deskripsi' => ['required', 'string'],
        ], [], ['pengumuman_judul' => 'judul', 'pengumuman_deskripsi' => 'isi pengumuman']);
    }
}
