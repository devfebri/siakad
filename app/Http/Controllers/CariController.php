<?php

namespace App\Http\Controllers;

use App\Berita;
use App\Guru;
use App\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pencarian cepat di topbar admin (siswa, guru, berita). */
class CariController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (! $request->expectsJson()) {
            return redirect()->route('admin/siswa', ['cari' => $q]);
        }

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $results = collect();

        Siswa::where('nama_siswa', 'like', $like)->orWhere('nisn', 'like', $like)->limit(4)->get(['id', 'nama_siswa', 'nisn'])
            ->each(fn ($s) => $results->push(['type' => 'Siswa', 'icon' => 'mortarboard', 'label' => $s->nama_siswa.' · '.$s->nisn, 'url' => route('admin/siswa', ['cari' => $s->nisn])]));
        Guru::where('nama_guru', 'like', $like)->orWhere('nip_guru', 'like', $like)->limit(4)->get(['id', 'nama_guru', 'nip_guru'])
            ->each(fn ($g) => $results->push(['type' => 'Guru', 'icon' => 'person-workspace', 'label' => $g->nama_guru, 'url' => route('admin/guru', ['cari' => $g->nip_guru])]));
        Berita::where('berita_judul', 'like', $like)->limit(4)->get(['id', 'berita_judul'])
            ->each(fn ($b) => $results->push(['type' => 'Berita', 'icon' => 'newspaper', 'label' => $b->berita_judul, 'url' => route('admin/berita.edit', $b)]));

        return response()->json(['results' => $results->values()]);
    }
}
