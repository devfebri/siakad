<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Tagihan sewa. Dikonfirmasi lunas manual oleh super admin; konfirmasi membuat atau memperpanjang langganan. */
class Invoice extends Model
{
    public const STATUS = ['menunggu' => 'Menunggu pembayaran', 'lunas' => 'Lunas', 'batal' => 'Dibatalkan'];
    public const WARNA = ['menunggu' => 'warning', 'lunas' => 'success', 'batal' => 'muted'];

    protected $table = 'invoice';
    protected $fillable = ['nomor', 'sekolah_id', 'paket_id', 'durasi_bulan', 'jumlah', 'status', 'jatuh_tempo', 'dibayar_pada', 'metode', 'catatan', 'dikonfirmasi_oleh', 'langganan_id'];
    protected $casts = ['jatuh_tempo' => 'date', 'dibayar_pada' => 'datetime'];

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function paket()
    {
        return $this->belongsTo(Paket::class);
    }

    public function langganan()
    {
        return $this->belongsTo(Langganan::class);
    }

    /** Nomor berurutan per tahun, mis. INV-2026-0007. */
    public static function nomorBerikut(): string
    {
        $tahun = now()->format('Y');
        $terakhir = static::where('nomor', 'like', "INV-{$tahun}-%")->orderByDesc('nomor')->value('nomor');
        $urut = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return sprintf('INV-%s-%04d', $tahun, $urut);
    }
}
