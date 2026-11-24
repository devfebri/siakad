<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaketHarga extends Model
{
    protected $table = 'paket_harga';
    protected $fillable = ['paket_id', 'durasi_bulan', 'harga'];
}
