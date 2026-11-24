<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Tagihan seorang siswa. terbayar/status dijaga oleh App\Support\Keuangan (jangan diubah langsung). */
class Tagihan extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const STATUS = ['belum' => 'Belum bayar', 'sebagian' => 'Dicicil', 'lunas' => 'Lunas', 'batal' => 'Dibatalkan'];
    public const WARNA = ['belum' => 'danger', 'sebagian' => 'warning', 'lunas' => 'success', 'batal' => 'muted'];

    protected $table = 'tagihan';
    protected $fillable = ['siswa_id', 'jenis_biaya_id', 'tahun_ajaran_id', 'nama', 'periode', 'jumlah', 'terbayar', 'status', 'jatuh_tempo', 'catatan'];
    protected $casts = ['jatuh_tempo' => 'date', 'jumlah' => 'integer', 'terbayar' => 'integer'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function jenisBiaya()
    {
        return $this->belongsTo(JenisBiaya::class);
    }

    public function pembayaran()
    {
        return $this->hasMany(PembayaranSpp::class);
    }

    public function getSisaAttribute(): int
    {
        return $this->status === 'batal' ? 0 : max(0, $this->jumlah - $this->terbayar);
    }

    /** Lewat jatuh tempo dan belum lunas. */
    public function terlambat(): bool
    {
        return in_array($this->status, ['belum', 'sebagian'], true) && $this->jatuh_tempo && $this->jatuh_tempo->lt(today());
    }

    /** Tagihan yang masih harus dibayar. */
    public function scopeBelumLunas($q)
    {
        return $q->whereIn('status', ['belum', 'sebagian']);
    }
}
