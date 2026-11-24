<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Lulusan (atau siswa yang dinyatakan tidak lulus pada tingkat akhir) yang dipindahkan dari Data Siswa. */
class Alumni extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'alumni';
    protected $fillable = ['siswa_id', 'angkatan', 'tanggal_lulus', 'status', 'no_ijazah'];
    protected $casts = ['tanggal_lulus' => 'date'];

    public const STATUS = ['lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
