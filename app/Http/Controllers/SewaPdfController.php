<?php

namespace App\Http\Controllers;

use App\Invoice;
use App\Support\SekolahAktif;
use App\Support\SewaPdf;
use Illuminate\Http\Response;

/** Unduh invoice dan kuitansi sewa (PDF). Admin sekolah hanya untuk invoice sekolahnya; Super Admin untuk semua. */
class SewaPdfController extends Controller
{
    public function invoice(Invoice $invoice): Response
    {
        $this->otorisasi($invoice);

        return SewaPdf::invoice($invoice)->download(SewaPdf::namaBerkas($invoice, false));
    }

    public function kuitansi(Invoice $invoice): Response
    {
        $this->otorisasi($invoice);

        return SewaPdf::kuitansi($invoice)->download(SewaPdf::namaBerkas($invoice, true));
    }

    private function otorisasi(Invoice $invoice): void
    {
        $sekolahId = SekolahAktif::instance()->id();
        abort_if($sekolahId !== null && (int) $invoice->sekolah_id !== $sekolahId, 404);
    }
}
