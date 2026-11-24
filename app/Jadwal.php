<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'jadwal';
    protected $fillable = ['kelas_id', 'mapel_id', 'guru_id', 'hari', 'jam_mulai', 'jam_selesai', 'ruang', 'semester_id'];

    public const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function getNamaHariAttribute(): string
    {
        return self::HARI[$this->hari] ?? '-';
    }

    /** Waktu "07:30" tanpa detik. */
    public function getMulaiAttribute(): string
    {
        return substr((string) $this->jam_mulai, 0, 5);
    }

    public function getSelesaiAttribute(): string
    {
        return substr((string) $this->jam_selesai, 0, 5);
    }
}
