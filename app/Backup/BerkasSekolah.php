<?php

namespace App\Backup;

use App\Sekolah;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Berkas unggahan milik satu sekolah. Berkas disimpan di folder bersama (public/storage/...) tanpa folder per sekolah,
 * jadi kepemilikan ditentukan dari baris database yang merujuknya. Hanya berkas di dalam public/storage atau disk privat aplikasi
 * yang dihitung (jalur dari database tidak dipercaya mentah-mentah).
 */
class BerkasSekolah
{
    /** Kunci pengaturan situs yang berisi jalur gambar (relatif terhadap public/). */
    private const GAMBAR_PENGATURAN = ['logo', 'hero_gambar', 'kepsek_foto'];

    /**
     * @return array<string, string> [jalur mutlak => jalur di dalam zip]
     */
    public static function daftar(Sekolah $sekolah): array
    {
        $id = $sekolah->id;
        $kandidat = [];

        $publik = function (?string $relatif) use (&$kandidat) {
            if ($relatif) {
                $kandidat[public_path(ltrim($relatif, '/'))] = 'berkas/'.ltrim($relatif, '/');
            }
        };

        foreach (['guru', 'siswa'] as $peran) {
            foreach (DB::table($peran)->join('users', 'users.id', '=', "$peran.user_id")->where("$peran.sekolah_id", $id)->whereNotNull("$peran.avatar")->select('users.email', "$peran.avatar")->cursor() as $r) {
                $publik("storage/$peran/{$r->email}/{$r->avatar}");
            }
        }
        foreach (DB::table('berita')->where('sekolah_id', $id)->whereNotNull('berita_gambar')->pluck('berita_gambar') as $f) {
            $publik('storage/berita/files/'.$f);
        }
        foreach (DB::table('gallery')->where('sekolah_id', $id)->whereNotNull('foto')->pluck('foto') as $f) {
            $publik('storage/gallery/'.$f);
        }
        foreach (DB::table('testimoni')->where('sekolah_id', $id)->whereNotNull('foto')->pluck('foto') as $f) {
            $publik('storage/testimoni/'.$f);
        }
        foreach (DB::table('unduhan')->where('sekolah_id', $id)->whereNotNull('berkas')->pluck('berkas') as $f) {
            $publik('storage/unduhan/'.$f);
        }
        foreach (DB::table('ppdb_pendaftar')->where('sekolah_id', $id)->whereNotNull('berkas')->pluck('berkas') as $f) {
            $publik($f);
        }
        foreach (DB::table('site_settings')->where('sekolah_id', $id)->whereIn('key', self::GAMBAR_PENGATURAN)->whereNotNull('value')->pluck('value') as $f) {
            $publik($f);
        }
        $dirPengaturan = collect(config('profil-sekolah'))->map(fn ($s) => $s['dir'] ?? null)->all();
        foreach (DB::table('pengaturan')->where('sekolah_id', $id)->whereNotNull('gambar')->get(['slot', 'gambar']) as $r) {
            if (! empty($dirPengaturan[$r->slot])) {
                $publik('storage/pengaturan/'.$dirPengaturan[$r->slot].'/'.$r->gambar);
            }
        }
        foreach (DB::table('chat_attachments')->where('sekolah_id', $id)->pluck('path') as $p) {
            $kandidat[Storage::disk('local')->path($p)] = 'berkas/chat/'.ltrim($p, '/');
        }

        $hasil = [];
        foreach ($kandidat as $mutlak => $zip) {
            if ($nyata = self::aman($mutlak)) {
                $hasil[$nyata] = $zip;
            }
        }

        return $hasil;
    }

    /** Jalur nyata bila berkas ada dan berada di dalam folder yang diizinkan, selain itu null. */
    public static function aman(string $mutlak): ?string
    {
        $nyata = realpath($mutlak);
        if ($nyata === false || ! is_file($nyata)) {
            return null;
        }

        foreach ([public_path('storage'), Storage::disk('local')->path('')] as $akar) {
            $akarNyata = realpath($akar);
            if ($akarNyata && str_starts_with($nyata, rtrim($akarNyata, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return $nyata;
            }
        }

        return null;
    }

    /** Total ukuran berkas sekolah (byte), disimpan sebentar di cache karena harus membuka banyak berkas. */
    public static function ukuran(Sekolah $sekolah, bool $segarkan = false): int
    {
        $kunci = "ukuran-berkas:{$sekolah->id}";
        if ($segarkan) {
            Cache::forget($kunci);
        }

        return (int) Cache::remember($kunci, 3600, fn () => array_sum(array_map('filesize', array_keys(self::daftar($sekolah)))));
    }
}
