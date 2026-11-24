<?php

namespace App\Support;

use App\PembayaranSpp;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDokumen;

/** Kuitansi pembayaran SPP dalam PDF dengan kop sekolah. */
class SppPdf
{
    public static function kuitansi(PembayaranSpp $p): PdfDokumen
    {
        $p->loadMissing(['tagihan.siswa.kelas', 'penerima']);
        $logo = config('school.logo') ? public_path(config('school.logo')) : null;

        return Pdf::loadView('pdf.kuitansi-spp', [
            'p' => $p,
            'sekolah' => config('school.name'),
            'alamat' => config('school.alamat'),
            'kontak' => collect([config('school.telepon'), config('school.email')])->filter()->implode(' · '),
            'warna' => Tema::warna(),
            'logo' => $logo && is_file($logo) ? $logo : null,
            'terbilang' => Terbilang::rupiah((int) $p->jumlah),
        ])->setPaper('a5', 'landscape');
    }

    public static function namaBerkas(PembayaranSpp $p): string
    {
        return 'kuitansi-'.$p->nomor.'.pdf';
    }
}
