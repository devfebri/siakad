<?php

namespace Database\Seeders;

use App\Absensi;
use App\Guru;
use App\Jadwal;
use App\Kelas;
use App\Nilai;
use App\PpdbPendaftar;
use App\Siswa;
use App\Support\Akademik;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/** Data demo untuk dashboard: jadwal pelajaran, kehadiran 2 pekan terakhir, nilai, dan beberapa pendaftar PPDB. */
class AkademikSeeder extends Seeder
{
    public function run(): void
    {
        $periode = ['semester_id' => Akademik::semesterAktif()->id];
        $kelas = Kelas::orderBy('id')->first();
        $guru = Guru::whereHas('user', fn ($q) => $q->denganPeran('guru'))->orderBy('id')->get();
        if (! $kelas || $guru->isEmpty()) {
            return;
        }

        $slot = [['07:00:00', '08:30:00'], ['08:30:00', '10:00:00'], ['10:15:00', '11:45:00']];
        foreach (range(1, 5) as $hari) {
            foreach ($slot as $i => [$mulai, $selesai]) {
                $g = $guru[($hari + $i) % $guru->count()];
                Jadwal::updateOrCreate(
                    ['kelas_id' => $kelas->id, 'hari' => $hari, 'jam_mulai' => $mulai, ...$periode],
                    ['mapel_id' => $g->mapel_id, 'guru_id' => $g->id, 'jam_selesai' => $selesai, 'ruang' => 'R-'.($i + 1)],
                );
            }
        }

        $siswa = Siswa::where('kelas_id', $kelas->id)->get();
        for ($d = 13; $d >= 0; $d--) {
            $tgl = today()->subDays($d);
            if ($tgl->isWeekend()) {
                continue;
            }
            foreach ($siswa as $n => $s) {
                $status = ($d + $n) % 9 === 0 ? 'sakit' : (($d + $n) % 13 === 0 ? 'alpa' : 'hadir');
                if (! Absensi::where('siswa_id', $s->id)->whereDate('tanggal', $tgl)->exists()) {
                    Absensi::create(['siswa_id' => $s->id, 'kelas_id' => $kelas->id, 'tanggal' => $tgl, 'status' => $status]);
                }
            }
        }

        foreach ($siswa as $n => $s) {
            foreach ($guru as $g) {
                foreach (['tugas' => 78, 'uh' => 82, 'uts' => 75, 'uas' => 80] as $jenis => $dasar) {
                    Nilai::updateOrCreate(
                        ['siswa_id' => $s->id, 'mapel_id' => $g->mapel_id, 'jenis' => $jenis, ...$periode],
                        ['kelas_id' => $kelas->id, 'nilai' => min(100, $dasar + ($n * 4) % 15)],
                    );
                }
            }
        }

        foreach ([['Aulia Rahma', 'Reguler', 'menunggu'], ['Bagas Saputra', 'Prestasi', 'diterima'], ['Citra Dewi', 'Zonasi', 'ditolak']] as $i => [$nama, $jalur, $status]) {
            if (PpdbPendaftar::where('nisn', '99000000'.($i + 1))->exists()) {
                continue;
            }
            PpdbPendaftar::daftar([
                'tahun_ajaran' => Akademik::aktif()['tahun_ajaran'], 'nama' => $nama, 'nisn' => '99000000'.($i + 1), 'jenis_kelamin' => $i === 1 ? 'Laki-Laki' : 'Perempuan',
                'tempat_lahir' => 'Jambi', 'tanggal_lahir' => '2010-0'.($i + 1).'-10', 'asal_sekolah' => 'SMP Contoh',
                'alamat' => 'Jl. Contoh No. '.($i + 1), 'nama_ortu' => 'Orang Tua '.$nama, 'telepon' => '08120000000'.$i,
                'jalur' => $jalur, 'status' => $status,
            ]);
        }
    }
}
