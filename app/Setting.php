<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Pengaturan situs bentuk key-value yang dikelola dari menu Pengaturan. */
class Setting extends Model
{
    use \App\Concerns\BelongsToSekolah;


    protected $table = 'site_settings';
    protected $fillable = ['key', 'value'];

    /** Cache pengaturan dipisah per sekolah. */
    private static function cacheKey(): string
    {
        return 'site-settings:'.(\App\Support\SekolahAktif::instance()->id() ?? 'semua');
    }

    /** @return array<string, string|null> */
    public static function values(): array
    {
        try {
            return Cache::rememberForever(self::cacheKey(), fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            return []; // tabel belum ada (sebelum migrate) atau database tidak tersedia
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::values()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /** @param array<string, string|null> $values */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::cacheKey());
    }
}
