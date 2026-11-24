<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Bobot tiap jenis penilaian untuk nilai akhir rapor (total 100), per sekolah. */
class BobotNilai extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'bobot_nilai';
    protected $fillable = ['jenis', 'label', 'bobot', 'urutan'];

    public const BAWAAN = ['tugas' => ['Tugas', 20], 'uh' => ['Ulangan Harian', 20], 'uts' => ['UTS', 25], 'uas' => ['UAS', 35]];

    /** @return array<string, array{label: string, bobot: int}> menurut urutan; membuat bawaan bila sekolah belum punya. */
    public static function peta(): array
    {
        $baris = static::query()->orderBy('urutan')->get();
        if ($baris->isEmpty() && \App\Support\SekolahAktif::instance()->id()) {
            $urutan = 0;
            foreach (self::BAWAAN as $jenis => [$label, $bobot]) {
                static::create(['jenis' => $jenis, 'label' => $label, 'bobot' => $bobot, 'urutan' => $urutan++]);
            }
            $baris = static::query()->orderBy('urutan')->get();
        }

        return $baris->mapWithKeys(fn (self $b) => [$b->jenis => ['label' => $b->label, 'bobot' => (int) $b->bobot]])->all()
            ?: collect(self::BAWAAN)->map(fn ($v) => ['label' => $v[0], 'bobot' => $v[1]])->all();
    }
}
