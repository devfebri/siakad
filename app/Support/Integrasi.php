<?php

namespace App\Support;

use App\Sekolah;
use App\SsoKlien;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/** Pengelolaan klien E-Learning per sekolah: aktifkan, putar kunci, dan cari klien aktif. */
class Integrasi
{
    public const MODUL = 'integrasi_elearning';

    public static function cacheKey(int $sekolahId): string
    {
        return "integrasi:klien:{$sekolahId}";
    }

    /** Klien aktif (dengan alamat webhook) untuk sekolah, disimpan sebentar di cache agar simpan data massal tidak membebani database. */
    public static function klienWebhook(int $sekolahId): ?array
    {
        return Cache::remember(self::cacheKey($sekolahId), 300, function () use ($sekolahId) {
            $k = SsoKlien::withoutGlobalScopes()->where('sekolah_id', $sekolahId)->where('aktif', true)->whereNotNull('webhook_url')->first();

            return $k ? ['id' => $k->id] : false;
        }) ?: null;
    }

    /** Apakah paket sekolah mencakup integrasi E-Learning dan sekolahnya aktif. */
    public static function tersedia(Sekolah $sekolah): bool
    {
        return $sekolah->isAktif() && AksesSekolah::modulUntuk($sekolah, self::MODUL);
    }

    /**
     * Buat klien (atau buat ulang kuncinya bila sudah ada) dan kembalikan kunci apa adanya SEKALI.
     *
     * @return array{klien: SsoKlien, client_secret: string, webhook_secret: string}
     */
    public static function siapkan(Sekolah $sekolah): array
    {
        return SekolahAktif::jalankan($sekolah, function () use ($sekolah) {
            $klien = SsoKlien::first();
            $model = Passport::client();

            $oauth = $klien ? $model->newQuery()->find($klien->client_id) : null;
            if (! $oauth) {
                $oauth = $model->newQuery()->forceCreate([
                    'name' => 'E-Learning '.$sekolah->nama, 'secret' => Str::random(48), 'provider' => null, 'revoked' => false,
                    'redirect_uris' => [], 'grant_types' => ['client_credentials', 'authorization_code', 'refresh_token'],
                ]);
            } else {
                $oauth->forceFill(['secret' => Str::random(48), 'revoked' => false])->save();
            }
            $secret = $oauth->plainSecret;
            $webhook = Str::random(48);

            $klien = SsoKlien::first() ?? new SsoKlien();
            $klien->fill(['client_id' => $oauth->getKey(), 'webhook_secret' => $webhook, 'aktif' => true])->save();

            return ['klien' => $klien, 'client_secret' => $secret, 'webhook_secret' => $webhook];
        });
    }

    /** Nonaktifkan: token yang beredar dicabut dan webhook berhenti. */
    public static function nonaktifkan(SsoKlien $klien): void
    {
        $klien->update(['aktif' => false]);
        Passport::client()->newQuery()->whereKey($klien->client_id)->update(['revoked' => true]);
        Passport::token()->newQuery()->where('client_id', $klien->client_id)->update(['revoked' => true]);
    }

    /** Alamat E-Learning untuk tombol "Buka E-Learning" bila SSO menyala dan paket mencakup integrasi; selain itu null. */
    public static function alamatElearning(): ?string
    {
        $sekolah = SekolahAktif::instance()->get();
        if (! $sekolah || ! self::tersedia($sekolah)) {
            return null;
        }
        $klien = SsoKlien::first();

        return $klien && $klien->aktif && $klien->sso_aktif && $klien->elearning_url ? $klien->elearning_url : null;
    }

    /** Tanda tangan webhook: sha256=HMAC-SHA256(rahasia, "<timestamp>.<isi>"). */
    public static function tandatangani(string $timestamp, string $isi, string $rahasia): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$isi, $rahasia);
    }

    /** Tampilkan kunci dengan menyisakan 4 karakter terakhir. */
    public static function sembunyikan(?string $nilai): string
    {
        return $nilai ? str_repeat('•', 12).substr($nilai, -4) : '-';
    }
}
