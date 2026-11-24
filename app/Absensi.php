<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'absensi';
    protected $fillable = ['siswa_id', 'kelas_id', 'tanggal', 'semester_id', 'status', 'keterangan', 'dicatat_oleh'];
    protected $casts = ['tanggal' => 'date'];

    public const STATUS = [
        'hadir' => ['label' => 'Hadir', 'huruf' => 'H', 'badge' => 'success'],
        'izin' => ['label' => 'Izin', 'huruf' => 'I', 'badge' => 'primary'],
        'sakit' => ['label' => 'Sakit', 'huruf' => 'S', 'badge' => 'warning'],
        'alpa' => ['label' => 'Alpa', 'huruf' => 'A', 'badge' => 'danger'],
    ];

    protected static function booted(): void
    {
        // semester menurut tanggal absensi (rentang tanggal semester di tabel semester)
        static::saving(function (self $a) {
            if ($a->isDirty('tanggal') || $a->semester_id === null) {
                $a->semester_id = Semester::untukTanggal($a->tanggal)?->id;
            }
        });
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }
}
