<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan konsultasi online (jam layanan, batas chat per petugas, dsb.) dalam bentuk key-value. */
class ChatSetting extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
