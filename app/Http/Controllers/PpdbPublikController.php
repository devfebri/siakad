<?php

namespace App\Http\Controllers;

use App\PpdbPendaftar;
use App\Support\Ppdb;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Halaman PPDB untuk calon siswa: informasi, formulir pendaftaran, dan cek status. */
class PpdbPublikController extends Controller
{
    public function index(): View
    {
        return view('public.ppdb', [
            'status' => Ppdb::status(),
            'persyaratan' => Ppdb::persyaratan(),
            'jalur' => PpdbPendaftar::JALUR,
            'peminatan' => PpdbPendaftar::PEMINATAN,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $status = Ppdb::status();
        if (! $status['bisa_daftar']) {
            return back()->withInput()->with('error', 'Pendaftaran PPDB sedang tidak dibuka.');
        }

        [$aturan, $pesan, $atribut] = PpdbPendaftar::aturan();
        $data = $request->validate($aturan, $pesan, $atribut);

        if ($request->hasFile('berkas')) {
            $file = $request->file('berkas');
            $name = Str::random(24).'.'.$file->extension();
            $file->move(public_path('storage/ppdb'), $name);
            $data['berkas'] = 'storage/ppdb/'.$name;
        }

        $pendaftar = PpdbPendaftar::daftar($data + ['tahun_ajaran' => $status['tahun_ajaran']]);

        return redirect()->route('ppdb.sukses')->with('ppdb_no', $pendaftar->no_pendaftaran)->with('ppdb_nama', $pendaftar->nama);
    }

    public function sukses(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('ppdb_no')) {
            return redirect()->route('ppdb');
        }

        return view('public.ppdb-sukses', [
            'no' => $request->session()->get('ppdb_no'),
            'nama' => $request->session()->get('ppdb_nama'),
        ]);
    }

    public function cek(Request $request): View
    {
        $hasil = null;
        $dicari = false;

        if ($request->isMethod('post')) {
            $dicari = true;
            $data = $request->validate([
                'no_pendaftaran' => ['required', 'string', 'max:30'],
                'tanggal_lahir' => ['required', 'date'],
            ], [], ['no_pendaftaran' => 'nomor pendaftaran', 'tanggal_lahir' => 'tanggal lahir']);

            // Nomor + tanggal lahir harus cocok, supaya data tidak bisa ditebak lewat nomor berurutan.
            $hasil = PpdbPendaftar::where('no_pendaftaran', strtoupper(trim($data['no_pendaftaran'])))
                ->whereDate('tanggal_lahir', $data['tanggal_lahir'])->first();
        }

        return view('public.ppdb-cek', ['hasil' => $hasil, 'dicari' => $dicari]);
    }
}
