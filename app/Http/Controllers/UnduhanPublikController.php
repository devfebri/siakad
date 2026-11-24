<?php

namespace App\Http\Controllers;

use App\Unduhan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Menu Unduhan di situs publik: daftar berkas, pencarian, filter kategori, dan unduhan dengan penghitung. */
class UnduhanPublikController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'kategori' => ['nullable', 'string', 'max:60']]);

        $data = Unduhan::where('aktif', true)
            ->when($f['kategori'] ?? null, fn ($q, $v) => $q->where('kategori', $v))
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($w) => $w->where('judul', 'like', $like)->orWhere('deskripsi', 'like', $like));
            })
            ->orderBy('urutan')->latest('id')->paginate(12)->withQueryString();

        return view('public.unduhan', [
            'data' => $data,
            'kategori' => Unduhan::where('aktif', true)->select('kategori', DB::raw('count(*) as total'))->groupBy('kategori')->orderBy('kategori')->pluck('total', 'kategori'),
            'q' => $f['q'] ?? '',
            'aktif' => $f['kategori'] ?? '',
        ]);
    }

    /** Unduh berkas (selalu sebagai lampiran) dan tambah hitungan. Berkas yang disembunyikan atau hilang menghasilkan 404. */
    public function unduh(Unduhan $unduhan)
    {
        abort_unless($unduhan->aktif && is_file($unduhan->path), 404);

        $unduhan->increment('jumlah_unduh');

        return response()->download($unduhan->path, $unduhan->nama_asli, ['X-Content-Type-Options' => 'nosniff']);
    }
}
