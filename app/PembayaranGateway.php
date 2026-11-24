<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Satu percobaan bayar online sebuah invoice sewa. Tidak terikat sekolah (dibaca platform lintas sekolah). */
class PembayaranGateway extends Model
{
    public const STATUS = ['menunggu' => 'Menunggu pembayaran', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'kedaluwarsa' => 'Kedaluwarsa'];

    protected $table = 'pembayaran_gateway';
    protected $fillable = ['invoice_id', 'driver', 'order_id', 'jumlah', 'status', 'url_bayar', 'referensi', 'metode', 'kedaluwarsa_pada', 'dibayar_pada', 'catatan'];
    protected $casts = ['kedaluwarsa_pada' => 'datetime', 'dibayar_pada' => 'datetime'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Masih bisa dipakai membayar. */
    public function masihBerlaku(): bool
    {
        return $this->status === 'menunggu' && (! $this->kedaluwarsa_pada || $this->kedaluwarsa_pada->isFuture()) && $this->url_bayar;
    }
}
