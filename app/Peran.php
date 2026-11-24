<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Peran pengguna (global untuk semua sekolah): super_admin, admin, kepsek, guru, wali_kelas, bendahara, siswa, orang_tua. */
class Peran extends Model
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const KEPSEK = 'kepsek';
    public const GURU = 'guru';
    public const WALI_KELAS = 'wali_kelas';
    public const BENDAHARA = 'bendahara';
    public const SISWA = 'siswa';
    public const ORANG_TUA = 'orang_tua';

    /** Peran yang boleh diberikan admin sekolah (super_admin hanya dari panel platform). */
    public const DAPAT_DIBERIKAN = [self::ADMIN, self::KEPSEK, self::GURU, self::WALI_KELAS, self::BENDAHARA, self::SISWA, self::ORANG_TUA];

    /** Warna lencana per peran di daftar pengguna. */
    public const WARNA = [
        'super_admin' => 'danger', 'admin' => 'primary', 'kepsek' => 'primary', 'guru' => 'accent',
        'wali_kelas' => 'accent', 'bendahara' => 'warning', 'siswa' => 'success', 'orang_tua' => 'success',
    ];

    protected $table = 'peran';
    protected $fillable = ['kode', 'nama'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'peran_user');
    }
}
