<?php

namespace App\Http\Controllers;

use App\Support\DomainKustom;
use App\Support\SekolahAktif;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Menu "Domain Website" untuk admin sekolah: mendaftarkan domain sendiri, melihat instruksi DNS, dan mengecek verifikasi. */
class DomainController extends Controller
{
    public function index(): View
    {
        $sekolah = SekolahAktif::instance()->get();

        return view('domain.index', [
            'sekolah' => $sekolah,
            'instruksi' => DomainKustom::instruksi($sekolah),
            'alamatBawaan' => $this->alamatBawaan($sekolah),
            'cek' => session('hasil_cek_domain'),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $sekolah = SekolahAktif::instance()->get();
        $request->merge(['domain' => DomainKustom::normalkan($request->input('domain'))]);
        $data = $request->validate(['domain' => ['required', 'string', 'max:150']], ['domain.required' => 'Isi nama domain Anda, misalnya sekolahku.sch.id.'], ['domain' => 'domain']);

        try {
            DomainKustom::klaim($sekolah, $data['domain']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['domain' => $e->getMessage()]);
        }

        return redirect()->route('admin/domain')->with('toast_success', 'Domain disimpan. Ikuti langkah di bawah untuk memasangnya.');
    }

    public function periksa(): RedirectResponse
    {
        $hasil = DomainKustom::periksa(SekolahAktif::instance()->get());

        return redirect()->route('admin/domain')->with('hasil_cek_domain', $hasil);
    }

    public function hapus(): RedirectResponse
    {
        DomainKustom::klaim(SekolahAktif::instance()->get(), null);

        return redirect()->route('admin/domain')->with('toast_success', 'Domain sendiri dihapus. Situs kembali memakai alamat bawaan.');
    }

    /** Alamat bawaan situs (subdomain) tanpa memakai domain kustom, supaya selalu ada cara masuk bila DNS bermasalah. */
    private function alamatBawaan($sekolah): string
    {
        $utama = config('sekolah.domain_utama');

        return $utama ? request()->getScheme().'://'.$sekolah->subdomain.'.'.$utama : request()->root().'/?sekolah='.$sekolah->subdomain;
    }
}
