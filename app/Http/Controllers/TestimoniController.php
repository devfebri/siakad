<?php

namespace App\Http\Controllers;

use App\Support\DataTableResponse;
use App\Testimoni;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** Testimoni alumni/orang tua yang tampil di beranda (dikelola dari menu Pengaturan). */
class TestimoniController extends Controller
{
    public function index()
    {
        return redirect()->route('admin/pengaturan', ['tab' => 'testimoni']);
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Testimoni::query(),
            searchable: ['nama', 'peran', 'isi'],
            orderable: ['nama', 'urutan'],
            map: fn (Testimoni $t) => [
                'id' => $t->id,
                'nama' => $t->nama,
                'peran' => $t->peran,
                'foto' => $t->foto_url,
                'isi' => $t->isi,
                'ringkas' => Str::limit($t->isi, 90),
                'urutan' => $t->urutan,
                'aktif' => $t->aktif ? 1 : 0,
                'status' => $t->aktif ? 'Tampil|success' : 'Disembunyikan|muted',
            ],
            defaultOrder: 'urutan',
            defaultDirection: 'asc',
        );
    }

    public function store(Request $request): JsonResponse
    {
        Testimoni::create($this->validated($request) + ['foto' => $this->simpanFoto($request)]);

        return response()->json(['message' => 'Testimoni berhasil ditambahkan.'], 201);
    }

    public function update(Request $request, Testimoni $testimoni): JsonResponse
    {
        $data = $this->validated($request);
        if ($foto = $this->simpanFoto($request)) {
            $this->hapusFoto($testimoni);
            $data['foto'] = $foto;
        }
        $testimoni->update($data);

        return response()->json(['message' => 'Testimoni berhasil diperbarui.']);
    }

    public function destroy(Testimoni $testimoni): JsonResponse
    {
        $this->hapusFoto($testimoni);
        $testimoni->delete();

        return response()->json(['message' => 'Testimoni berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'peran' => ['nullable', 'string', 'max:120'],
            'isi' => ['required', 'string', 'max:600'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'aktif' => ['nullable', 'boolean'],
        ], [], ['nama' => 'nama', 'peran' => 'peran', 'foto' => 'foto', 'isi' => 'isi testimoni', 'urutan' => 'urutan']);

        $data['urutan'] = $data['urutan'] ?? 0;
        $data['aktif'] = $request->boolean('aktif');
        unset($data['foto']);

        return $data;
    }

    private function simpanFoto(Request $request): ?string
    {
        if (! $request->hasFile('foto')) {
            return null;
        }

        $file = $request->file('foto');
        $name = Str::random(24).'.'.$file->extension();
        $file->move(public_path(Testimoni::DIR), $name);

        return $name;
    }

    private function hapusFoto(Testimoni $testimoni): void
    {
        if ($testimoni->foto && File::isFile($path = public_path(Testimoni::DIR.'/'.$testimoni->foto))) {
            File::delete($path);
        }
    }
}
