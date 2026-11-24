<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TahunAjaran extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'tahun_ajaran';
    protected $fillable = ['nama', 'mulai', 'selesai', 'status', 'dari_tahun_id'];

    public const STATUS = ['draf' => 'Draf', 'aktif' => 'Aktif', 'ditutup' => 'Ditutup'];
    protected $casts = ['mulai' => 'date', 'selesai' => 'date'];

    public function semester()
    {
        return $this->hasMany(Semester::class)->orderByRaw("jenis = 'Genap'");
    }

    public function isDitutup(): bool
    {
        return $this->status === 'ditutup';
    }

    public function isAktif(): bool
    {
        return $this->semester->contains('aktif', true);
    }

    /** Buat tahun ajaran beserta semester Ganjil (Jul–Des) dan Genap (Jan–Jun). KKM disalin dari semester terakhir. */
    public static function buat(string $nama): self
    {
        [$awal, $akhir] = array_map('intval', explode('/', $nama));
        $kkm = (int) (Semester::query()->orderByDesc('id')->value('kkm') ?: 70);

        $ta = self::create(['nama' => $nama, 'mulai' => "$awal-07-01", 'selesai' => "$akhir-06-30"]);
        $ta->semester()->create(['jenis' => 'Ganjil', 'mulai' => "$awal-07-01", 'selesai' => "$awal-12-31", 'kkm' => $kkm]);
        $ta->semester()->create(['jenis' => 'Genap', 'mulai' => "$akhir-01-01", 'selesai' => "$akhir-06-30", 'kkm' => $kkm]);

        return $ta->load('semester');
    }
}
