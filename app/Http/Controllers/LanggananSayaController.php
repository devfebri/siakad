<?php

namespace App\Http\Controllers;

use App\Invoice;
use App\Modul;
use App\Paket;
use App\Pembayaran\Gerbang;
use App\Pembayaran\GerbangException;
use App\Pembayaran\Sewa;
use App\Support\AksesSekolah;
use App\Support\Penyewaan;
use App\Support\SekolahAktif;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Menu "Langganan Saya" untuk admin sekolah: status sewa, modul paket, riwayat, dan pengajuan perpanjangan/upgrade (invoice). */
class LanggananSayaController extends Controller
{
    public function index(): View
    {
        $sekolah = SekolahAktif::instance()->get();
        $akses = AksesSekolah::ambil();

        return view('langganan.index', [
            'sekolah' => $sekolah,
            'akses' => $akses,
            'terkini' => $sekolah->langgananTerkini(),
            'riwayat' => $sekolah->langganan()->with('paket:id,nama')->orderByDesc('berakhir')->get(),
            'invoice' => Invoice::where('sekolah_id', $sekolah->id)->with('paket:id,nama')->latest('id')->get(),
            'paket' => Paket::with(['harga', 'modul'])->where('aktif', true)->orderBy('urutan')->get(),
            'modul' => Modul::orderBy('id')->get(),
            'petunjukBayar' => config('sekolah.petunjuk_pembayaran'),
            'bayarOnline' => Gerbang::aktif() !== null,
            'baruKembaliDariBayar' => request()->query('bayar') === 'kembali',
        ]);
    }

    /** Bayar invoice lewat gerbang pembayaran: membuat (atau melanjutkan) tagihan lalu membuka halaman bayar. */
    public function bayar(Invoice $invoice): RedirectResponse
    {
        abort_unless((int) $invoice->sekolah_id === SekolahAktif::instance()->id(), 404);

        try {
            $bayar = Sewa::mulai($invoice->loadMissing('sekolah', 'paket'), auth()->user()->name, auth()->user()->email);
        } catch (GerbangException $e) {
            return redirect()->route('admin/langganan')->with('toast_error', $e->getMessage());
        }

        return redirect()->away($bayar->url_bayar);
    }

    /** Ajukan perpanjangan atau perubahan paket: membuat invoice yang menunggu pembayaran (dikonfirmasi pengelola platform). */
    public function ajukan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'paket_id' => ['required', 'integer', Rule::exists('paket', 'id')->where('aktif', true)],
            'durasi_bulan' => ['required', 'integer', Rule::in(Paket::DURASI)],
        ], [], ['paket_id' => 'paket', 'durasi_bulan' => 'durasi']);

        $sekolah = SekolahAktif::instance()->get();
        $kembar = Invoice::where('sekolah_id', $sekolah->id)->where('status', 'menunggu')->where('paket_id', $data['paket_id'])->where('durasi_bulan', $data['durasi_bulan'])->exists();
        if ($kembar) {
            return response()->json(['message' => 'Pengajuan yang sama masih menunggu pembayaran. Lihat daftar invoice di bawah.'], 409);
        }

        $invoice = Penyewaan::buatInvoice($sekolah, Paket::with('harga')->findOrFail($data['paket_id']), (int) $data['durasi_bulan'], 'Diajukan oleh '.auth()->user()->name);

        return response()->json(['message' => "Invoice {$invoice->nomor} dibuat. Lakukan pembayaran lalu hubungi pengelola untuk konfirmasi.", 'redirect' => route('admin/langganan')], 201);
    }
}
