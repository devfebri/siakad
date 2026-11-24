<?php

namespace App\Http\Controllers;

use App\Modul;
use App\Paket;
use App\PengaturanPlatform;
use App\Sekolah;
use App\Support\Pendaftaran;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Halaman produk (landing) dan pendaftaran sekolah mandiri. Tidak terikat sekolah. */
class ProdukController extends Controller
{
    public function landing(): View
    {
        return view('produk.landing', [
            'paket' => Paket::with(['harga', 'modul'])->where('aktif', true)->orderBy('urutan')->orderBy('id')->get(),
            'modul' => Modul::orderBy('id')->get(),
            'trial' => Pendaftaran::trialDitawarkan() ? Pendaftaran::trialHari() : 0,
            'kontak' => ['email' => PengaturanPlatform::get('kontak_email'), 'wa' => PengaturanPlatform::get('kontak_wa')],
            'produk' => PengaturanPlatform::get('nama_produk'),
            'fitur' => config('landing.fitur'),
            'faq' => config('landing.faq'),
        ]);
    }

    public function formDaftar(Request $request): View
    {
        return view('produk.daftar', [
            'paket' => Paket::with(['harga', 'modul'])->where('aktif', true)->orderBy('urutan')->orderBy('id')->get(),
            'trial' => Pendaftaran::trialDitawarkan() ? Pendaftaran::trialHari() : 0,
            'produk' => PengaturanPlatform::get('nama_produk'),
            'pilihPaket' => (int) $request->query('paket'),
            'domainUtama' => config('sekolah.domain_utama') ?: 'domain-utama',
            'jenjang' => Sekolah::JENJANG,
        ]);
    }

    /** Cek ketersediaan alamat sekolah (untuk tampilan langsung saat mengetik). */
    public function cekSubdomain(Request $request): JsonResponse
    {
        $s = mb_strtolower(trim((string) $request->query('s')));
        $v = Validator::make(['s' => $s], ['s' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/']]);
        if ($v->fails()) {
            return response()->json(['tersedia' => false, 'pesan' => 'Gunakan huruf kecil, angka, dan strip saja (minimal 2 huruf).']);
        }

        $bebas = Pendaftaran::subdomainBebas($s);

        return response()->json(['tersedia' => $bebas, 'pesan' => $bebas ? 'Alamat ini masih tersedia.' : 'Alamat ini sudah dipakai atau tidak bisa dipakai.']);
    }

    public function simpan(Request $request): RedirectResponse
    {
        // Kotak jebakan untuk robot: manusia tidak melihat dan tidak mengisinya.
        if ($request->filled('website')) {
            return redirect()->route('daftar.cek-email');
        }

        $request->merge([
            'subdomain' => mb_strtolower(trim((string) $request->input('subdomain'))),
            'admin_username' => mb_strtolower(trim((string) $request->input('admin_username'))),
        ]);
        $data = $request->validate(Pendaftaran::aturan(), Pendaftaran::pesan(), Pendaftaran::atribut());

        $p = Pendaftaran::mulai($data, $request->ip());

        return redirect()->route('daftar.cek-email')->with('email_pendaftaran', $p->email);
    }

    public function cekEmail(): View
    {
        return view('produk.cek-email', ['email' => session('email_pendaftaran'), 'produk' => PengaturanPlatform::get('nama_produk')]);
    }

    public function verifikasi(string $token): View
    {
        $hasil = Pendaftaran::verifikasi($token);

        return view('produk.selesai', ['hasil' => $hasil, 'produk' => PengaturanPlatform::get('nama_produk')]);
    }
}
