<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Siswa extends Model
{
    use \App\Concerns\BelongsToSekolah;


    protected $casts = ['tanggal_lahir' => 'datetime'];
    protected $table = 'siswa';
    protected $fillable = ['nisn', 'nama_siswa', 'agama', 'jenis_kelamin', 'tanggal_lahir', 'avatar', 'user_id', 'kelas_id', 'status'];

    public const STATUS = ['aktif' => 'Aktif', 'lulus' => 'Lulus', 'tidak_lulus' => 'Tidak Lulus', 'mutasi_keluar' => 'Mutasi Keluar'];

    public function scopeAktif($query)
    {
        return $query->where('siswa.status', 'aktif');
    }

    protected static function booted(): void
    {
        // kelas_id (rombel aktif) selalu tercatat juga di rombel_siswa untuk tahun ajaran rombel itu
        static::saved(function (self $siswa) {
            if ($siswa->kelas_id && ($siswa->wasRecentlyCreated || $siswa->wasChanged('kelas_id'))) {
                Kelas::find($siswa->kelas_id)?->tempatkan($siswa);
            } elseif (! $siswa->kelas_id && $siswa->wasChanged('kelas_id')) {
                \Illuminate\Support\Facades\DB::table('rombel_siswa')->where('siswa_id', $siswa->id)->where('tahun_ajaran_id', \App\Support\Akademik::aktif()['tahun_ajaran_id'] ?? 0)->delete();
            }
        });
    }

    public function getAvatar()
    {
        if (!$this->avatar) {
            return asset('images/default.png');
        }
        return asset('storage/siswa/' . ($this->user->email ?? auth()->user()->email) . '/' . $this->avatar);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orangTua()
    {
        return $this->belongsToMany(OrangTua::class, 'orang_tua_siswa', 'siswa_id', 'orang_tua_id')->withPivot('hubungan');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
