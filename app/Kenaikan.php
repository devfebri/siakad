<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Hasil proses kenaikan/kelulusan satu siswa dari tahun ajaran asal ke tahun ajaran tujuan (draf). */
class Kenaikan extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'kenaikan';
    protected $fillable = ['siswa_id', 'tahun_ajaran_id', 'tahun_ajaran_tujuan_id', 'rombel_asal_id', 'rombel_tujuan_id', 'hasil', 'no_ijazah', 'diproses_oleh'];

    public const HASIL = ['naik' => 'Naik kelas', 'tinggal' => 'Tinggal kelas', 'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak lulus'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function isKelulusan(): bool
    {
        return in_array($this->hasil, ['lulus', 'tidak_lulus'], true);
    }
}
