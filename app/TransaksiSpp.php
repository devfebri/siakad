<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Satu transaksi bayar online di gerbang milik sekolah; membayar satu atau beberapa tagihan sekaligus. */
class TransaksiSpp extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const STATUS = ['menunggu' => 'Menunggu pembayaran', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'kedaluwarsa' => 'Kedaluwarsa'];

    protected $table = 'transaksi_spp';
    protected $fillable = ['user_id', 'driver', 'order_id', 'subtotal', 'biaya_admin', 'biaya_ditanggung', 'total', 'status', 'url_bayar', 'referensi', 'metode', 'kedaluwarsa_pada', 'dibayar_pada', 'catatan'];
    protected $casts = ['kedaluwarsa_pada' => 'datetime', 'dibayar_pada' => 'datetime', 'subtotal' => 'integer', 'biaya_admin' => 'integer', 'total' => 'integer'];

    public function item()
    {
        return $this->hasMany(TransaksiSppItem::class);
    }

    public function masihBerlaku(): bool
    {
        return $this->status === 'menunggu' && $this->url_bayar && (! $this->kedaluwarsa_pada || $this->kedaluwarsa_pada->isFuture());
    }
}
