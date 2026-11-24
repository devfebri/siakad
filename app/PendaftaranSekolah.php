<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Pendaftaran sekolah mandiri yang menunggu verifikasi email. Tidak terikat sekolah. */
class PendaftaranSekolah extends Model
{
    protected $table = 'pendaftaran_sekolah';
    protected $fillable = ['token_hash', 'email', 'subdomain', 'npsn', 'data', 'pakai_trial', 'kedaluwarsa_pada', 'diverifikasi_pada', 'sekolah_id', 'ip'];
    protected $casts = ['data' => 'array', 'pakai_trial' => 'boolean', 'kedaluwarsa_pada' => 'datetime', 'diverifikasi_pada' => 'datetime'];

    /** Masih menunggu dan belum lewat batas waktu. */
    public function scopeMenunggu($q)
    {
        return $q->whereNull('diverifikasi_pada')->where('kedaluwarsa_pada', '>', now());
    }
}
