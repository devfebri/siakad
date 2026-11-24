<?php

namespace App\Pembayaran\Driver;

use App\Pembayaran\GerbangException;
use App\Pembayaran\HasilTagihan;
use App\Pembayaran\NotifikasiBayar;
use App\Pembayaran\PaymentGateway;
use App\Pembayaran\PermintaanBayar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Midtrans Snap (VA, QRIS, e-wallet, kartu, dll dipilih pembayar di halaman Midtrans). Mode sandbox memakai server key sandbox. */
class MidtransGateway implements PaymentGateway
{
    public function __construct(private string $serverKey, private bool $sandbox = true)
    {
    }

    public function kode(): string
    {
        return 'midtrans';
    }

    private function basis(): string
    {
        return $this->sandbox ? 'https://app.sandbox.midtrans.com' : 'https://app.midtrans.com';
    }

    public function buatTagihan(PermintaanBayar $p): HasilTagihan
    {
        try {
            $res = Http::withBasicAuth($this->serverKey, '')->acceptJson()->timeout(20)->post($this->basis().'/snap/v1/transactions', [
                'transaction_details' => ['order_id' => $p->orderId, 'gross_amount' => $p->jumlah],
                'item_details' => [['id' => $p->orderId, 'price' => $p->jumlah, 'quantity' => 1, 'name' => mb_substr($p->deskripsi, 0, 50)]],
                'customer_details' => array_filter(['first_name' => mb_substr($p->namaPelanggan, 0, 60), 'email' => $p->emailPelanggan]),
                'callbacks' => ['finish' => $p->urlKembali],
                'expiry' => ['unit' => 'minutes', 'duration' => $p->kedaluwarsaMenit],
            ]);
        } catch (\Throwable) {
            throw new GerbangException('Midtrans tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        if (! $res->successful() || ! $res->json('redirect_url')) {
            throw new GerbangException('Midtrans menolak pembuatan tagihan'.($res->json('error_messages.0') ? ': '.$res->json('error_messages.0') : '.'));
        }

        return new HasilTagihan($res->json('redirect_url'), $res->json('token'), now()->addMinutes($p->kedaluwarsaMenit));
    }

    public function terimaCallback(Request $request): ?NotifikasiBayar
    {
        $d = $request->json()->all() ?: $request->all();
        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_status'] as $k) {
            if (! isset($d[$k]) || ! is_scalar($d[$k])) {
                return null;
            }
        }

        // signature_key = SHA512(order_id + status_code + gross_amount + server_key)
        $harapan = hash('sha512', $d['order_id'].$d['status_code'].$d['gross_amount'].$this->serverKey);
        if (! hash_equals($harapan, (string) $d['signature_key'])) {
            return null;
        }

        $status = match (true) {
            $d['transaction_status'] === 'settlement' => 'berhasil',
            $d['transaction_status'] === 'capture' => ($d['fraud_status'] ?? 'accept') === 'accept' ? 'berhasil' : 'menunggu',
            $d['transaction_status'] === 'pending' => 'menunggu',
            $d['transaction_status'] === 'expire' => 'kedaluwarsa',
            default => 'gagal', // deny, cancel, failure
        };

        return new NotifikasiBayar(
            'midtrans', (string) $d['order_id'], $status, (int) round((float) $d['gross_amount']),
            ($d['transaction_id'] ?? $d['order_id']).'|'.$d['transaction_status'], $d['payment_type'] ?? null,
            array_diff_key($d, ['signature_key' => 1]),
        );
    }
}
