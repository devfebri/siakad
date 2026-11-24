<?php

namespace App;

use App\Support\Akademik;
use App\Support\SekolahAktif;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Rombongan belajar (rombel): kelas pada satu tahun ajaran, dengan wali kelas dan daftar anggota. */
class Kelas extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'rombel';
    protected $fillable = ['nama_kelas', 'tingkat', 'tahun_ajaran_id', 'wali_guru_id'];

    protected static function booted(): void
    {
        // rombel baru masuk tahun ajaran aktif bila tidak ditentukan
        static::creating(function (self $k) {
            $k->tahun_ajaran_id ??= Akademik::semesterAktif()->tahun_ajaran_id;
        });
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function wali()
    {
        return $this->belongsTo(Guru::class, 'wali_guru_id');
    }

    /** Siswa yang rombel aktifnya ini (siswa.kelas_id; hanya berlaku untuk rombel pada tahun ajaran aktif). */
    public function siswa()
    {
        return $this->hasMany(Siswa::class);
    }

    /** Anggota rombel menurut catatan per tahun ajaran (juga untuk tahun ajaran lampau). */
    public function anggota()
    {
        return $this->belongsToMany(Siswa::class, 'rombel_siswa', 'rombel_id', 'siswa_id')->withTimestamps();
    }

    public function jadwal()
    {
        return $this->hasMany(Jadwal::class);
    }

    /** Rombel pada tahun ajaran aktif. */
    public function scopeTahunAktif(Builder $query): Builder
    {
        return $query->where('tahun_ajaran_id', Akademik::aktif()['tahun_ajaran_id'] ?? 0);
    }

    /** Catat siswa sebagai anggota rombel ini (menggantikan rombelnya di tahun ajaran yang sama). */
    public function tempatkan(Siswa $siswa): void
    {
        DB::table('rombel_siswa')->updateOrInsert(
            ['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $this->tahun_ajaran_id],
            ['sekolah_id' => $this->sekolah_id, 'rombel_id' => $this->id, 'updated_at' => now(), 'created_at' => now()],
        );

        if ($this->tahun_ajaran_id === (Akademik::aktif()['tahun_ajaran_id'] ?? null)) {
            DB::table('siswa')->where('id', $siswa->id)->update(['kelas_id' => $this->id]);
            $siswa->kelas_id = $this->id;
        }
    }

    /** Keluarkan siswa dari rombel ini. */
    public function keluarkan(Siswa $siswa): void
    {
        DB::table('rombel_siswa')->where('rombel_id', $this->id)->where('siswa_id', $siswa->id)->delete();

        if ((int) $siswa->kelas_id === $this->id) {
            DB::table('siswa')->where('id', $siswa->id)->update(['kelas_id' => null]);
            $siswa->kelas_id = null;
        }
    }

    /** Setelah tahun ajaran aktif berganti: siswa.kelas_id mengikuti rombel mereka pada tahun ajaran aktif (kosong bila belum ditempatkan). */
    public static function sinkronkanSiswaAktif(): void
    {
        $taId = Akademik::aktif()['tahun_ajaran_id'] ?? 0;
        $peta = DB::table('rombel_siswa')->where('sekolah_id', SekolahAktif::instance()->id())->where('tahun_ajaran_id', $taId)->pluck('rombel_id', 'siswa_id');

        foreach (Siswa::query()->get(['id', 'kelas_id']) as $s) {
            $baru = $peta[$s->id] ?? null;
            if ($s->kelas_id !== $baru) {
                DB::table('siswa')->where('id', $s->id)->update(['kelas_id' => $baru]);
            }
        }
    }

    /** Peran wali_kelas mengikuti penugasan wali: guru yang menjadi wali di rombel mana pun mendapat peran. */
    public static function sinkronkanPeranWali(): void
    {
        $wali = static::query()->whereNotNull('wali_guru_id')->pluck('wali_guru_id')->unique();

        foreach (Guru::query()->with('user')->get() as $guru) {
            if (! $guru->user) {
                continue;
            }
            if ($wali->contains($guru->id)) {
                $guru->user->tambahPeran('wali_kelas');
            } elseif ($guru->user->punyaPeran('wali_kelas')) {
                $guru->user->setPeran(array_values(array_diff($guru->user->kodePeran(), ['wali_kelas'])));
            }
        }
    }
}
