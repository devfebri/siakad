<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Akun yang dinonaktifkan (lulus, mutasi keluar) langsung keluar dari sesi yang masih berjalan. */
class TolakAkunNonaktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->aktif === false && ! $request->session()->has('impersonator_id')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'Akun Anda sudah dinonaktifkan.');
            }

            return redirect()->route('login')->withErrors(['username' => 'Akun Anda sudah dinonaktifkan. Hubungi admin sekolah.']);
        }

        return $next($request);
    }
}
