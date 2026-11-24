<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Tagihan yang dibayar dalam sebuah transaksi online. */
class TransaksiSppItem extends Model
{
    protected $table = 'transaksi_spp_item';
    protected $fillable = ['transaksi_spp_id', 'tagihan_id', 'jumlah'];

    public function transaksi()
    {
        return $this->belongsTo(TransaksiSpp::class, 'transaksi_spp_id');
    }

    public function tagihan()
    {
        return $this->belongsTo(Tagihan::class)->withoutGlobalScopes();
    }
}
