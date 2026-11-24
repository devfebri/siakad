<?php

namespace App\Support\Api;

use App\Guru;
use App\Jadwal;
use App\Kelas;
use App\Mapel;
use App\RombelSiswa;
use App\Semester;
use App\Siswa;
use App\TahunAjaran;
use Illuminate\Database\Eloquent\Model;

/**
 * Daftar data induk yang dibuka lewat API v1 dan dikabarkan lewat webhook. Satu tempat untuk bentuk datanya,
 * supaya isi API dan isi webhook selalu sama. Tidak pernah memuat password atau data login.
 */
class Sumber
{
    /** nama sumber => [model, relasi yang dimuat, filter (parameter => kolom), pemeta]. */
    public static function semua(): array
    {
        $waktu = fn ($t) => $t?->toIso8601String();
        $tanggal = fn ($t) => $t?->format('Y-m-d');

        return [
            'tahun-ajaran' => [
                'model' => TahunAjaran::class, 'with' => ['semester'], 'filter' => ['status' => 'status'],
                'peta' => fn (TahunAjaran $m) => [
                    'id' => $m->id, 'nama' => $m->nama, 'mulai' => $tanggal($m->mulai), 'selesai' => $tanggal($m->selesai), 'status' => $m->status,
                    'semester' => $m->semester->map(fn ($s) => self::semester($s))->all(), 'updated_at' => $waktu($m->updated_at),
                ],
            ],
            'rombel' => [
                'model' => Kelas::class, 'with' => [], 'filter' => ['tahun_ajaran_id' => 'tahun_ajaran_id', 'tingkat' => 'tingkat'],
                'peta' => fn (Kelas $m) => [
                    'id' => $m->id, 'nama' => $m->nama_kelas, 'tingkat' => $m->tingkat, 'tahun_ajaran_id' => $m->tahun_ajaran_id,
                    'wali_guru_id' => $m->wali_guru_id, 'updated_at' => $waktu($m->updated_at),
                ],
            ],
            'rombel-siswa' => [
                'model' => RombelSiswa::class, 'with' => [], 'filter' => ['rombel_id' => 'rombel_id', 'siswa_id' => 'siswa_id', 'tahun_ajaran_id' => 'tahun_ajaran_id'],
                'peta' => fn (RombelSiswa $m) => [
                    'id' => $m->id, 'rombel_id' => $m->rombel_id, 'siswa_id' => $m->siswa_id, 'tahun_ajaran_id' => $m->tahun_ajaran_id, 'updated_at' => $waktu($m->updated_at),
                ],
            ],
            'mapel' => [
                'model' => Mapel::class, 'with' => [], 'filter' => [],
                'peta' => fn (Mapel $m) => ['id' => $m->id, 'nama' => $m->mapel_nama, 'updated_at' => $waktu($m->updated_at)],
            ],
            'guru' => [
                'model' => Guru::class, 'with' => ['user:id,username,email,aktif'], 'filter' => ['mapel_id' => 'mapel_id'],
                'peta' => fn (Guru $m) => [
                    'id' => $m->id, 'nip' => $m->nip_guru, 'nama' => $m->nama_guru, 'jenis_kelamin' => $m->jenis_kelamin, 'mapel_id' => $m->mapel_id,
                    'no_hp' => $m->no_hp, 'tanggal_lahir' => $tanggal($m->tanggal_lahir), 'user_id' => $m->user_id,
                    'username' => $m->user?->username, 'email' => $m->user?->email, 'aktif' => $m->user ? (bool) $m->user->aktif : null,
                    'updated_at' => $waktu($m->updated_at),
                ],
            ],
            'siswa' => [
                'model' => Siswa::class, 'with' => ['user:id,username,email,aktif'], 'filter' => ['rombel_id' => 'kelas_id', 'status' => 'status'],
                'peta' => fn (Siswa $m) => [
                    'id' => $m->id, 'nisn' => $m->nisn, 'nama' => $m->nama_siswa, 'jenis_kelamin' => $m->jenis_kelamin, 'agama' => $m->agama,
                    'tanggal_lahir' => $tanggal($m->tanggal_lahir), 'status' => $m->status, 'rombel_id' => $m->kelas_id, 'user_id' => $m->user_id,
                    'username' => $m->user?->username, 'email' => $m->user?->email, 'aktif' => $m->user ? (bool) $m->user->aktif : null,
                    'updated_at' => $waktu($m->updated_at),
                ],
            ],
            'jadwal' => [
                'model' => Jadwal::class, 'with' => [], 'filter' => ['rombel_id' => 'kelas_id', 'guru_id' => 'guru_id', 'mapel_id' => 'mapel_id', 'semester_id' => 'semester_id'],
                'peta' => fn (Jadwal $m) => [
                    'id' => $m->id, 'rombel_id' => $m->kelas_id, 'mapel_id' => $m->mapel_id, 'guru_id' => $m->guru_id, 'semester_id' => $m->semester_id,
                    'hari' => (int) $m->hari, 'hari_nama' => Jadwal::HARI[$m->hari] ?? null, 'jam_mulai' => substr((string) $m->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $m->jam_selesai, 0, 5), 'ruang' => $m->ruang, 'updated_at' => $waktu($m->updated_at),
                ],
            ],
        ];
    }

    public static function semester(Semester $s): array
    {
        return [
            'id' => $s->id, 'tahun_ajaran_id' => $s->tahun_ajaran_id, 'jenis' => $s->jenis, 'mulai' => $s->mulai?->format('Y-m-d'),
            'selesai' => $s->selesai?->format('Y-m-d'), 'kkm' => $s->kkm, 'aktif' => (bool) $s->aktif,
        ];
    }

    /** Nama sumber untuk sebuah model (untuk webhook), atau null bila bukan data induk. */
    public static function untukModel(Model $model): ?string
    {
        foreach (self::semua() as $nama => $d) {
            if ($model instanceof $d['model']) {
                return $nama;
            }
        }

        return null;
    }

    /** Bentuk data satu baris. */
    public static function petakan(string $sumber, Model $model): array
    {
        $d = self::semua()[$sumber];
        $model->loadMissing($d['with']);

        return $d['peta']($model);
    }
}
