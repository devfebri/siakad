<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Pengaturan milik platform (bukan per sekolah). Nilai biasa disimpan apa adanya; kunci/rahasia lewat putRahasia()/rahasia()
 * disimpan terenkripsi dan tidak pernah dikembalikan oleh values().
 */
class PengaturanPlatform extends Model
{
    public const BAWAAN = [
        'trial_aktif' => '1',
        'trial_hari' => '14',
        'nama_produk' => 'SIAKAD Sekolah',
        'kontak_email' => '',
        'kontak_wa' => '',
    ];

    private const CACHE = 'pengaturan-platform';
    private const AWALAN_RAHASIA = 'rahasia:';

    protected $table = 'platform_setting';
    protected $fillable = ['key', 'value'];

    /** @return array<string, string|null> */
    public static function values(): array
    {
        return Cache::rememberForever(self::CACHE, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $nilai = static::values()[$key] ?? null;
        if ($nilai === null || $nilai === '' || str_starts_with((string) $nilai, self::AWALAN_RAHASIA)) {
            return $default ?? (self::BAWAAN[$key] ?? null);
        }

        return $nilai;
    }

    public static function aktif(string $key): bool
    {
        return in_array((string) static::get($key, '0'), ['1', 'true', 'on'], true);
    }

    /** @param array<string, string|null> $nilai */
    public static function put(array $nilai): void
    {
        foreach ($nilai as $key => $v) {
            static::updateOrCreate(['key' => $key], ['value' => $v]);
        }
        Cache::forget(self::CACHE);
    }

    public static function putRahasia(string $key, ?string $nilai): void
    {
        static::put([$key => $nilai === null || $nilai === '' ? null : self::AWALAN_RAHASIA.Crypt::encryptString($nilai)]);
    }

    public static function rahasia(string $key): ?string
    {
        $nilai = static::values()[$key] ?? null;
        if (! $nilai || ! str_starts_with($nilai, self::AWALAN_RAHASIA)) {
            return null;
        }

        try {
            return Crypt::decryptString(substr($nilai, strlen(self::AWALAN_RAHASIA)));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Tampilan aman sebuah rahasia: hanya 4 karakter terakhir. */
    public static function rahasiaTersembunyi(string $key): string
    {
        $r = static::rahasia($key);

        return $r ? str_repeat('•', 10).substr($r, -4) : '';
    }
}
