<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\LogAktivitas;
use App\Pembayaran\Gerbang;
use App\PengaturanPlatform;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pengaturan Platform: identitas produk, kontak, dan percobaan gratis (trial) untuk pendaftaran mandiri. */
class PengaturanController extends Controller
{
    public function index(): View
    {
        return view('platform.pengaturan.index', [
            'nilai' => [
                'nama_produk' => PengaturanPlatform::get('nama_produk'),
                'kontak_email' => PengaturanPlatform::get('kontak_email', ''),
                'kontak_wa' => PengaturanPlatform::get('kontak_wa', ''),
                'trial_aktif' => PengaturanPlatform::aktif('trial_aktif'),
                'trial_hari' => PengaturanPlatform::get('trial_hari'),
                'backup_terjadwal_aktif' => PengaturanPlatform::aktif('backup_terjadwal_aktif'),
                'backup_simpan_jumlah' => PengaturanPlatform::get('backup_simpan_jumlah', 4),
                'pembayaran_driver' => Gerbang::kodeAktif(),
                'pembayaran_sandbox' => Gerbang::sandbox(),
            ],
            'driver' => array_filter(Gerbang::DRIVER, fn ($k) => $k !== 'fake' || ! app()->isProduction(), ARRAY_FILTER_USE_KEY),
            'kunci' => [
                'midtrans_server_key' => PengaturanPlatform::rahasiaTersembunyi('midtrans_server_key'),
                'xendit_secret_key' => PengaturanPlatform::rahasiaTersembunyi('xendit_secret_key'),
                'xendit_callback_token' => PengaturanPlatform::rahasiaTersembunyi('xendit_callback_token'),
            ],
            'urlCallback' => [
                'midtrans' => url('/api/webhook/pembayaran/midtrans'),
                'xendit' => url('/api/webhook/pembayaran/xendit'),
            ],
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_produk' => ['required', 'string', 'max:60'],
            'kontak_email' => ['nullable', 'email', 'max:150'],
            'kontak_wa' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+ ()-]*$/'],
            'trial_hari' => ['required', 'integer', 'between:1,90'],
            'backup_simpan_jumlah' => ['nullable', 'integer', 'between:1,30'],
            'pembayaran_driver' => ['required', 'in:'.implode(',', array_keys(Gerbang::DRIVER))],
            'midtrans_server_key' => ['nullable', 'string', 'max:200'],
            'xendit_secret_key' => ['nullable', 'string', 'max:200'],
            'xendit_callback_token' => ['nullable', 'string', 'max:200'],
        ], ['kontak_wa.regex' => 'Nomor WhatsApp hanya boleh angka.'], ['nama_produk' => 'nama produk', 'trial_hari' => 'lama percobaan']);

        PengaturanPlatform::put([
            'nama_produk' => trim(strip_tags($data['nama_produk'])),
            'kontak_email' => $data['kontak_email'] ?? '',
            'kontak_wa' => $data['kontak_wa'] ?? '',
            'trial_aktif' => $request->boolean('trial_aktif') ? '1' : '0',
            'trial_hari' => (string) $data['trial_hari'],
            'backup_terjadwal_aktif' => $request->boolean('backup_terjadwal_aktif') ? '1' : '0',
            'backup_simpan_jumlah' => (string) ($data['backup_simpan_jumlah'] ?? PengaturanPlatform::get('backup_simpan_jumlah', 4)),
            'pembayaran_driver' => $data['pembayaran_driver'],
            'pembayaran_sandbox' => $request->boolean('pembayaran_sandbox') ? '1' : '0',
        ]);
        // Kunci dikosongkan = tidak diubah (kunci yang tersimpan tidak pernah ditampilkan utuh).
        foreach (['midtrans_server_key', 'xendit_secret_key', 'xendit_callback_token'] as $kunci) {
            if (filled($data[$kunci] ?? null)) {
                PengaturanPlatform::putRahasia($kunci, trim($data[$kunci]));
            }
        }
        LogAktivitas::catat('platform.pengaturan', 'Pengaturan platform diperbarui (pembayaran: '.$data['pembayaran_driver'].', trial '.($request->boolean('trial_aktif') ? 'aktif '.$data['trial_hari'].' hari' : 'dimatikan').').');

        return redirect()->route('platform/pengaturan')->with('toast_success', 'Pengaturan platform disimpan.');
    }
}
