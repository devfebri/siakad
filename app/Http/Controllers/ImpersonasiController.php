<?php

namespace App\Http\Controllers;

use App\LogAktivitas;
use App\Peran;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login-sebagai admin sekolah oleh Super Admin. Tautan bertanda tangan (5 menit) dibuat di panel platform dan dibuka
 * di alamat situs sekolah, sehingga sesi admin terbentuk di domain sekolah. Setiap penggunaan dicatat di log aktivitas.
 */
class ImpersonasiController extends Controller
{
    public const SESI = 'impersonator_id';

    public function masuk(Request $request): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Tautan tidak valid atau sudah kedaluwarsa.');

        $oleh = User::withoutGlobalScopes()->find((int) $request->query('oleh'));
        abort_unless($oleh && $oleh->punyaPeran(Peran::SUPER_ADMIN), 403, 'Akses ditolak.');

        // Pencarian terikat sekolah dari alamat ini: admin sekolah lain tidak bisa dimasuki lewat alamat sekolah ini.
        $admin = User::find((int) $request->query('user'));
        abort_unless($admin && $admin->punyaPeran(Peran::ADMIN), 404);

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put(self::SESI, $oleh->id);

        LogAktivitas::create([
            'user_id' => $oleh->id, 'sekolah_id' => $admin->sekolah_id, 'aksi' => 'impersonasi.masuk',
            'deskripsi' => "Super admin {$oleh->username} masuk sebagai {$admin->username}.", 'ip' => $request->ip(),
        ]);

        return redirect()->route('admin/dashboard');
    }

    public function keluar(Request $request): RedirectResponse
    {
        $oleh = $request->session()->get(self::SESI);
        abort_unless($oleh, 403);

        LogAktivitas::create([
            'user_id' => $oleh, 'sekolah_id' => auth()->user()?->sekolah_id, 'aksi' => 'impersonasi.keluar',
            'deskripsi' => 'Super admin keluar dari login-sebagai '.auth()->user()?->username.'.', 'ip' => $request->ip(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away(config('sekolah.platform_url') ?: url('/platform/login'));
    }
}
