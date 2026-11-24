<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Membatasi route untuk peran tertentu (cukup punya salah satu): checkRole:admin atau checkRole:guru,wali_kelas. */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        if ($user && $user->punyaPeran(...$peran)) {
            return $next($request);
        }

        // Permintaan AJAX/JSON mendapat 403; halaman biasa dikembalikan ke panel miliknya (atau beranda bila belum punya panel).
        if ($request->expectsJson() || ! $user) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        $panel = $user->panel;

        return redirect($panel ? route($panel.'/dashboard') : url('/'))->with('toast_error', 'Anda tidak memiliki akses ke halaman itu.');
    }
}
