<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/** Membaca isi menu Panduan (config/panduan.php) dan menyaringnya menurut panel pengguna dan modul paket sekolah. */
class Panduan
{
    /** @return Collection<int, array<string, mixed>> semua artikel yang boleh dilihat panel ini, sesuai urutan kategori lalu urutan tulis */
    public static function untukPanel(string $panel): Collection
    {
        $urutan = array_flip(array_keys(config('panduan.kategori')));

        return collect(config('panduan.artikel'))
            ->filter(fn (array $a) => self::tampil($a, $panel))
            ->values()
            ->sortBy(fn (array $a) => $urutan[$a['kategori']] ?? 99)
            ->values();
    }

    public static function cari(string $panel, string $slug): ?array
    {
        return self::untukPanel($panel)->firstWhere('slug', $slug);
    }

    /** Artikel tampil bila panelnya cocok dan (bila ada) salah satu modulnya tersedia untuk sekolah aktif. */
    public static function tampil(array $artikel, string $panel): bool
    {
        if (! array_intersect([$panel, 'semua'], $artikel['panel'])) {
            return false;
        }

        $modul = (array) ($artikel['modul'] ?? []);

        return ! $modul || collect($modul)->contains(fn ($m) => AksesSekolah::modul($m));
    }

    /** Alamat halaman yang dibahas artikel, atau null bila tidak ada atau rutenya belum tersedia untuk panel ini. */
    public static function tautan(array $artikel, string $panel): ?array
    {
        if (empty($artikel['tautan'])) {
            return null;
        }

        [$rute, $teks] = $artikel['tautan'];
        $rute = str_replace('{panel}', $panel, $rute);

        return Route::has($rute) ? [route($rute), $teks] : null;
    }

    /** **tebal** menjadi <strong>, `kode` menjadi <code>; sisanya di-escape sehingga aman ditampilkan. */
    public static function format(string $teks): string
    {
        $aman = e($teks);
        $aman = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $aman);

        return preg_replace('/`(.+?)`/', '<code class="rounded bg-slate-100 px-1.5 py-0.5 text-[0.85em]">$1</code>', $aman);
    }
}
