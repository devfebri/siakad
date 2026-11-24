<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function getLogin(): View|RedirectResponse
    {
        return Auth::check() ? redirect(Auth::user()->dashboardUrl()) : view('auths.login');
    }

    public function postLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username', 'password' => 'password']);

        // Username disimpan huruf kecil; login tidak peka huruf besar-kecil.
        $credentials['username'] = mb_strtolower(trim($credentials['username']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('username'))->withErrors(['username' => 'Username atau password salah.']);
        }

        if (Auth::user()->aktif === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withInput($request->only('username'))->withErrors(['username' => 'Akun Anda sudah dinonaktifkan. Hubungi admin sekolah.']);
        }

        $request->session()->regenerate();
        Auth::user()->forceFill(['login_terakhir_at' => now()])->saveQuietly();

        // Akun tanpa peran yang membuka panel (mis. baru dibuat tanpa peran) tidak bisa masuk.
        if (! Auth::user()->panel) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withInput($request->only('username'))->withErrors(['username' => 'Akun Anda belum memiliki peran untuk mengakses panel. Hubungi admin sekolah.']);
        }

        // Masuk karena diminta E-Learning (SSO): lanjutkan permintaan otorisasi yang tertunda, bukan ke dashboard.
        $tujuan = (string) $request->session()->get('url.intended', '');
        if (str_contains($tujuan, '/oauth/authorize?')) {
            $request->session()->forget('url.intended');

            return redirect($tujuan);
        }

        \App\LogAktivitas::catat('auth.login', 'Masuk ke panel '.Auth::user()->panel.'.');

        return redirect(Auth::user()->dashboardUrl());
    }

    /** Pindah panel untuk akun yang punya beberapa peran (mis. admin sekaligus guru). */
    public function gantiPanel(Request $request, string $panel): RedirectResponse
    {
        abort_unless(in_array($panel, $request->user()->panelTersedia(), true), 403, 'Anda tidak memiliki akses ke panel itu.');

        $request->session()->put('panel_aktif', $panel);

        return redirect()->route($panel.'/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        // Sekolah dicatat sebelum sesi dihapus, supaya pengguna kembali ke situs sekolahnya (bukan ke alamat tanpa konteks sekolah).
        $sekolah = SekolahAktif::instance()->get();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $sekolah ? redirect()->away($sekolah->url('/')) : redirect('/');
    }
}
