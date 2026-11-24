<?php

namespace App\Pembayaran;

use Illuminate\Http\Request;

/**
 * Kontrak gerbang pembayaran. Dipakai untuk sewa platform (kunci milik platform) dan SPP online (kunci milik tiap sekolah).
 * Driver: Midtrans, Xendit, dan Fake (simulasi lokal/uji). Semua panggilan keluar lewat Http client Laravel sehingga bisa di-fake di tes.
 */
interface PaymentGateway
{
    /** Kode driver: midtrans | xendit | fake. */
    public function kode(): string;

    /** Buat tagihan/halaman bayar di gerbang. @throws GerbangException bila gerbang menolak atau tidak terjangkau */
    public function buatTagihan(PermintaanBayar $permintaan): HasilTagihan;

    /**
     * Periksa dan urai callback dari gerbang. Tanda tangan/token WAJIB diverifikasi di sini.
     *
     * @return NotifikasiBayar|null null bila tanda tangan salah atau isi tidak dikenali
     */
    public function terimaCallback(Request $request): ?NotifikasiBayar;
}
