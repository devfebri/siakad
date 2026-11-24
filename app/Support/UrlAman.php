<?php

namespace App\Support;

/**
 * Pemeriksa alamat tujuan (webhook, redirect) agar server tidak dipakai menembak jaringan internal (SSRF):
 * wajib https (http hanya di lokal/uji) dan tidak boleh menuju IP privat/loopback/link-local.
 */
class UrlAman
{
    /** @return string|null pesan kesalahan, null bila aman */
    public static function galat(?string $url, bool $periksaDns = false): ?string
    {
        if ($url === null || trim($url) === '') {
            return 'Alamat kosong.';
        }

        $bagian = parse_url($url);
        if (! $bagian || empty($bagian['scheme']) || empty($bagian['host'])) {
            return 'Alamat tidak valid.';
        }

        $lokal = app()->environment('local', 'testing');
        if (! in_array($bagian['scheme'], $lokal ? ['http', 'https'] : ['https'], true)) {
            return $lokal ? 'Alamat harus diawali http:// atau https://.' : 'Alamat harus diawali https://.';
        }
        if (isset($bagian['user']) || isset($bagian['pass'])) {
            return 'Alamat tidak boleh memuat nama pengguna atau kata sandi.';
        }

        $host = trim($bagian['host'], '[]');
        if (strcasecmp($host, 'localhost') === 0 || str_ends_with(strtolower($host), '.local') || str_ends_with(strtolower($host), '.internal')) {
            return $lokal ? null : 'Alamat tidak boleh menuju jaringan internal.';
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($periksaDns ? self::resolusi($host) : []);
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $lokal && in_array($ip, ['127.0.0.1', '::1'], true) ? null : 'Alamat tidak boleh menuju jaringan internal.';
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private static function resolusi(string $host): array
    {
        $hasil = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            $hasil[] = $r['ip'] ?? $r['ipv6'] ?? null;
        }

        return array_values(array_filter($hasil));
    }
}
