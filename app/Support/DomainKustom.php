<?php

namespace App\Support;

use App\LogAktivitas;
use App\Sekolah;

/**
 * Domain kustom per sekolah. Alur: sekolah mengisi domain, memasang rekaman DNS TXT berisi token (bukti kepemilikan) dan
 * CNAME/A (arah lalu lintas), lalu menekan "Cek". Hanya domain yang TXT-nya cocok yang dipakai mengenali sekolah.
 * Aplikasi TIDAK mengubah konfigurasi server/SSL: lihat README (Caddy on-demand TLS atau certbot).
 */
class DomainKustom
{
    public const AWALAN_TXT = '_siakad-verifikasi';

    /** Huruf kecil, tanpa "www.", tanpa spasi. */
    public static function normalkan(?string $domain): ?string
    {
        $d = mb_strtolower(trim((string) $domain));

        return $d === '' ? null : preg_replace('/^www\./', '', $d);
    }

    /** @return string|null pesan kesalahan, null bila domain boleh diklaim sekolah ini */
    public static function galat(?string $domain, ?Sekolah $untuk = null): ?string
    {
        $domain = self::normalkan($domain);
        if ($domain === null) {
            return null; // mengosongkan = menghapus domain
        }

        if (! preg_match('/^(?!-)([a-z0-9-]{1,63}\.)+[a-z]{2,}$/', $domain) || strlen($domain) > 150) {
            return 'Domain tidak valid. Contoh yang benar: sekolahku.sch.id (tanpa http:// dan tanpa garis miring).';
        }

        $utama = strtolower((string) config('sekolah.domain_utama'));
        if ($utama !== '' && ($domain === $utama || str_ends_with($domain, '.'.$utama))) {
            return 'Alamat ini milik platform. Gunakan domain milik sekolah sendiri.';
        }

        $lain = Sekolah::where('domain_kustom', $domain)->when($untuk, fn ($q) => $q->whereKeyNot($untuk->id))->first();
        if ($lain && $lain->domain_terverifikasi_pada) {
            return 'Domain ini sudah dipakai sekolah lain.';
        }

        return null;
    }

    /**
     * Klaim (atau hapus bila null) domain untuk sekolah. Klaim lain atas domain yang sama yang belum terbukti dilepas,
     * karena hanya yang berhasil membuktikan kepemilikan yang berhak. Verifikasi diulang dari awal.
     *
     * @throws \InvalidArgumentException bila domain tidak sah
     */
    public static function klaim(Sekolah $sekolah, ?string $domain): Sekolah
    {
        $domain = self::normalkan($domain);
        if ($galat = self::galat($domain, $sekolah)) {
            throw new \InvalidArgumentException($galat);
        }

        self::lepaskanKlaimBelumTerbukti($domain, $sekolah->id);

        if ($sekolah->domain_kustom !== $domain) {
            $sekolah->forceFill(['domain_kustom' => $domain])->save(); // saving() mengatur ulang token dan verifikasi
            LogAktivitas::catat('domain.klaim', $domain ? "Domain kustom {$domain} didaftarkan (menunggu verifikasi)." : 'Domain kustom dihapus.', $sekolah->id);
        }

        return $sekolah;
    }

    /** Lepas klaim sekolah lain atas domain ini yang belum terbukti (indeks unik domain_kustom hanya mengizinkan satu pemegang). */
    public static function lepaskanKlaimBelumTerbukti(?string $domain, ?int $kecuali = null): void
    {
        if ($domain !== null) {
            Sekolah::where('domain_kustom', $domain)->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali))->whereNull('domain_terverifikasi_pada')->update(['domain_kustom' => null]);
        }
    }

    /**
     * Instruksi DNS untuk sekolah.
     *
     * @return array{domain: string, txt_host: string, txt_nilai: string, cname_host: string, cname_target: ?string, a_target: ?string}|null
     */
    public static function instruksi(Sekolah $sekolah): ?array
    {
        if (! $sekolah->domain_kustom) {
            return null;
        }

        $utama = config('sekolah.domain_utama');

        return [
            'domain' => $sekolah->domain_kustom,
            'txt_host' => self::AWALAN_TXT.'.'.$sekolah->domain_kustom,
            'txt_nilai' => 'siakad-verifikasi='.$sekolah->domain_token,
            'cname_host' => $sekolah->domain_kustom,
            'cname_target' => config('sekolah.cname_target') ?: ($utama ? $sekolah->subdomain.'.'.$utama : null),
            'a_target' => config('sekolah.server_ip') ?: null,
        ];
    }

    /**
     * Cek DNS. Bila rekaman TXT cocok, domain ditandai terverifikasi (sekali; tidak dicabut otomatis bila DNS berubah lalu).
     *
     * @return array{terverifikasi: bool, txt: bool, mengarah: ?bool, pesan: string}
     */
    public static function periksa(Sekolah $sekolah): array
    {
        $i = self::instruksi($sekolah);
        if (! $i) {
            return ['terverifikasi' => false, 'txt' => false, 'mengarah' => null, 'pesan' => 'Belum ada domain yang didaftarkan.'];
        }

        $txt = in_array($i['txt_nilai'], array_map('trim', DomainDns::txt($i['txt_host'])), true);
        $mengarah = self::mengarah($i);

        if ($txt && ! $sekolah->domain_terverifikasi_pada) {
            // dua sekolah mengklaim domain yang sama: yang lebih dulu terbukti yang berhak
            $sudahDiambil = Sekolah::where('domain_kustom', $sekolah->domain_kustom)->whereKeyNot($sekolah->id)->whereNotNull('domain_terverifikasi_pada')->exists();
            if ($sudahDiambil) {
                return ['terverifikasi' => false, 'txt' => true, 'mengarah' => $mengarah, 'pesan' => 'Domain ini sudah diverifikasi sekolah lain.'];
            }
            $sekolah->forceFill(['domain_terverifikasi_pada' => now()])->save();
            LogAktivitas::catat('domain.verifikasi', "Domain {$sekolah->domain_kustom} terverifikasi.", $sekolah->id);
        }

        $ok = (bool) $sekolah->domain_terverifikasi_pada;

        return [
            'terverifikasi' => $ok, 'txt' => $txt, 'mengarah' => $mengarah,
            'pesan' => match (true) {
                $ok && $mengarah === false => 'Domain terverifikasi, tetapi alamat (CNAME/A) belum mengarah ke server kami. Situs belum bisa dibuka lewat domain ini.',
                $ok => 'Domain terverifikasi dan siap dipakai.',
                default => 'Rekaman TXT belum ditemukan. Perubahan DNS bisa memakan waktu beberapa menit sampai beberapa jam; coba lagi nanti.',
            },
        ];
    }

    /** Apakah domain sudah mengarah ke server (CNAME ke target, atau A record ke IP server). null bila tujuan belum dikonfigurasi. */
    private static function mengarah(array $i): ?bool
    {
        if (! $i['cname_target'] && ! $i['a_target']) {
            return null;
        }

        if ($i['cname_target'] && DomainDns::cname($i['domain']) === strtolower(rtrim($i['cname_target'], '.'))) {
            return true;
        }

        return $i['a_target'] ? in_array($i['a_target'], DomainDns::alamat($i['domain']), true) : false;
    }

    /** Tandai terverifikasi tanpa cek DNS (hanya Super Admin, mis. bukti kepemilikan lewat jalur lain). */
    public static function verifikasiManual(Sekolah $sekolah): void
    {
        abort_unless($sekolah->domain_kustom, 422, 'Sekolah ini belum mendaftarkan domain.');
        if (Sekolah::where('domain_kustom', $sekolah->domain_kustom)->whereKeyNot($sekolah->id)->whereNotNull('domain_terverifikasi_pada')->exists()) {
            abort(422, 'Domain ini sudah diverifikasi sekolah lain.');
        }
        $sekolah->forceFill(['domain_terverifikasi_pada' => now()])->save();
        LogAktivitas::catat('domain.verifikasi_manual', "Domain {$sekolah->domain_kustom} ditandai terverifikasi oleh Super Admin.", $sekolah->id);
    }

    /** Apakah host ini boleh dilayani (dipakai endpoint "ask" Caddy sebelum meminta sertifikat SSL). */
    public static function diizinkan(string $host): bool
    {
        $host = self::normalkan($host);
        if (! $host) {
            return false;
        }

        $utama = strtolower((string) config('sekolah.domain_utama'));
        if ($utama !== '' && ($host === $utama || str_ends_with($host, '.'.$utama))) {
            return true;
        }

        return Sekolah::where('domain_kustom', $host)->whereNotNull('domain_terverifikasi_pada')->where('status', 'aktif')->exists();
    }
}
