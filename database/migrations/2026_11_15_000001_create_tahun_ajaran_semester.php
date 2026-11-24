<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahun ajaran dan semester menjadi tabel (sebelumnya string di jadwal/nilai dan nilai di Pengaturan).
 * Bobot nilai (sebelumnya konstanta) dan KKM (sebelumnya Pengaturan) juga pindah ke tabel.
 * Jadwal, absensi, dan nilai kini menunjuk semester_id.
 */
return new class extends Migration
{
    private const BOBOT = [['tugas', 'Tugas', 20], ['uh', 'Ulangan Harian', 20], ['uts', 'UTS', 25], ['uas', 'UAS', 35]];

    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->string('nama', 9); // 2026/2027
            $table->date('mulai');
            $table->date('selesai');
            $table->timestamps();

            $table->unique(['sekolah_id', 'nama']);
        });

        Schema::create('semester', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->unsignedBigInteger('tahun_ajaran_id')->index();
            $table->string('jenis', 6); // Ganjil | Genap
            $table->date('mulai');
            $table->date('selesai');
            $table->unsignedTinyInteger('kkm')->default(70);
            $table->boolean('aktif')->default(false);
            $table->timestamps();

            $table->unique(['tahun_ajaran_id', 'jenis']);
        });

        Schema::create('bobot_nilai', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sekolah_id')->index();
            $table->string('jenis', 12); // tugas | uh | uts | uas
            $table->string('label', 40);
            $table->unsignedTinyInteger('bobot');
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['sekolah_id', 'jenis']);
        });

        foreach (['jadwal', 'absensi', 'nilai'] as $nama) {
            Schema::table($nama, function (Blueprint $table) {
                $table->unsignedBigInteger('semester_id')->nullable()->index();
            });
        }

        $this->pindahkanData();

        Schema::table('nilai', function (Blueprint $table) {
            $table->dropUnique('nilai_unik');
        });
        Schema::table('nilai', function (Blueprint $table) {
            $table->dropColumn(['tahun_ajaran', 'semester']);
            $table->unique(['siswa_id', 'mapel_id', 'semester_id', 'jenis'], 'nilai_unik');
        });
        Schema::table('jadwal', function (Blueprint $table) {
            $table->dropColumn(['tahun_ajaran', 'semester']);
        });

        if (Schema::hasTable('site_settings')) {
            DB::table('site_settings')->whereIn('key', ['tahun_ajaran', 'semester', 'kkm'])->delete();
        }
    }

    /** Buat tahun ajaran/semester per sekolah dari data yang ada, lalu isi semester_id. */
    private function pindahkanData(): void
    {
        $sekarang = now();
        $ganjilSekarang = $sekarang->month >= 7;
        $awalSekarang = $ganjilSekarang ? $sekarang->year : $sekarang->year - 1;

        foreach (DB::table('sekolah')->pluck('id') as $sekolahId) {
            $setelan = Schema::hasTable('site_settings')
                ? DB::table('site_settings')->where('sekolah_id', $sekolahId)->whereIn('key', ['tahun_ajaran', 'semester', 'kkm'])->pluck('value', 'key')->all()
                : [];

            $aktifTahun = ($setelan['tahun_ajaran'] ?? null) ?: $awalSekarang.'/'.($awalSekarang + 1);
            $aktifJenis = ($setelan['semester'] ?? null) ?: ($ganjilSekarang ? 'Ganjil' : 'Genap');
            $kkm = (int) (($setelan['kkm'] ?? null) ?: 70);

            $periode = [[$aktifTahun, $aktifJenis]];
            foreach (['jadwal', 'nilai'] as $tabel) {
                foreach (DB::table($tabel)->where('sekolah_id', $sekolahId)->select('tahun_ajaran', 'semester')->distinct()->get() as $baris) {
                    $periode[] = [$baris->tahun_ajaran, $baris->semester];
                }
            }

            $id = [];
            foreach ($periode as [$tahun, $jenis]) {
                if (! preg_match('/^(\d{4})\/(\d{4})$/', (string) $tahun, $m) || ! in_array($jenis, ['Ganjil', 'Genap'], true) || isset($id["$tahun|$jenis"])) {
                    continue;
                }
                $awal = (int) $m[1];
                $akhir = (int) $m[2];

                $taId = DB::table('tahun_ajaran')->where('sekolah_id', $sekolahId)->where('nama', $tahun)->value('id')
                    ?? DB::table('tahun_ajaran')->insertGetId(['sekolah_id' => $sekolahId, 'nama' => $tahun, 'mulai' => "$awal-07-01", 'selesai' => "$akhir-06-30", 'created_at' => now(), 'updated_at' => now()]);

                $id["$tahun|$jenis"] = DB::table('semester')->insertGetId([
                    'sekolah_id' => $sekolahId, 'tahun_ajaran_id' => $taId, 'jenis' => $jenis,
                    'mulai' => $jenis === 'Ganjil' ? "$awal-07-01" : "$akhir-01-01", 'selesai' => $jenis === 'Ganjil' ? "$awal-12-31" : "$akhir-06-30",
                    'kkm' => $kkm, 'aktif' => "$tahun|$jenis" === "$aktifTahun|$aktifJenis", 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            foreach ($id as $kunci => $semesterId) {
                [$tahun, $jenis] = explode('|', $kunci);
                foreach (['jadwal', 'nilai'] as $tabel) {
                    DB::table($tabel)->where('sekolah_id', $sekolahId)->where('tahun_ajaran', $tahun)->where('semester', $jenis)->update(['semester_id' => $semesterId]);
                }
            }

            foreach (DB::table('semester')->where('sekolah_id', $sekolahId)->get() as $s) {
                DB::table('absensi')->where('sekolah_id', $sekolahId)->whereNull('semester_id')->whereBetween('tanggal', [$s->mulai, $s->selesai])->update(['semester_id' => $s->id]);
            }

            foreach (self::BOBOT as $urutan => [$jenis, $label, $bobot]) {
                DB::table('bobot_nilai')->insert(['sekolah_id' => $sekolahId, 'jenis' => $jenis, 'label' => $label, 'bobot' => $bobot, 'urutan' => $urutan, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('jadwal', function (Blueprint $table) {
            $table->string('tahun_ajaran', 9)->default('');
            $table->string('semester', 6)->default('');
        });
        Schema::table('nilai', function (Blueprint $table) {
            $table->dropUnique('nilai_unik');
            $table->string('tahun_ajaran', 9)->default('');
            $table->string('semester', 6)->default('');
        });

        foreach (DB::table('semester')->join('tahun_ajaran', 'tahun_ajaran.id', '=', 'semester.tahun_ajaran_id')->get(['semester.id', 'semester.jenis', 'tahun_ajaran.nama']) as $s) {
            foreach (['jadwal', 'nilai'] as $tabel) {
                DB::table($tabel)->where('semester_id', $s->id)->update(['tahun_ajaran' => $s->nama, 'semester' => $s->jenis]);
            }
        }

        Schema::table('nilai', function (Blueprint $table) {
            $table->unique(['siswa_id', 'mapel_id', 'tahun_ajaran', 'semester', 'jenis'], 'nilai_unik');
        });
        foreach (['jadwal', 'absensi', 'nilai'] as $nama) {
            Schema::table($nama, function (Blueprint $table) use ($nama) {
                $table->dropIndex($nama.'_semester_id_index');
                $table->dropColumn('semester_id');
            });
        }
        Schema::dropIfExists('bobot_nilai');
        Schema::dropIfExists('semester');
        Schema::dropIfExists('tahun_ajaran');
    }
};
