<?php

namespace App\Http\Controllers;

use App\Support\Panduan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Menu Panduan: langkah-langkah pemakaian aplikasi sesuai peran dan paket langganan pengguna. Untuk semua pengguna yang sudah masuk. */
class PanduanController extends Controller
{
    public function index(Request $request): View
    {
        $panel = auth()->user()->panel;
        $semua = Panduan::untukPanel($panel);
        $q = mb_strtolower(trim((string) $request->query('q')));

        $hasil = $q === '' ? $semua : $semua->filter(fn (array $a) => str_contains(mb_strtolower($a['judul'].' '.$a['ringkas'].' '.implode(' ', $a['langkah'])), $q))->values();

        return view('panduan.index', [
            'panel' => $panel,
            'q' => $q,
            'artikel' => $hasil,
            'mulai' => $q === '' ? $semua->where('mulai', true)->values() : collect(),
            'kategori' => config('panduan.kategori'),
            'namaPanel' => \App\User::PANEL_NAMA[$panel] ?? $panel,
        ]);
    }

    public function show(string $slug): View
    {
        $panel = auth()->user()->panel;
        $artikel = Panduan::cari($panel, $slug);
        abort_unless($artikel, 404, 'Panduan tidak ditemukan untuk peran atau paket Anda.');

        return view('panduan.show', [
            'a' => $artikel,
            'panel' => $panel,
            'tautan' => Panduan::tautan($artikel, $panel),
            'serupa' => Panduan::untukPanel($panel)->where('kategori', $artikel['kategori'])->where('slug', '!=', $slug)->values(),
        ]);
    }
}
