<?php

namespace App\Http\Controllers;

use App\Support\Integrasi;
use Illuminate\Http\RedirectResponse;

/** Tombol "Buka E-Learning" untuk guru dan siswa: meneruskan ke aplikasi E-Learning sekolah (yang kemudian meminta login tunggal). */
class ElearningController extends Controller
{
    public function buka(): RedirectResponse
    {
        $url = Integrasi::alamatElearning();
        abort_unless($url && auth()->user()->punyaPeran(\App\Peran::GURU, \App\Peran::WALI_KELAS, \App\Peran::SISWA), 404);

        return redirect()->away($url);
    }
}
