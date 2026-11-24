<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Jenis biaya sekolah (SPP, uang gedung, seragam, dst.). */
class JenisBiaya extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const JENIS = ['bulanan' => 'Bulanan (tiap bulan)', 'sekali' => 'Sekali bayar'];

    protected $table = 'jenis_biaya';
    protected $fillable = ['nama', 'nominal', 'jenis', 'aktif'];
    protected $casts = ['aktif' => 'boolean', 'nominal' => 'integer'];

    public function tagihan()
    {
        return $this->hasMany(Tagihan::class);
    }
}
