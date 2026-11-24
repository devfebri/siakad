<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Semester extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'semester';
    protected $fillable = ['tahun_ajaran_id', 'jenis', 'mulai', 'selesai', 'kkm', 'aktif'];
    protected $casts = ['mulai' => 'date', 'selesai' => 'date', 'aktif' => 'boolean', 'kkm' => 'integer'];

    public const JENIS = ['Ganjil', 'Genap'];

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function getLabelAttribute(): string
    {
        return 'TA '.$this->tahunAjaran?->nama.' · Semester '.$this->jenis;
    }

    /** Hanya satu semester aktif per sekolah. */
    public function aktifkan(): void
    {
        DB::transaction(function () {
            static::query()->where('aktif', true)->update(['aktif' => false]);
            $this->forceFill(['aktif' => true])->save();

            // status tahun ajaran mengikuti semester aktif: yang sedang berjalan Aktif, yang sebelumnya Aktif menjadi Ditutup (baca-saja)
            TahunAjaran::query()->where('status', 'aktif')->where('id', '!=', $this->tahun_ajaran_id)->update(['status' => 'ditutup']);
            TahunAjaran::query()->whereKey($this->tahun_ajaran_id)->update(['status' => 'aktif']);
        });
    }

    public function dipakai(): bool
    {
        return Jadwal::where('semester_id', $this->id)->exists() || Nilai::where('semester_id', $this->id)->exists() || Absensi::where('semester_id', $this->id)->exists();
    }

    public static function untukTanggal($tanggal): ?self
    {
        $t = \Illuminate\Support\Carbon::parse($tanggal)->toDateString();

        return static::query()->whereDate('mulai', '<=', $t)->whereDate('selesai', '>=', $t)->orderByDesc('aktif')->first();
    }

    public static function cari(string $tahun, string $jenis): ?self
    {
        return static::query()->where('jenis', $jenis)->whereHas('tahunAjaran', fn ($q) => $q->where('nama', $tahun))->with('tahunAjaran')->first();
    }
}
