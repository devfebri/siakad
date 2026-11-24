<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Invoice;
use App\Paket;
use App\Sekolah;
use App\Support\DataTableResponse;
use App\Support\Penyewaan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Panel platform: invoice sewa, pembuatan, konfirmasi bayar manual, dan pembatalan. */
class InvoiceController extends Controller
{
    public function index(): View
    {
        return view('platform.invoice.index', [
            'sekolah' => Sekolah::orderBy('nama')->get(['id', 'nama']),
            'paket' => Paket::with('harga')->where('aktif', true)->orderBy('urutan')->get(),
            'durasi' => Paket::DURASI,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['sekolah:id,nama', 'paket:id,nama']);
        if (in_array($status = $request->input('status'), array_keys(Invoice::STATUS), true)) {
            $query->where('status', $status);
        }

        return DataTableResponse::make(
            $request,
            $query,
            searchable: ['nomor'],
            orderable: ['nomor', 'jumlah', 'created_at'],
            map: fn (Invoice $i) => [
                'id' => $i->id,
                'nomor' => $i->nomor,
                'sekolah' => $i->sekolah?->nama ?? '-',
                'paket' => ($i->paket?->nama ?? '-').' · '.$i->durasi_bulan.' bln',
                'jumlah' => 'Rp '.number_format($i->jumlah, 0, ',', '.'),
                'jatuh_tempo' => $i->jatuh_tempo?->translatedFormat('d M Y') ?? '-',
                'status_raw' => $i->status,
                'status' => (Invoice::STATUS[$i->status] ?? $i->status).'|'.(Invoice::WARNA[$i->status] ?? 'muted'),
                'tanggal' => $i->created_at?->translatedFormat('d M Y') ?? '-',
            ],
            searchRelations: ['sekolah' => ['nama']],
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sekolah_id' => ['required', 'integer', Rule::exists('sekolah', 'id')],
            'paket_id' => ['required', 'integer', Rule::exists('paket', 'id')],
            'durasi_bulan' => ['required', 'integer', Rule::in(Paket::DURASI)],
            'catatan' => ['nullable', 'string', 'max:300'],
        ], [], ['sekolah_id' => 'sekolah', 'paket_id' => 'paket', 'durasi_bulan' => 'durasi']);

        $invoice = Penyewaan::buatInvoice(Sekolah::findOrFail($data['sekolah_id']), Paket::with('harga')->findOrFail($data['paket_id']), (int) $data['durasi_bulan'], $data['catatan'] ?? null);

        return response()->json(['message' => "Invoice {$invoice->nomor} dibuat."], 201);
    }

    /** Konfirmasi pembayaran manual: invoice lunas dan langganan dibuat/diperpanjang. */
    public function lunas(Request $request, Invoice $invoice): JsonResponse
    {
        $data = $request->validate(['metode' => ['nullable', 'string', 'max:60'], 'catatan' => ['nullable', 'string', 'max:300']]);
        $langganan = Penyewaan::konfirmasiLunas($invoice, $data['metode'] ?? null, $data['catatan'] ?? null);

        return response()->json(['message' => "Invoice {$invoice->nomor} lunas. Langganan aktif sampai {$langganan->berakhir->translatedFormat('d F Y')}."]);
    }

    public function batal(Invoice $invoice): JsonResponse
    {
        Penyewaan::batalkanInvoice($invoice);

        return response()->json(['message' => "Invoice {$invoice->nomor} dibatalkan."]);
    }
}
