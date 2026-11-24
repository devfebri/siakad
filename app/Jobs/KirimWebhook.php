<?php

namespace App\Jobs;

use App\SsoKlien;
use App\Support\Integrasi;
use App\Support\UrlAman;
use App\WebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * Kirim satu kabar perubahan data induk ke E-Learning. Isi ditandatangani HMAC-SHA256:
 *   X-Siakad-Signature: sha256=hmac(secret, "<X-Siakad-Timestamp>.<isi>")
 * Gagal dicoba ulang bertahap (1 mnt, 5 mnt, 30 mnt, 2 jam, 12 jam); setiap percobaan dicatat di webhook_log.
 */
class KirimWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public const JEDA = [60, 300, 1800, 7200, 43200];

    public function __construct(
        public int $klienId,
        public string $pengirimanId,
        public string $event,
        public array $data,
        public ?string $waktu = null,
    ) {
        $this->waktu ??= now()->toIso8601String();
    }

    public function handle(): void
    {
        $klien = SsoKlien::withoutGlobalScopes()->find($this->klienId);
        if (! $klien || ! $klien->aktif || ! $klien->webhook_url) {
            $this->catat($klien, 'dibatalkan', null, 'Integrasi dinonaktifkan atau alamat webhook kosong.');

            return;
        }

        if ($galat = UrlAman::galat($klien->webhook_url, periksaDns: true)) {
            $this->catat($klien, 'dibatalkan', null, $galat);

            return;
        }

        $isi = json_encode([
            'id' => $this->pengirimanId, 'event' => $this->event, 'waktu' => $this->waktu,
            'sekolah' => $klien->sekolah?->subdomain, 'data' => $this->data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ts = (string) time();
        $tanda = Integrasi::tandatangani($ts, $isi, (string) $klien->webhook_secret);

        $mulai = microtime(true);
        try {
            $res = Http::timeout(10)->withoutRedirecting()->withHeaders([
                'X-Siakad-Event' => $this->event, 'X-Siakad-Delivery' => $this->pengirimanId,
                'X-Siakad-Timestamp' => $ts, 'X-Siakad-Signature' => $tanda, 'User-Agent' => 'SIAKAD-Webhook/1.0',
            ])->withBody($isi, 'application/json')->post($klien->webhook_url);
        } catch (\Throwable $e) {
            $this->gagal($klien, null, 'Tidak tersambung: '.mb_substr($e->getMessage(), 0, 200), $mulai);

            return;
        }

        if ($res->successful()) {
            $this->catat($klien, 'berhasil', $res->status(), null, $mulai);

            return;
        }

        $this->gagal($klien, $res->status(), 'E-Learning membalas kode '.$res->status().'.', $mulai);
    }

    private function gagal(SsoKlien $klien, ?int $http, string $pesan, float $mulai): void
    {
        $this->catat($klien, 'gagal', $http, $pesan, $mulai);

        if ($this->attempts() < $this->tries) {
            $this->release(self::JEDA[min($this->attempts() - 1, count(self::JEDA) - 1)]);
        }
    }

    private function catat(?SsoKlien $klien, string $status, ?int $http, ?string $pesan, ?float $mulai = null): void
    {
        if (! $klien) {
            return;
        }

        WebhookLog::withoutGlobalScopes()->create([
            'sekolah_id' => $klien->sekolah_id, 'sso_klien_id' => $klien->id, 'pengiriman_id' => $this->pengirimanId, 'event' => $this->event,
            'percobaan' => max(1, $this->attempts()), 'status' => $status, 'http_status' => $http, 'pesan' => $pesan,
            'durasi_ms' => $mulai ? (int) round((microtime(true) - $mulai) * 1000) : null,
        ]);
    }
}
