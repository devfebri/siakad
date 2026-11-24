<?php

namespace App\Http\Controllers;

use App\ProgramUnggulan;
use App\Support\DataTableResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Program unggulan yang tampil di beranda (dikelola dari menu Pengaturan). */
class ProgramUnggulanController extends Controller
{
    public function index()
    {
        return redirect()->route('admin/pengaturan', ['tab' => 'program']);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            ProgramUnggulan::query(),
            searchable: ['judul', 'deskripsi'],
            orderable: ['judul', 'urutan'],
            map: fn (ProgramUnggulan $p) => [
                'id' => $p->id,
                'judul' => $p->judul,
                'deskripsi' => $p->deskripsi,
                'ikon' => $p->ikon,
                'ikon_label' => ProgramUnggulan::IKON[$p->ikon] ?? $p->ikon,
                'urutan' => $p->urutan,
                'aktif' => $p->aktif ? 1 : 0,
                'status' => $p->aktif ? 'Tampil|success' : 'Disembunyikan|muted',
            ],
            defaultOrder: 'urutan',
            defaultDirection: 'asc',
        );
    }

    public function store(Request $request): JsonResponse
    {
        ProgramUnggulan::create($this->validated($request));

        return response()->json(['message' => 'Program unggulan berhasil ditambahkan.'], 201);
    }

    public function update(Request $request, ProgramUnggulan $program): JsonResponse
    {
        $program->update($this->validated($request));

        return response()->json(['message' => 'Program unggulan berhasil diperbarui.']);
    }

    public function destroy(ProgramUnggulan $program): JsonResponse
    {
        $program->delete();

        return response()->json(['message' => 'Program unggulan berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:300'],
            'ikon' => ['required', Rule::in(array_keys(ProgramUnggulan::IKON))],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'aktif' => ['nullable', 'boolean'],
        ], [], ['judul' => 'judul', 'ikon' => 'ikon', 'urutan' => 'urutan']);

        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = $request->boolean('aktif');

        return $data;
    }
}
