<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Usulan wali kelas untuk satu siswa pada akhir tahun ajaran. */
class UsulanKenaikan extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'usulan_kenaikan';
    protected $fillable = ['siswa_id', 'tahun_ajaran_id', 'rombel_id', 'usulan', 'catatan', 'diusulkan_oleh'];

    public const USULAN = ['naik' => 'Naik kelas', 'tinggal' => 'Tinggal kelas', 'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak lulus'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
