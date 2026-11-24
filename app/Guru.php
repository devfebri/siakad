<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Guru extends Model
{
    use \App\Concerns\BelongsToSekolah;


    protected $casts = ['tanggal_lahir' => 'datetime'];
    protected $table = 'guru';
    protected $fillable = ['nip_guru', 'nama_guru', 'user_id', 'mapel_id', 'jenis_kelamin', 'no_hp', 'tanggal_lahir', 'avatar'];

    public function getAvatar()
    {
        if (!$this->avatar) {
            return asset('images/default.png');
        }
        return asset('storage/guru/' . ($this->user->email ?? auth()->user()->email) . '/' . $this->avatar);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }
}
