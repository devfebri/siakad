<?php

namespace App\Pembayaran\Driver;

use App\Pembayaran\GerbangException;
use App\Pembayaran\HasilTagihan;
use App\Pembayaran\NotifikasiBayar;
use App\Pembayaran\PaymentGateway;
use App\Pembayaran\PermintaanBayar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Xendit Invoice (VA, QRIS, e-wallet, retail). Sandbox = memakai kunci rahasia "development" dari dashboard Xendit.
 * Callback diverifikasi dengan header x-callback-token yang harus sama dengan Callback Verification Token di dashboard.
 */
class XenditGateway implements PaymentGateway
{
    public function __construct(private string $secretKey, private string $callbackToken)
    {
    }

    public function kode(): string
    {
        return 'xendit';
    }

    public function buatTagihan(PermintaanBayar $p): HasilTagihan
    {
        try {
            $res = Http::withBasicAuth($this->secretKey, '')->acceptJson()->timeout(20)->post('https://api.xendit.co/v2/invoices', array_filter([
                'external_id' => $p->orderId, 'amount' => $p->jumlah, 'currency' => 'IDR', 'description' => mb_substr($p->deskripsi, 0, 200),
                'payer_email' => $p->emailPelanggan, 'invoice_duration' => $p->kedaluwarsaMenit * 60,
                'success_redirect_url' => $p->urlKembali, 'failure_redirect_url' => $p->urlKembali,
            ]));
        } catch (\Throwable) {
            throw new GerbangException('Xendit tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        if (! $res->successful() || ! $res->json('invoice_url')) {
            throw new GerbangException('Xendit menolak pembuatan tagihan'.($res->json('message') ? ': '.$res->json('message') : '.'));
        }

        return new HasilTagihan($res->json('invoice_url'), $res->json('id'), $res->json('expiry_date') ? Carbon::parse($res->json('expiry_date')) : null);
    }

    public function terimaCallback(Request $request): ?NotifikasiBayar
    {
        if ($this->callbackToken === '' || ! hash_equals($this->callbackToken, (string) $request->header('x-callback-token'))) {
            return null;
        }

        $d = $request->json()->all() ?: $request->all();
        if (! isset($d['external_id'], $d['status']) || ! is_scalar($d['external_id']) || ! is_scalar($d['status'])) {
            return null;
        }

        $status = match (strtoupper((string) $d['status'])) {
            'PAID', 'SETTLED' => 'berhasil',
            'EXPIRED' => 'kedaluwarsa',
            'PENDING' => 'menunggu',
            default => 'gagal',
        };

        return new NotifikasiBayar(
            'xendit', (string) $d['external_id'], $status, (int) round((float) ($d['paid_amount'] ?? $d['amount'] ?? 0)),
            ($d['id'] ?? $d['external_id']).'|'.strtoupper((string) $d['status']), $d['payment_channel'] ?? $d['payment_method'] ?? null, $d,
        );
    }
}
