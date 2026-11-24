<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Nilai extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'nilai';
    protected $fillable = ['siswa_id', 'kelas_id', 'mapel_id', 'semester_id', 'jenis', 'nilai', 'dicatat_oleh'];
    protected $casts = ['nilai' => 'float'];

    /** Jenis penilaian beserta label dan bobotnya (dari tabel bobot_nilai milik sekolah; total 100). */
    public static function jenis(): array
    {
        return BobotNilai::peta();
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function mapel()
    {
        return $this->belongsTo(Mapel::class);
    }

    /**
     * Nilai akhir tertimbang dari ['tugas' => 80, 'uh' => 90, ...]. Jenis yang belum ada tidak dihitung
     * (bobotnya dibagi ulang di antara jenis yang sudah terisi).
     */
    public static function nilaiAkhir(array $perJenis): ?float
    {
        $total = 0.0;
        $bobot = 0;

        foreach (self::jenis() as $kode => $info) {
            if (isset($perJenis[$kode])) {
                $total += $perJenis[$kode] * $info['bobot'];
                $bobot += $info['bobot'];
            }
        }

        return $bobot > 0 ? round($total / $bobot, 2) : null;
    }

    public static function predikat(?float $nilai): string
    {
        return match (true) {
            $nilai === null => '-',
            $nilai >= 90 => 'A',
            $nilai >= 80 => 'B',
            $nilai >= 70 => 'C',
            $nilai >= 60 => 'D',
            default => 'E',
        };
    }
}
