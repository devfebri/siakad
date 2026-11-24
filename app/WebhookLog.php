<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Satu baris per percobaan pengiriman webhook. */
class WebhookLog extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const STATUS = ['berhasil' => 'Berhasil', 'gagal' => 'Gagal, akan dicoba lagi', 'dibatalkan' => 'Dibatalkan'];
    public const WARNA = ['berhasil' => 'success', 'gagal' => 'warning', 'dibatalkan' => 'muted'];

    protected $table = 'webhook_log';
    protected $fillable = ['sso_klien_id', 'pengiriman_id', 'event', 'percobaan', 'status', 'http_status', 'pesan', 'durasi_ms'];
}
