<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Orang tua/wali murid: akun login (peran orang_tua) yang dapat melihat data anak-anaknya. */
class OrangTua extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'orang_tua';
    protected $fillable = ['user_id', 'nama', 'no_hp', 'pekerjaan', 'alamat'];

    public const HUBUNGAN = ['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function anak()
    {
        return $this->belongsToMany(Siswa::class, 'orang_tua_siswa', 'orang_tua_id', 'siswa_id')->withPivot('hubungan')->withTimestamps()->orderBy('nama_siswa');
    }

    /** Hubungkan dengan siswa (memperbarui hubungan bila sudah terhubung). */
    public function hubungkan(Siswa $siswa, string $hubungan): void
    {
        $this->anak()->syncWithoutDetaching([$siswa->id => ['hubungan' => $hubungan, 'sekolah_id' => $this->sekolah_id]]);
    }
}
