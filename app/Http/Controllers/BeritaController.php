<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\Berita;
use App\Kategori;
use App\Support\DataTableResponse;
use App\Support\Html;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BeritaController extends Controller
{
    // ---------------------------------------------------------------- admin & guru

    public function index(): View
    {
        return view('berita.index');
    }

    public function data(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Berita::query()->with('kategori:id,kategori_nama');

        // Admin melihat semua berita; guru hanya berita tulisannya sendiri.
        if ($user->panel !== 'admin') {
            $query->where('author', $user->name);
        }

        return DataTableResponse::make(
            $request,
            $query,
            searchable: ['berita_judul', 'author'],
            orderable: ['berita_judul', 'author', 'berita_views', 'created_at'],
            map: fn (Berita $berita) => [
                'id' => $berita->id,
                'slug' => $berita->berita_slug,
                'gambar_url' => $berita->gambar_url,
                'berita_judul' => $berita->berita_judul,
                'kategori' => $berita->kategori?->kategori_nama ?? '-',
                'author' => $berita->author,
                'berita_views' => $berita->berita_views,
                'tanggal' => $berita->created_at?->translatedFormat('d M Y') ?? '-',
            ],
            searchRelations: ['kategori' => ['kategori_nama']],
        );
    }

    public function create(): View
    {
        return view('berita.form', ['berita' => null, 'kategori' => Kategori::orderBy('kategori_nama')->get(['id', 'kategori_nama'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $berita = new Berita($data);
        $berita->berita_isi = Html::clean($data['berita_isi']);
        $berita->author = auth()->user()->name;
        $berita->berita_gambar = $this->storeImage($request);
        $berita->save();

        return response()->json([
            'message' => 'Berita berhasil dipublikasikan.',
            'redirect' => route(auth()->user()->panel.'/berita'),
        ], 201);
    }

    public function edit(Berita $berita): View
    {
        $this->authorizeOwner($berita);

        return view('berita.form', ['berita' => $berita, 'kategori' => Kategori::orderBy('kategori_nama')->get(['id', 'kategori_nama'])]);
    }

    public function update(Request $request, Berita $berita): JsonResponse
    {
        $this->authorizeOwner($berita);
        $data = $this->validated($request);
        $data['berita_isi'] = Html::clean($data['berita_isi']);

        if ($image = $this->storeImage($request)) {
            $this->deleteImage($berita);
            $data['berita_gambar'] = $image;
        }

        $berita->update($data);

        return response()->json([
            'message' => 'Berita berhasil diperbarui.',
            'redirect' => route(auth()->user()->panel.'/berita'),
        ]);
    }

    public function destroy(Berita $berita): JsonResponse
    {
        $this->authorizeOwner($berita);

        $this->deleteImage($berita);
        $berita->delete();

        return response()->json(['message' => 'Berita berhasil dihapus.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'berita_judul' => ['required', 'string', 'max:100'],
            'kategori_id' => ['required', 'integer', SekolahAktif::ada('kategori')],
            'berita_isi' => ['required', 'string'],
            'berita_gambar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], [
            'berita_judul' => 'judul',
            'kategori_id' => 'kategori',
            'berita_isi' => 'isi berita',
            'berita_gambar' => 'gambar',
        ]);

        unset($data['berita_gambar']);

        return $data;
    }

    /** Guru hanya boleh mengubah/menghapus berita miliknya; admin boleh semuanya. */
    private function authorizeOwner(Berita $berita): void
    {
        $user = auth()->user();
        abort_unless($user->punyaPeran('admin') || $berita->author === $user->name, 403);
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('berita_gambar')) {
            return null;
        }

        $file = $request->file('berita_gambar');
        $name = Str::random(24).'.'.$file->extension();
        $file->move(public_path(Berita::IMAGE_DIR), $name);

        return $name;
    }

    private function deleteImage(Berita $berita): void
    {
        foreach ($berita->gambarPaths() as $path) {
            if (File::isFile($path)) {
                File::delete($path);
            }
        }
    }
}
