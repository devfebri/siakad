<?php

namespace App\Http\Controllers\Platform;

use App\Backup\BerkasSekolah;
use App\Http\Controllers\Controller;
use App\Sekolah;
use App\Support\Pemantauan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Panel platform: pemantauan penggunaan tiap sekolah (siswa, guru, penyimpanan, login terakhir, request API, status langganan). Hanya baca. */
class PemantauanController extends Controller
{
    public function index(Request $request): View
    {
        $baris = Pemantauan::semua($request->input('cari'), $request->input('status'));

        return view('platform.pemantauan.index', [
            'baris' => $baris,
            'total' => ['siswa' => $baris->sum('siswa'), 'guru' => $baris->sum('guru'), 'ukuran' => $baris->sum('ukuran'), 'api' => $baris->sum('api_30_hari')],
            'cari' => $request->input('cari'),
            'status' => $request->input('status'),
        ]);
    }

    /** Hitung ulang ukuran penyimpanan (satu sekolah atau semua). */
    public function segarkan(Request $request): RedirectResponse
    {
        $sekolah = $request->filled('sekolah_id') ? Sekolah::whereKey($request->input('sekolah_id'))->get() : Sekolah::all();
        foreach ($sekolah as $s) {
            BerkasSekolah::ukuran($s, true);
        }

        return redirect()->route('platform/pemantauan')->with('toast_success', 'Ukuran penyimpanan dihitung ulang.');
    }
}
