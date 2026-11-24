<?php

namespace App\Support;

use App\Invoice;
use App\PengaturanPlatform;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDokumen;

/** Invoice dan kuitansi sewa dalam PDF. Penerbit: platform; penerima: sekolah penyewa. */
class SewaPdf
{
    public static function invoice(Invoice $invoice): PdfDokumen
    {
        return self::buat($invoice, false);
    }

    /** Kuitansi hanya untuk invoice yang sudah lunas. */
    public static function kuitansi(Invoice $invoice): PdfDokumen
    {
        abort_unless($invoice->status === 'lunas', 404, 'Kuitansi hanya tersedia untuk invoice yang sudah lunas.');

        return self::buat($invoice, true);
    }

    public static function namaBerkas(Invoice $invoice, bool $kuitansi): string
    {
        return ($kuitansi ? 'kuitansi-' : 'invoice-').$invoice->nomor.'.pdf';
    }

    private static function buat(Invoice $invoice, bool $kuitansi): PdfDokumen
    {
        $invoice->loadMissing(['sekolah', 'paket', 'langganan']);

        return Pdf::loadView('pdf.sewa', [
            'invoice' => $invoice,
            'kuitansi' => $kuitansi,
            'produk' => PengaturanPlatform::get('nama_produk'),
            'kontak' => collect([PengaturanPlatform::get('kontak_email'), PengaturanPlatform::get('kontak_wa')])->filter()->implode(' · '),
            'admin' => $invoice->sekolah?->adminPertama(),
            'warna' => Tema::warna(),
            'terbilang' => Terbilang::rupiah((int) $invoice->jumlah),
            'petunjuk' => config('sekolah.petunjuk_pembayaran'),
        ])->setPaper('a4');
    }
}
