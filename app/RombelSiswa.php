<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Keanggotaan siswa pada rombel per tahun ajaran. */
class RombelSiswa extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'rombel_siswa';
    protected $fillable = ['rombel_id', 'siswa_id', 'tahun_ajaran_id'];
}
