<?php

namespace App\Support;

use Closure;

/**
 * Pencarian DNS untuk verifikasi domain kustom. Dipisah supaya tes bisa menggantinya (DomainDns::$palsu) dan tidak pernah menghubungi DNS sungguhan.
 * $palsu menerima ($jenis, $host) dan mengembalikan array nilai: jenis "TXT" | "CNAME" | "A".
 */
class DomainDns
{
    public static ?Closure $palsu = null;

    /** @return array<int, string> */
    private static function cari(string $jenis, string $host): array
    {
        if (self::$palsu) {
            return array_map('strval', (self::$palsu)($jenis, $host));
        }

        $tipe = ['TXT' => DNS_TXT, 'CNAME' => DNS_CNAME, 'A' => DNS_A][$jenis];
        $hasil = [];
        foreach (@dns_get_record($host, $tipe) ?: [] as $r) {
            $hasil[] = match ($jenis) {
                'TXT' => $r['txt'] ?? implode('', $r['entries'] ?? []),
                'CNAME' => $r['target'] ?? '',
                'A' => $r['ip'] ?? '',
            };
        }

        return array_values(array_filter($hasil));
    }

    /** @return array<int, string> */
    public static function txt(string $host): array
    {
        return self::cari('TXT', $host);
    }

    public static function cname(string $host): ?string
    {
        $c = self::cari('CNAME', $host)[0] ?? null;

        return $c ? strtolower(rtrim($c, '.')) : null;
    }

    /** @return array<int, string> */
    public static function alamat(string $host): array
    {
        return self::cari('A', $host);
    }
}
