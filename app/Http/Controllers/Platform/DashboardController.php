<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Invoice;
use App\Langganan;
use App\LogAktivitas;
use App\Sekolah;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $sekolah = Sekolah::all();
        $status = $sekolah->map(fn (Sekolah $s) => $s->statusLangganan());

        return view('platform.dashboard', [
            'ringkas' => [
                'sekolah' => $sekolah->count(),
                'sekolah_aktif' => $sekolah->where('status', 'aktif')->count(),
                'langganan_aktif' => $status->filter(fn ($st) => $st === 'aktif')->count(),
                'tenggang' => $status->filter(fn ($st) => in_array($st, ['tenggang', 'baca_saja'], true))->count(),
                'invoice_menunggu' => Invoice::where('status', 'menunggu')->count(),
                'pendapatan_bulan_ini' => (int) Invoice::where('status', 'lunas')->whereBetween('dibayar_pada', [now()->startOfMonth(), now()->endOfMonth()])->sum('jumlah'),
            ],
            'segeraBerakhir' => Langganan::with(['sekolah:id,nama,subdomain', 'paket:id,nama'])
                ->where('status', 'aktif')->whereBetween('berakhir', [today()->toDateString(), today()->addDays(30)->toDateString()])
                ->orderBy('berakhir')->limit(10)->get(),
            'invoiceTerbaru' => Invoice::with(['sekolah:id,nama', 'paket:id,nama'])->latest('id')->limit(6)->get(),
            'log' => LogAktivitas::with('sekolah:id,nama')->latest('id')->limit(8)->get(),
        ]);
    }
}
