<?php

namespace App\Http\Middleware;

use App\Sekolah;
use App\SsoKlien;
use App\Support\Integrasi;
use App\Support\SekolahAktif;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengaman endpoint yang memakai token pengguna hasil SSO (authorization code): token harus milik klien E-Learning yang aktif,
 * penggunanya harus akun aktif di sekolah klien itu, dan paket sekolah harus masih mencakup integrasi.
 */
class AuthPenggunaApi
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::forgetGuards(); // guard dibuat baru agar pengguna/klien tidak terbawa dari request sebelumnya
        $guard = Auth::guard('api');
        $user = $guard->user();
        $client = $guard->client();

        $klien = $client ? SsoKlien::withoutGlobalScopes()->where('client_id', $client->getKey())->first() : null;
        if (! $user || ! $klien || ! $klien->aktif || ! $klien->sso_aktif) {
            return response()->json(['message' => 'Token tidak sah untuk layanan ini.'], 401);
        }

        $sekolah = Sekolah::find($klien->sekolah_id);
        if (! $sekolah || (int) $user->sekolah_id !== (int) $sekolah->id || $user->aktif === false || ! Integrasi::tersedia($sekolah)) {
            return response()->json(['message' => 'Akses ditolak: akun tidak aktif atau sekolah tidak berlangganan integrasi E-Learning.'], 403);
        }

        SekolahAktif::instance()->set($sekolah);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
