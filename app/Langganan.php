<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Masa sewa satu sekolah atas satu paket. Tidak memakai BelongsToSekolah: dibaca panel platform lintas sekolah. */
class Langganan extends Model
{
    protected $table = 'langganan';
    protected $fillable = ['sekolah_id', 'paket_id', 'invoice_id', 'durasi_bulan', 'mulai', 'berakhir', 'status', 'trial', 'catatan'];
    protected $casts = ['mulai' => 'date', 'berakhir' => 'date', 'trial' => 'boolean'];

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function paket()
    {
        return $this->belongsTo(Paket::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
