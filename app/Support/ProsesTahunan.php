<?php

namespace App\Support;

use App\Alumni;
use App\Jadwal;
use App\Kelas;
use App\Kenaikan;
use App\LogAktivitas;
use App\OrangTua;
use App\Semester;
use App\Siswa;
use App\TahunAjaran;
use App\UsulanKenaikan;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses akhir tahun ajaran: buka tahun ajaran baru (draf) dengan rombel salinan, kenaikan/tinggal/kelulusan per siswa,
 * lalu aktivasi yang menutup tahun ajaran lama. Selama masih draf, semua proses dapat dibatalkan; efek yang tidak bisa
 * dibatalkan (alumni, akun nonaktif) baru diterapkan saat aktivasi.
 */
class ProsesTahunan
{
    public static function tahunAktif(): ?TahunAjaran
    {
        return TahunAjaran::query()->where('status', 'aktif')->first();
    }

    /** Tahun ajaran draf yang dibuka dari tahun ajaran aktif (tujuan proses tahunan). */
    public static function tahunBaru(): ?TahunAjaran
    {
        $aktif = self::tahunAktif();

        return $aktif ? TahunAjaran::query()->where('status', 'draf')->where('dari_tahun_id', $aktif->id)->with('semester')->first() : null;
    }

    /** Buka tahun ajaran baru berstatus Draf dan salin rombel (opsional wali kelas dan jadwal). */
    public static function bukaTahunBaru(string $nama, bool $salinWali = false, bool $salinJadwal = false): TahunAjaran
    {
        $asal = self::tahunAktif() ?? throw ValidationException::withMessages(['nama' => 'Belum ada tahun ajaran aktif. Atur dulu di menu Tahun Ajaran.']);
        if (self::tahunBaru()) {
            throw ValidationException::withMessages(['nama' => 'Sudah ada tahun ajaran draf. Selesaikan atau batalkan prosesnya dulu.']);
        }

        return DB::transaction(function () use ($nama, $asal, $salinWali, $salinJadwal) {
            $baru = TahunAjaran::buat($nama);
            $baru->update(['status' => 'draf', 'dari_tahun_id' => $asal->id]);

            $peta = []; // id rombel lama => rombel baru
            foreach (Kelas::where('tahun_ajaran_id', $asal->id)->orderBy('nama_kelas')->get() as $k) {
                $peta[$k->id] = Kelas::create([
                    'nama_kelas' => $k->nama_kelas, 'tingkat' => $k->tingkat, 'tahun_ajaran_id' => $baru->id,
                    'wali_guru_id' => $salinWali ? $k->wali_guru_id : null,
                ]);
            }

            $jadwal = 0;
            if ($salinJadwal) {
                // jadwal semester terakhir yang punya jadwal menjadi jadwal semester Ganjil tahun baru
                $terakhir = Semester::where('tahun_ajaran_id', $asal->id)->orderByDesc('mulai')->get()->first(fn (Semester $s) => Jadwal::where('semester_id', $s->id)->exists());
                $ganjil = $baru->semester->firstWhere('jenis', 'Ganjil');
                foreach ($terakhir ? Jadwal::where('semester_id', $terakhir->id)->get() : [] as $j) {
                    if (isset($peta[$j->kelas_id])) {
                        Jadwal::create(['kelas_id' => $peta[$j->kelas_id]->id, 'mapel_id' => $j->mapel_id, 'guru_id' => $j->guru_id, 'hari' => $j->hari,
                            'jam_mulai' => $j->jam_mulai, 'jam_selesai' => $j->jam_selesai, 'ruang' => $j->ruang, 'semester_id' => $ganjil->id]);
                        $jadwal++;
                    }
                }
            }

            LogAktivitas::catat('tahun.buka', "Tahun ajaran baru {$nama} dibuka (draf) dari {$asal->nama}: ".count($peta)." rombel, {$jadwal} jadwal disalin.", null, ['dari' => $asal->nama, 'rombel' => count($peta), 'jadwal' => $jadwal]);

            return $baru;
        });
    }

    /** Rombel tujuan bawaan: naik = tingkat berikutnya dengan paralel sama (atau yang pertama); tinggal = nama sama. */
    public static function tujuanBawaan(Kelas $asal, TahunAjaran $baru, string $hasil): ?Kelas
    {
        $rombelBaru = Kelas::where('tahun_ajaran_id', $baru->id)->orderBy('nama_kelas')->get();

        if ($hasil === 'tinggal') {
            return $rombelBaru->firstWhere('nama_kelas', $asal->nama_kelas);
        }

        $tingkat = Tingkat::angka($asal->tingkat, $asal->nama_kelas);
        if (! $tingkat) {
            return null;
        }
        $calon = $rombelBaru->filter(fn (Kelas $k) => Tingkat::angka($k->tingkat, $k->nama_kelas) === $tingkat + 1);

        return $calon->first(fn (Kelas $k) => Tingkat::paralel($k->nama_kelas) === Tingkat::paralel($asal->nama_kelas)) ?? $calon->first();
    }

    public static function tingkatAkhir(Kelas $rombel): bool
    {
        return Tingkat::angka($rombel->tingkat, $rombel->nama_kelas) === Tingkat::akhir();
    }

    /**
     * Proses satu siswa. $tujuan wajib untuk naik/tinggal (rombel di tahun ajaran baru).
     *
     * @throws ValidationException
     */
    public static function prosesSiswa(Siswa $siswa, Kelas $asal, TahunAjaran $baru, string $hasil, ?Kelas $tujuan = null, ?string $noIjazah = null): Kenaikan
    {
        $akhir = self::tingkatAkhir($asal);
        $galat = fn (string $pesan) => throw ValidationException::withMessages(['siswa' => "{$siswa->nama_siswa}: {$pesan}"]);

        if (! array_key_exists($hasil, Kenaikan::HASIL)) {
            $galat('hasil tidak dikenal.');
        }
        if ($siswa->status !== 'aktif') {
            $galat('siswa tidak berstatus aktif.');
        }
        if (in_array($hasil, ['lulus', 'tidak_lulus'], true) && ! $akhir) {
            $galat('kelulusan hanya untuk rombel tingkat akhir.');
        }
        if ($hasil === 'naik' && $akhir) {
            $galat('rombel tingkat akhir tidak naik kelas; pilih lulus, tidak lulus, atau tinggal kelas.');
        }
        if (in_array($hasil, ['naik', 'tinggal'], true)) {
            if (! $tujuan || $tujuan->tahun_ajaran_id !== $baru->id) {
                $galat('rombel tujuan di tahun ajaran baru belum ditemukan. Buat atau pilih rombel tujuannya.');
            }
            $tingkatAsal = Tingkat::angka($asal->tingkat, $asal->nama_kelas);
            $tingkatTujuan = Tingkat::angka($tujuan->tingkat, $tujuan->nama_kelas);
            if ($tingkatAsal && $tingkatTujuan && $tingkatTujuan !== $tingkatAsal + ($hasil === 'naik' ? 1 : 0)) {
                $galat($hasil === 'naik' ? 'rombel tujuan harus satu tingkat di atas.' : 'rombel tujuan harus bertingkat sama.');
            }
        }

        return DB::transaction(function () use ($siswa, $asal, $baru, $hasil, $tujuan, $noIjazah) {
            self::batalSiswa($siswa, $baru);

            if ($tujuan && in_array($hasil, ['naik', 'tinggal'], true)) {
                $tujuan->tempatkan($siswa);
            }

            $k = Kenaikan::create([
                'siswa_id' => $siswa->id, 'tahun_ajaran_id' => $asal->tahun_ajaran_id, 'tahun_ajaran_tujuan_id' => $baru->id,
                'rombel_asal_id' => $asal->id, 'rombel_tujuan_id' => in_array($hasil, ['naik', 'tinggal'], true) ? $tujuan->id : null,
                'hasil' => $hasil, 'no_ijazah' => $noIjazah ?: null, 'diproses_oleh' => auth()->id(),
            ]);

            LogAktivitas::catat('kenaikan.proses', "{$siswa->nama_siswa} ({$asal->nama_kelas}): ".Kenaikan::HASIL[$hasil].($tujuan ? " → {$tujuan->nama_kelas}" : '').'.', null, ['siswa_id' => $siswa->id, 'hasil' => $hasil]);

            return $k;
        });
    }

    /**
     * Proses seluruh siswa satu rombel sesuai usulan wali kelas.
     *
     * @return array{diproses: int, dilewati: int, gagal: array<int, string>}
     */
    public static function prosesRombel(Kelas $asal, TahunAjaran $baru, bool $tanpaUsulanDianggapNaik, ?Kelas $tujuanNaik = null, ?Kelas $tujuanTinggal = null): array
    {
        $usulan = UsulanKenaikan::where('rombel_id', $asal->id)->where('tahun_ajaran_id', $asal->tahun_ajaran_id)->pluck('usulan', 'siswa_id');
        $sudah = Kenaikan::where('tahun_ajaran_id', $asal->tahun_ajaran_id)->pluck('siswa_id')->flip();
        $akhir = self::tingkatAkhir($asal);
        $hasil = ['diproses' => 0, 'dilewati' => 0, 'gagal' => []];

        foreach ($asal->anggota()->where('siswa.status', 'aktif')->orderBy('nama_siswa')->get() as $siswa) {
            if ($sudah->has($siswa->id)) {
                continue;
            }

            $pilihan = $usulan[$siswa->id] ?? ($tanpaUsulanDianggapNaik ? ($akhir ? 'lulus' : 'naik') : null);
            if (! $pilihan) {
                $hasil['dilewati']++;

                continue;
            }

            $tujuan = match ($pilihan) {
                'naik' => $tujuanNaik ?? self::tujuanBawaan($asal, $baru, 'naik'),
                'tinggal' => $tujuanTinggal ?? self::tujuanBawaan($asal, $baru, 'tinggal'),
                default => null,
            };

            try {
                self::prosesSiswa($siswa, $asal, $baru, $pilihan, $tujuan);
                $hasil['diproses']++;
            } catch (ValidationException $e) {
                $hasil['gagal'][] = $e->validator->errors()->first();
            }
        }

        return $hasil;
    }

    /** Batalkan proses satu siswa (hanya selama tahun ajaran tujuan masih draf). */
    public static function batalSiswa(Siswa $siswa, TahunAjaran $baru): bool
    {
        abort_unless($baru->status === 'draf', 409, 'Tahun ajaran tujuan sudah aktif; proses tidak dapat dibatalkan.');

        $k = Kenaikan::where('siswa_id', $siswa->id)->where('tahun_ajaran_tujuan_id', $baru->id)->first();
        if (! $k) {
            return false;
        }

        DB::table('rombel_siswa')->where('siswa_id', $siswa->id)->where('tahun_ajaran_id', $baru->id)->delete();
        $k->delete();

        return true;
    }

    public static function batalSemua(TahunAjaran $baru): int
    {
        $jumlah = 0;
        DB::transaction(function () use ($baru, &$jumlah) {
            foreach (Kenaikan::where('tahun_ajaran_tujuan_id', $baru->id)->with('siswa')->get() as $k) {
                $jumlah += (int) self::batalSiswa($k->siswa, $baru);
            }
            LogAktivitas::catat('kenaikan.batal', "Seluruh proses kenaikan ke TA {$baru->nama} dibatalkan ({$jumlah} siswa).", null, ['jumlah' => $jumlah]);
        });

        return $jumlah;
    }

    /** Siswa aktif di tahun ajaran asal yang belum punya hasil kenaikan ke tahun ajaran tujuan. */
    public static function belumDiproses(TahunAjaran $asal, TahunAjaran $baru): Collection
    {
        $sudah = Kenaikan::where('tahun_ajaran_tujuan_id', $baru->id)->pluck('siswa_id');

        return Siswa::query()->aktif()
            ->join('rombel_siswa', 'rombel_siswa.siswa_id', '=', 'siswa.id')
            ->join('rombel', 'rombel.id', '=', 'rombel_siswa.rombel_id')
            ->where('rombel_siswa.tahun_ajaran_id', $asal->id)
            ->whereNotIn('siswa.id', $sudah)
            ->orderBy('rombel.nama_kelas')->orderBy('siswa.nama_siswa')
            ->get(['siswa.id', 'siswa.nama_siswa', 'rombel.nama_kelas as rombel']);
    }

    /**
     * Aktifkan semester. Untuk tahun ajaran draf hasil Proses Tahunan: tolak bila masih ada siswa belum diproses,
     * terapkan kelulusan (alumni, akun nonaktif), lalu tahun ajaran lama otomatis Ditutup.
     */
    public static function aktifkan(Semester $semester): void
    {
        $ta = $semester->tahunAjaran;
        $asal = $ta->status === 'draf' && $ta->dari_tahun_id ? TahunAjaran::find($ta->dari_tahun_id) : null;

        DB::transaction(function () use ($semester, $ta, $asal) {
            if ($asal && $asal->status === 'aktif') {
                $belum = self::belumDiproses($asal, $ta);
                if ($belum->isNotEmpty()) {
                    throw new HttpResponseException(response()->json([
                        'message' => "Tidak dapat mengaktifkan: {$belum->count()} siswa belum diproses kenaikan/kelulusannya.",
                        'belum_diproses' => $belum->map(fn ($s) => ['id' => $s->id, 'nama' => $s->nama_siswa, 'rombel' => $s->rombel])->values(),
                    ], 409));
                }
                self::terapkanKelulusan($asal, $ta);
            }

            $taLama = Semester::query()->where('aktif', true)->value('tahun_ajaran_id');
            $semester->aktifkan();
            if ($taLama !== $semester->tahun_ajaran_id) {
                Kelas::sinkronkanSiswaAktif();
            }

            if ($asal) {
                LogAktivitas::catat('tahun.aktifkan', "Tahun ajaran {$ta->nama} diaktifkan; {$asal->nama} ditutup.", null, ['tahun' => $ta->nama, 'ditutup' => $asal->nama]);
            }
        });
    }

    /** Siswa lulus/tidak lulus dipindah ke Data Alumni dan akunnya dinonaktifkan (akun orang tua bila tak punya anak aktif lain). */
    private static function terapkanKelulusan(TahunAjaran $asal, TahunAjaran $baru): void
    {
        $angkatan = (int) substr($asal->nama, -4);
        $ortuTerdampak = collect();

        foreach (Kenaikan::where('tahun_ajaran_tujuan_id', $baru->id)->whereIn('hasil', ['lulus', 'tidak_lulus'])->with('siswa.user', 'siswa.orangTua.user')->get() as $k) {
            $siswa = $k->siswa;
            Alumni::updateOrCreate(['siswa_id' => $siswa->id], [
                'angkatan' => $angkatan, 'tanggal_lulus' => today(), 'status' => $k->hasil, 'no_ijazah' => $k->no_ijazah,
            ]);
            $siswa->forceFill(['status' => $k->hasil, 'kelas_id' => null])->saveQuietly();
            $siswa->user?->forceFill(['aktif' => false])->save();
            $ortuTerdampak = $ortuTerdampak->merge($siswa->orangTua);

            LogAktivitas::catat('kelulusan', "{$siswa->nama_siswa}: ".Kenaikan::HASIL[$k->hasil]." (angkatan {$angkatan}).", null, ['siswa_id' => $siswa->id, 'no_ijazah' => $k->no_ijazah]);
        }

        foreach ($ortuTerdampak->unique('id') as $ortu) {
            /** @var OrangTua $ortu */
            if (! $ortu->anak()->where('siswa.status', 'aktif')->exists()) {
                $ortu->user?->forceFill(['aktif' => false])->save();
                LogAktivitas::catat('kelulusan.ortu', "Akun orang tua {$ortu->nama} dinonaktifkan (tidak punya anak aktif).", null, ['orang_tua_id' => $ortu->id]);
            }
        }
    }
}
