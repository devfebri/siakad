<?php

namespace App\Http\Controllers;

use App\Gallery;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GalleryController extends Controller
{
    /** Folder foto, relatif terhadap public/ (sama dengan data lama, jadi foto yang sudah ada tetap tampil). */
    private const DIR = 'storage/gallery';

    public function index(): View
    {
        return view('gallery.index');
    }

    public function data(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            Gallery::query(),
            searchable: ['author', 'foto'],
            orderable: ['author', 'created_at'],
            map: fn (Gallery $foto) => [
                'id' => $foto->id,
                'url' => asset(self::DIR.'/'.$foto->foto),
                'author' => $foto->author,
                'tanggal' => $foto->created_at?->translatedFormat('d M Y') ?? '-',
            ],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], ['foto' => 'foto']);

        $file = $request->file('foto');
        $name = Str::random(24).'.'.$file->extension();
        $file->move(public_path(self::DIR), $name);

        Gallery::create(['author' => auth()->user()->name, 'foto' => $name]);

        return response()->json(['message' => 'Foto berhasil ditambahkan.'], 201);
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $path = public_path(self::DIR.'/'.$gallery->foto);
        if (File::isFile($path)) {
            File::delete($path);
        }

        $gallery->delete();

        return response()->json(['message' => 'Foto berhasil dihapus.']);
    }
}
