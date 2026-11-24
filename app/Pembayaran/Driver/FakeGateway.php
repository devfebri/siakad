<?php

namespace App\Pembayaran\Driver;

use App\Pembayaran\HasilTagihan;
use App\Pembayaran\NotifikasiBayar;
use App\Pembayaran\PaymentGateway;
use App\Pembayaran\PermintaanBayar;
use Illuminate\Http\Request;

/**
 * Gerbang palsu untuk lokal dan tes: tidak menghubungi layanan apa pun. Halaman bayarnya adalah halaman simulasi di aplikasi sendiri.
 * Callback ditandatangani HMAC dengan APP_KEY, jadi tetap melewati jalur verifikasi yang sama dengan gerbang sungguhan.
 */
class FakeGateway implements PaymentGateway
{
    /** @param string $rute nama route halaman simulasi (sewa atau SPP) */
    public function __construct(private string $rute = 'pembayaran.simulasi')
    {
    }

    public function kode(): string
    {
        return 'fake';
    }

    public function buatTagihan(PermintaanBayar $p): HasilTagihan
    {
        return new HasilTagihan(route($this->rute, ['orderId' => $p->orderId]), 'FAKE-'.$p->orderId, now()->addMinutes($p->kedaluwarsaMenit));
    }

    public static function tandatangani(string $orderId, string $status, int $jumlah): string
    {
        return hash_hmac('sha256', "{$orderId}|{$status}|{$jumlah}", (string) config('app.key'));
    }

    public function terimaCallback(Request $request): ?NotifikasiBayar
    {
        $d = $request->json()->all() ?: $request->all();
        if (! isset($d['order_id'], $d['status'], $d['jumlah']) || ! in_array($d['status'], NotifikasiBayar::STATUS, true)) {
            return null;
        }

        $sah = hash_equals(self::tandatangani((string) $d['order_id'], (string) $d['status'], (int) $d['jumlah']), (string) $request->header('X-Fake-Signature'));
        if (! $sah) {
            return null;
        }

        return new NotifikasiBayar('fake', (string) $d['order_id'], $d['status'], (int) $d['jumlah'], $d['order_id'].'|'.$d['status'].'|'.($d['nonce'] ?? ''), 'simulasi', $d);
    }
}
