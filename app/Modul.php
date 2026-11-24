<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Modul fitur yang dapat dimasukkan ke paket langganan. */
class Modul extends Model
{
    protected $table = 'modul';
    protected $fillable = ['kode', 'nama', 'deskripsi'];

    public function paket()
    {
        return $this->belongsToMany(Paket::class, 'paket_modul');
    }
}
