<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Testimoni extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'testimoni';
    protected $fillable = ['nama', 'peran', 'foto', 'isi', 'urutan', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    public const DIR = 'storage/testimoni';

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto && is_file(public_path(self::DIR.'/'.$this->foto)) ? asset(self::DIR.'/'.$this->foto) : null;
    }
}
