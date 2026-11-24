<?php

namespace App\Http\Middleware;

use App\Sekolah;
use App\Support\SekolahAktif;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengenali sekolah dari alamat yang diakses dan mengisi konteks tenant (SekolahAktif).
 * Urutan: ?sekolah= (bila diizinkan) -> domain kustom -> subdomain dari domain utama -> sekolah bawaan.
 * Alamat /platform* (panel super admin) tidak terikat sekolah.
 */
class IdentifikasiSekolah
{
    private const SESI = 'sekolah_override';

    public function handle(Request $request, Closure $next): Response
    {
        $konteks = SekolahAktif::instance();

        // Panel platform, halaman produk, dan pendaftaran sekolah tidak terikat sekolah. Domain utama platform juga hanya melayani itu.
        if ($request->is('platform', 'platform/*', 'produk', 'daftar', 'daftar/*', 'pembayaran/*', '_domain/*') || self::hostUtama($request)) {
            $konteks->set(null);

            return $next($request);
        }

        $sekolah = $this->cari($request);

        if (! $sekolah) {
            return response()->view('sekolah.tidak-ditemukan', ['judul' => 'Sekolah tidak ditemukan', 'pesan' => 'Alamat ini belum terdaftar sebagai situs sekolah.'], 404);
        }

        if (! $sekolah->isAktif()) {
            return response()->view('sekolah.tidak-ditemukan', ['judul' => 'Situs tidak aktif', 'pesan' => 'Situs sekolah ini sedang dinonaktifkan. Hubungi pengelola sekolah.'], 403);
        }

        $konteks->set($sekolah);
        View::share('sekolahAktif', $sekolah);

        return $next($request);
    }

    /** Host sama persis dengan domain utama platform (atau www-nya): halaman produk, bukan situs sekolah. */
    public static function hostUtama(Request $request): bool
    {
        $utama = strtolower((string) config('sekolah.domain_utama'));

        return $utama !== '' && in_array(strtolower($request->getHost()), [$utama, 'www.'.$utama], true);
    }

    private function cari(Request $request): ?Sekolah
    {
        // 1. override ?sekolah= (hanya lokal/uji), diingat di sesi agar tautan berikutnya tetap di sekolah yang sama
        if (config('sekolah.izinkan_query')) {
            $kode = $request->query('sekolah') ?: ($request->hasSession() ? $request->session()->get(self::SESI) : null);
            if (is_string($kode) && $kode !== '') {
                $sekolah = Sekolah::where('subdomain', strtolower($kode))->first();
                if ($sekolah && $request->hasSession() && $request->query('sekolah')) {
                    $request->session()->put(self::SESI, $sekolah->subdomain);
                }

                // Kode sekolah yang diminta tetapi tidak ada: jangan diam-diam jatuh ke sekolah lain.
                return $sekolah;
            }
        }

        $host = strtolower($request->getHost());
        $hostTanpaWww = preg_replace('/^www\./', '', $host);

        // 2. domain kustom
        if ($sekolah = Sekolah::where('domain_kustom', $hostTanpaWww)->whereNotNull('domain_terverifikasi_pada')->first()) {
            return $sekolah;
        }

        // 3. subdomain dari domain utama (smpn1.siakad.id -> smpn1)
        $utama = strtolower((string) config('sekolah.domain_utama'));
        if ($utama !== '' && str_ends_with($host, '.'.$utama)) {
            $sub = substr($host, 0, -strlen('.'.$utama));

            // Di bawah domain utama hanya subdomain tunggal yang sah; selain itu tidak jatuh ke sekolah bawaan.
            return ($sub !== '' && ! str_contains($sub, '.')) ? Sekolah::where('subdomain', $sub)->first() : null;
        }

        // 4. sekolah bawaan; tanpa pengaturan bawaan dan hanya ada satu sekolah, pakai sekolah itu
        if ($bawaan = config('sekolah.default_subdomain')) {
            return Sekolah::where('subdomain', strtolower($bawaan))->first();
        }

        return Sekolah::count() === 1 ? Sekolah::first() : null;
    }
}
