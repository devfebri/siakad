<?php

namespace App\Http\Middleware;

use App\Peran;
use App\SsoKlien;
use App\Support\Integrasi;
use App\Support\SekolahAktif;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga alamat /oauth/authorize (login tunggal ke E-Learning): klien harus milik sekolah yang sedang dibuka,
 * SSO dinyalakan admin, paket mencakup integrasi, dan yang masuk adalah guru atau siswa.
 * Klien sekolah A tidak bisa dipakai lewat alamat sekolah B.
 */
class CekSso
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('oauth/authorize')) {
            return $next($request);
        }

        $sekolah = SekolahAktif::instance()->get();
        $klien = $sekolah ? SsoKlien::where('client_id', (string) $request->input('client_id'))->first() : null;

        if (! $klien || ! $klien->aktif || ! $klien->sso_aktif || ! Integrasi::tersedia($sekolah)) {
            return $this->tolak('Login tunggal ke E-Learning belum diaktifkan untuk sekolah ini.');
        }

        $user = $request->user();
        if ($user && ! $user->punyaPeran(Peran::GURU, Peran::WALI_KELAS, Peran::SISWA)) {
            return $this->tolak('Login ke E-Learning hanya untuk guru dan siswa.');
        }

        return $next($request);
    }

    private function tolak(string $pesan): Response
    {
        return response()->view('sekolah.tidak-ditemukan', ['judul' => 'Tidak bisa masuk ke E-Learning', 'pesan' => $pesan], 403);
    }
}
