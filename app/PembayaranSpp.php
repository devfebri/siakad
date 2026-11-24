<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Satu pembayaran atas satu tagihan, dengan nomor kuitansi. Tidak dihapus; dibatalkan dengan alasan. */
class PembayaranSpp extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const METODE = ['tunai' => 'Tunai', 'transfer' => 'Transfer', 'online' => 'Online'];

    protected $table = 'pembayaran_spp';
    protected $fillable = ['nomor', 'tagihan_id', 'transaksi_id', 'jumlah', 'metode', 'tanggal', 'referensi', 'catatan', 'diterima_oleh', 'dibatalkan_pada', 'alasan_batal'];
    protected $casts = ['tanggal' => 'date', 'dibatalkan_pada' => 'datetime', 'jumlah' => 'integer'];

    public function tagihan()
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function penerima()
    {
        return $this->belongsTo(User::class, 'diterima_oleh')->withoutGlobalScopes();
    }

    public function dibatalkan(): bool
    {
        return $this->dibatalkan_pada !== null;
    }

    public function scopeAktif($q)
    {
        return $q->whereNull('dibatalkan_pada');
    }
}
