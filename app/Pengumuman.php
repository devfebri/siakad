<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pengumuman extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $casts = ['pengumuman_tanggal' => 'datetime'];
    protected $primaryKey = 'pengumuman_id';
    protected $table = 'pengumuman';
    protected $fillable = ['pengumuman_id', 'pengumuman_judul', 'pengumuman_deskripsi', 'pengumuman_tanggal', 'pengumuman_author'];
}
