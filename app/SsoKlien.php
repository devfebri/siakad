<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Klien E-Learning milik satu sekolah. client_id = id klien OAuth di Passport (rahasia klien di-hash di sana).
 * webhook_secret terenkripsi dan hanya dipakai menandatangani HMAC pengiriman webhook.
 */
class SsoKlien extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'sso_klien';
    protected $fillable = ['nama', 'client_id', 'redirect_url', 'elearning_url', 'webhook_url', 'webhook_secret', 'aktif', 'sso_aktif', 'terakhir_dipakai'];
    protected $hidden = ['webhook_secret'];
    protected $casts = ['webhook_secret' => 'encrypted', 'aktif' => 'boolean', 'sso_aktif' => 'boolean', 'terakhir_dipakai' => 'datetime'];

    protected static function booted(): void
    {
        $lupa = fn (self $k) => Cache::forget(\App\Support\Integrasi::cacheKey((int) $k->sekolah_id));
        static::saved($lupa);
        static::deleted($lupa);
    }

    public function logPengiriman()
    {
        return $this->hasMany(WebhookLog::class);
    }
}
