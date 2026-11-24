<?php

namespace App\Http\Controllers;

use App\Pembayaran\Driver\FakeGateway;
use App\Pembayaran\Gerbang;
use App\Pembayaran\Sewa;
use App\Pembayaran\SppOnline;
use App\PembayaranGateway;
use App\PengaturanPembayaranSekolah;
use App\TransaksiSpp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Halaman bayar tiruan untuk gerbang "Simulasi" (lokal/uji): memilih hasil lalu mengirim callback bertanda tangan ke aplikasi sendiri. */
class PembayaranSimulasiController extends Controller
{
    public function tampil(string $orderId): View
    {
        abort_unless(Gerbang::driver('fake'), 404);

        return view('pembayaran.simulasi', ['bayar' => PembayaranGateway::where('order_id', $orderId)->where('driver', 'fake')->firstOrFail()]);
    }

    public function kirim(Request $request, string $orderId): RedirectResponse
    {
        abort_unless(Gerbang::driver('fake'), 404);
        $bayar = PembayaranGateway::where('order_id', $orderId)->where('driver', 'fake')->with('invoice.sekolah')->firstOrFail();
        $status = $request->validate(['status' => ['required', 'in:berhasil,gagal,kedaluwarsa']])['status'];

        $callback = Request::create('/', 'POST', ['order_id' => $bayar->order_id, 'status' => $status, 'jumlah' => (int) $bayar->jumlah, 'nonce' => bin2hex(random_bytes(4))], [], [], [
            'HTTP_X_FAKE_SIGNATURE' => FakeGateway::tandatangani($bayar->order_id, $status, (int) $bayar->jumlah),
        ]);
        Sewa::terima('fake', $callback);

        return redirect()->away($bayar->invoice->sekolah->url('/admin/langganan?bayar=kembali'));
    }

    // ------------------------------------------------------------ SPP online (gerbang simulasi milik sekolah)

    public function tampilSpp(string $orderId): View
    {
        $t = TransaksiSpp::where('order_id', $orderId)->where('driver', 'fake')->firstOrFail();
        abort_unless(PengaturanPembayaranSekolah::withoutGlobalScopes()->where('sekolah_id', $t->sekolah_id)->first()?->gerbang('fake'), 404);

        return view('pembayaran.simulasi', ['bayar' => (object) ['jumlah' => $t->total, 'order_id' => $t->order_id, 'invoice' => (object) ['nomor' => 'SPP Online']], 'aksi' => route('pembayaran.simulasi-spp.kirim', $t->order_id)]);
    }

    public function kirimSpp(Request $request, string $orderId): RedirectResponse
    {
        $t = TransaksiSpp::where('order_id', $orderId)->where('driver', 'fake')->with('sekolah')->firstOrFail();
        $pg = PengaturanPembayaranSekolah::withoutGlobalScopes()->where('sekolah_id', $t->sekolah_id)->first();
        abort_unless($pg?->gerbang('fake'), 404);
        $status = $request->validate(['status' => ['required', 'in:berhasil,gagal,kedaluwarsa']])['status'];

        $callback = Request::create('/', 'POST', ['order_id' => $t->order_id, 'status' => $status, 'jumlah' => (int) $t->total, 'nonce' => bin2hex(random_bytes(4))], [], [], [
            'HTTP_X_FAKE_SIGNATURE' => FakeGateway::tandatangani($t->order_id, $status, (int) $t->total),
        ]);
        SppOnline::terima($pg, 'fake', $callback);

        return redirect()->away($t->sekolah->url('/ortu/tagihan?bayar=kembali'));
    }
}
