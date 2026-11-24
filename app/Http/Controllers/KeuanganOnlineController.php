<?php

namespace App\Http\Controllers;

use App\LogAktivitas;
use App\PengaturanPembayaranSekolah;
use App\TransaksiSpp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pengaturan pembayaran SPP online milik sekolah (khusus admin): gerbang, kunci, dan biaya admin. Kunci tidak pernah tampil utuh. */
class KeuanganOnlineController extends Controller
{
    public function index(): View
    {
        $pg = PengaturanPembayaranSekolah::milikSekolahAktif();

        return view('keuangan.online', [
            'pg' => $pg,
            'driver' => array_filter(PengaturanPembayaranSekolah::DRIVER, fn ($k) => $k !== 'fake' || ! app()->isProduction(), ARRAY_FILTER_USE_KEY),
            'kunci' => ['midtrans_server_key' => $pg->tersembunyi('midtrans_server_key'), 'xendit_secret_key' => $pg->tersembunyi('xendit_secret_key'), 'xendit_callback_token' => $pg->tersembunyi('xendit_callback_token')],
            'siap' => $pg->gerbangAktif() !== null,
            'transaksiTerakhir' => TransaksiSpp::latest('id')->limit(8)->get(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $pg = PengaturanPembayaranSekolah::milikSekolahAktif();
        $data = $request->validate([
            'driver' => ['required', Rule::in(array_keys(PengaturanPembayaranSekolah::DRIVER))],
            'midtrans_server_key' => ['nullable', 'string', 'max:200'],
            'xendit_secret_key' => ['nullable', 'string', 'max:200'],
            'xendit_callback_token' => ['nullable', 'string', 'max:200'],
            'biaya_admin_ditanggung' => ['required', Rule::in(array_keys(PengaturanPembayaranSekolah::DITANGGUNG))],
            'biaya_admin_tetap' => ['nullable', 'integer', 'between:0,100000'],
            'biaya_admin_persen' => ['nullable', 'numeric', 'between:0,10'],
        ], [], ['biaya_admin_tetap' => 'biaya admin tetap', 'biaya_admin_persen' => 'biaya admin persen']);

        $pg->fill([
            'driver' => $data['driver'], 'sandbox' => $request->boolean('sandbox'),
            'biaya_admin_ditanggung' => $data['biaya_admin_ditanggung'],
            'biaya_admin_tetap' => (int) ($data['biaya_admin_tetap'] ?? 0), 'biaya_admin_persen' => (float) ($data['biaya_admin_persen'] ?? 0),
        ]);
        // kolom kunci kosong = kunci yang tersimpan tidak diubah
        foreach (['midtrans_server_key', 'xendit_secret_key', 'xendit_callback_token'] as $kolom) {
            if (filled($data[$kolom] ?? null)) {
                $pg->{$kolom} = trim($data[$kolom]);
            }
        }

        if ($pg->driver !== 'nonaktif' && ! $pg->gerbang($pg->driver)) {
            return back()->withInput()->withErrors(['driver' => 'Kunci '.PengaturanPembayaranSekolah::DRIVER[$pg->driver].' belum lengkap. Isi kunci di bawah, atau pilih "Nonaktif".']);
        }
        if ($pg->driver === 'xendit' && ! $pg->xendit_callback_token) {
            return back()->withInput()->withErrors(['xendit_callback_token' => 'Isi Callback Verification Token dari dashboard Xendit.']);
        }

        $pg->save();
        LogAktivitas::catat('keuangan.online', 'Pengaturan pembayaran online SPP diperbarui (gerbang: '.$pg->driver.', biaya admin: '.$pg->biaya_admin_ditanggung.').');

        return redirect()->route('admin/keuangan/online')->with('toast_success', $pg->driver === 'nonaktif' ? 'Pembayaran online dimatikan.' : 'Pengaturan disimpan. Orang tua sekarang bisa membayar SPP online.');
    }
}
