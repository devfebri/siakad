<?php

namespace Database\Seeders;

use App\Kelas;
use App\Support\Akademik;
use App\UsulanKenaikan;
use Illuminate\Database\Seeder;

/**
 * Data contoh proses tahunan: usulan kenaikan (satu siswa tinggal kelas per rombel, bila rombel punya lebih dari 2 siswa).
 */
class ProsesTahunanSeeder extends Seeder
{
    public function run(): void
    {
        $semester = Akademik::semesterAktif();

        foreach (Kelas::where('tahun_ajaran_id', $semester->tahun_ajaran_id)->orderBy('nama_kelas')->get() as $rombel) {
            foreach ($rombel->anggota()->where('siswa.status', 'aktif')->orderBy('nama_siswa')->get() as $n => $siswa) {
                UsulanKenaikan::updateOrCreate(
                    ['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $semester->tahun_ajaran_id],
                    ['rombel_id' => $rombel->id, 'usulan' => $n === 0 && $rombel->anggota()->count() > 2 ? 'tinggal' : 'naik', 'catatan' => $n === 0 ? 'Perlu pendampingan belajar.' : null],
                );
            }
        }
        Kelas::sinkronkanPeranWali();
    }
}
