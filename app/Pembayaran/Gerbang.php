<?php

namespace App\Pembayaran;

use App\Pembayaran\Driver\FakeGateway;
use App\Pembayaran\Driver\MidtransGateway;
use App\Pembayaran\Driver\XenditGateway;
use App\PengaturanPlatform;

/**
 * Pemilih gerbang pembayaran SEWA (kunci milik platform). Driver dan kunci dari Pengaturan Platform (terenkripsi),
 * dengan cadangan dari .env (config/pembayaran.php). Gerbang SPP per sekolah memakai kunci sekolah di kelas terpisah (poin 5).
 */
class Gerbang
{
    public const DRIVER = ['nonaktif' => 'Nonaktif (hanya transfer manual)', 'midtrans' => 'Midtrans', 'xendit' => 'Xendit', 'fake' => 'Simulasi (hanya lokal/uji)'];

    /** Kode driver yang dipilih admin platform. */
    public static function kodeAktif(): string
    {
        $kode = (string) PengaturanPlatform::get('pembayaran_driver', config('pembayaran.driver'));

        return isset(self::DRIVER[$kode]) ? $kode : 'nonaktif';
    }

    public static function sandbox(): bool
    {
        $nilai = PengaturanPlatform::values()['pembayaran_sandbox'] ?? null;

        return $nilai === null ? (bool) config('pembayaran.midtrans.sandbox') : in_array($nilai, ['1', 'true', 'on'], true);
    }

    /** Gerbang yang dipakai untuk membuat tagihan baru; null bila nonaktif atau kuncinya belum diisi. */
    public static function aktif(): ?PaymentGateway
    {
        return self::driver(self::kodeAktif());
    }

    /** Gerbang untuk kode tertentu (juga dipakai memverifikasi callback), atau null bila belum terkonfigurasi. */
    public static function driver(string $kode): ?PaymentGateway
    {
        return match ($kode) {
            'midtrans' => ($k = self::kunci('midtrans_server_key', 'pembayaran.midtrans.server_key')) ? new MidtransGateway($k, self::sandbox()) : null,
            'xendit' => ($k = self::kunci('xendit_secret_key', 'pembayaran.xendit.secret_key')) ? new XenditGateway($k, (string) self::kunci('xendit_callback_token', 'pembayaran.xendit.callback_token')) : null,
            'fake' => app()->isProduction() ? null : new FakeGateway(),
            default => null,
        };
    }

    private static function kunci(string $kunciDb, string $config): ?string
    {
        return PengaturanPlatform::rahasia($kunciDb) ?: (config($config) ?: null);
    }
}
