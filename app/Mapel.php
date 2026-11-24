<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Mapel extends Model
{
    use \App\Concerns\BelongsToSekolah;


    protected $table = 'mapel';
    protected $fillable = ['mapel_nama'];

    public function guru()
    {
        return $this->hasMany(Guru::class);
    }
    public function jadwal()
    {
        return $this->hasMany(Jadwal::class);
    }

    public function nilai()
    {
        return $this->hasMany(Nilai::class);
    }
}
