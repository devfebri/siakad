<?php

namespace App\Http\Controllers;

use App\Agenda;
use App\Berita;
use App\Gallery;
use App\Guru;
use App\Kategori;
use App\Mapel;
use App\Pengaturan;
use App\Pengumuman;
use App\ProgramUnggulan;
use App\Siswa;
use App\Support\Ppdb;
use App\Testimoni;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Situs publik: beranda, profil, akademik, berita, pengumuman, agenda, galeri, kontak. */
class SitusController extends Controller
{
    public function beranda(): View
    {
        return view(['portal' => 'public.beranda-portal', 'modern' => 'public.beranda-modern'][config('school.tema_depan')] ?? 'public.beranda', [
            'stat' => [
                'siswa' => Siswa::count(),
                'guru' => Guru::count(),
                'prestasi' => (int) config('school.stat_prestasi'),
                'alumni' => (int) config('school.stat_alumni'),
            ],
            'program' => ProgramUnggulan::where('aktif', true)->orderBy('urutan')->orderBy('id')->limit(4)->get(),
            'berita' => Berita::with('kategori')->latest('id')->limit(3)->get(),
            'galeri' => Gallery::latest('id')->limit(8)->get(),
            'testimoni' => Testimoni::where('aktif', true)->orderBy('urutan')->orderBy('id')->limit(6)->get(),
            'pengumuman' => Pengumuman::latest('pengumuman_id')->limit(3)->get(),
            'ppdb' => Ppdb::status(),
        ]);
    }

    public function profil(): View
    {
        $sections = config('profil-sekolah');

        return view('public.profil', [
            'sections' => $sections,
            'rows' => Pengaturan::whereIn('slot', array_keys($sections))->get()->keyBy('slot'),
            'guru' => Guru::with(['mapel:id,mapel_nama', 'user:id,email'])->orderBy('nama_guru')->get(),
        ]);
    }

    public function akademik(): View
    {
        return view('public.akademik', [
            'program' => ProgramUnggulan::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(),
            'mapel' => Mapel::orderBy('mapel_nama')->get(['id', 'mapel_nama']),
            'agenda' => Agenda::latest('agenda_id')->limit(4)->get(),
            'pengumuman' => Pengumuman::latest('pengumuman_id')->limit(4)->get(),
        ]);
    }

    public function berita(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $kategoriNama = $request->query('kategori');

        $berita = Berita::with('kategori')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('berita_judul', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('berita_isi', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($kategoriNama, fn ($query) => $query->whereHas('kategori', fn ($k) => $k->where('kategori_nama', $kategoriNama)))
            ->orderByDesc('id')
            ->paginate(9)
            ->withQueryString();

        return view('public.berita', [
            'berita' => $berita,
            'kategori' => Kategori::withCount('berita')->orderBy('kategori_nama')->get(),
            'populer' => Berita::with('kategori')->orderByDesc('berita_views')->limit(5)->get(),
            'q' => $q,
            'kategoriAktif' => $kategoriNama,
        ]);
    }

    public function beritaShow(string $slug): View
    {
        $post = Berita::with('kategori')->where('berita_slug', $slug)->firstOrFail();
        $post->increment('berita_views');

        return view('public.berita-show', [
            'post' => $post,
            'terkait' => Berita::with('kategori')->where('kategori_id', $post->kategori_id)->whereKeyNot($post->id)->latest('id')->limit(3)->get(),
            'populer' => Berita::with('kategori')->orderByDesc('berita_views')->limit(5)->get(),
            'kategori' => Kategori::withCount('berita')->orderBy('kategori_nama')->get(),
        ]);
    }

    public function pengumuman(): View
    {
        return view('public.pengumuman', ['data' => Pengumuman::latest('pengumuman_id')->paginate(10)]);
    }

    public function agenda(): View
    {
        return view('public.agenda', ['data' => Agenda::latest('agenda_id')->paginate(10)]);
    }

    public function galeri(): View
    {
        return view('public.galeri', ['data' => Gallery::latest('id')->paginate(24)]);
    }

    public function kontak(): View
    {
        return view('public.kontak');
    }

    // ---------------------------------------------------------------- alamat lama -> alamat baru

    public function alihkanLama(string $tujuan, ?string $jangkar = null): RedirectResponse
    {
        return redirect(url($tujuan).($jangkar ? '#'.$jangkar : ''), 301);
    }
}
