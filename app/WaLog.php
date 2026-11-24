<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Satu pesan WhatsApp (antri, terkirim, gagal, atau dilewati). Unik per kejadian sehingga tidak terkirim ulang. */
class WaLog extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const STATUS = ['antri' => 'Dalam antrian', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal', 'dilewati' => 'Dilewati'];
    public const WARNA = ['antri' => 'warning', 'terkirim' => 'success', 'gagal' => 'danger', 'dilewati' => 'muted'];

    protected $table = 'wa_log';
    protected $fillable = ['kejadian', 'referensi', 'nomor', 'pesan', 'status', 'percobaan', 'galat', 'id_pesan', 'driver', 'terkirim_pada'];
    protected $casts = ['terkirim_pada' => 'datetime'];
}
