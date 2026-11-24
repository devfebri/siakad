<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Peran;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Login panel Super Admin (/platform). Hanya akun ber-peran super_admin yang dapat masuk. */
class AuthController extends Controller
{
    public function form(): View|RedirectResponse
    {
        return Auth::check() && Auth::user()->punyaPeran(Peran::SUPER_ADMIN)
            ? redirect()->route('platform/dashboard')
            : view('platform.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username', 'password' => 'password']);

        $berhasil = Auth::attempt([
            'username' => mb_strtolower(trim($data['username'])),
            'password' => $data['password'],
            fn ($query) => $query->denganPeran(Peran::SUPER_ADMIN),
        ], $request->boolean('remember'));

        if (! $berhasil) {
            return back()->withInput($request->only('username'))->withErrors(['username' => 'Username atau password salah.']);
        }

        $request->session()->regenerate();
        Auth::user()->forceFill(['login_terakhir_at' => now()])->saveQuietly();
        \App\LogAktivitas::catat('auth.login', 'Masuk ke panel platform.');

        return redirect()->route('platform/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
