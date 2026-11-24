<?php

namespace App;

use App\Wa\Driver\CloudSender;
use App\Wa\Driver\FakeSender;
use App\Wa\Driver\FonnteSender;
use App\Wa\Driver\WablasSender;
use App\Wa\WhatsAppSender;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Pengaturan notifikasi WhatsApp milik satu sekolah. Nonaktif secara bawaan. Kunci terenkripsi dan tidak ikut keluar di JSON. */
class PengaturanWa extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const DRIVER = ['fonnte' => 'Fonnte', 'wablas' => 'Wablas', 'cloud' => 'WhatsApp Cloud API (Meta)', 'fake' => 'Simulasi (hanya lokal/uji)'];

    protected $table = 'pengaturan_wa_sekolah';
    protected $fillable = ['aktif', 'driver', 'token', 'kunci2', 'server_url', 'nomor_admin', 'batas_per_menit', 'event_aktif', 'templat'];
    protected $hidden = ['token', 'kunci2'];
    protected $casts = [
        'aktif' => 'boolean', 'batas_per_menit' => 'integer', 'event_aktif' => 'array', 'templat' => 'array',
        'token' => 'encrypted', 'kunci2' => 'encrypted',
    ];

    public static function cacheKey(int $sekolahId): string
    {
        return "wa:pengaturan:{$sekolahId}";
    }

    protected static function booted(): void
    {
        $lupa = fn (self $p) => Cache::forget(self::cacheKey((int) $p->sekolah_id));
        static::saved($lupa);
        static::deleted($lupa);
    }

    public static function milikSekolahAktif(): self
    {
        return static::first() ?? static::create(['aktif' => false, 'driver' => 'fonnte']);
    }

    /** Pengirim sesuai driver dan kunci sekolah ini, atau null bila belum lengkap. */
    public function pengirim(): ?WhatsAppSender
    {
        return match ($this->driver) {
            'fonnte' => $this->token ? new FonnteSender($this->token) : null,
            'wablas' => $this->token && $this->server_url ? new WablasSender($this->token, $this->server_url, $this->kunci2) : null,
            'cloud' => $this->token && $this->kunci2 ? new CloudSender($this->token, $this->kunci2) : null,
            'fake' => app()->isProduction() ? null : new FakeSender(),
            default => null,
        };
    }

    /** Kejadian ini dihidupkan? (belum diatur = hidup) */
    public function kejadianAktif(string $kejadian): bool
    {
        return (bool) (($this->event_aktif ?? [])[$kejadian] ?? true);
    }

    /** Teks templat kejadian: buatan sekolah, atau bawaan. */
    public function templat(string $kejadian): string
    {
        $buatan = trim((string) (($this->templat ?? [])[$kejadian] ?? ''));

        return $buatan !== '' ? $buatan : (string) config("wa-templat.$kejadian.templat");
    }

    public function tersembunyi(string $kolom): string
    {
        $nilai = $this->{$kolom};

        return $nilai ? str_repeat('•', 10).substr($nilai, -4) : '';
    }
}
