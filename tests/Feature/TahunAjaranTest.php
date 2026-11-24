<?php

namespace Tests\Feature;

use App\Absensi;
use App\BobotNilai;
use App\Jadwal;
use App\Kelas;
use App\Mapel;
use App\Nilai;
use App\Sekolah;
use App\Semester;
use App\Siswa;
use App\Support\Akademik;
use App\Support\SekolahAktif;
use App\TahunAjaran;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Tahun ajaran, semester (aktif, KKM), bobot nilai, dan semester_id pada jadwal/absensi/nilai. */
class TahunAjaranTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $kelas;
    private Mapel $mapel;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->kelas = Kelas::create(['nama_kelas' => 'X-A']);
        $this->mapel = Mapel::create(['mapel_nama' => 'Matematika']);
        $this->siswa = Siswa::create(['nisn' => '0011', 'nama_siswa' => 'Ani', 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2009-01-01', 'kelas_id' => $this->kelas->id, 'user_id' => User::factory()->create(['role' => 'siswa'])->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function nilai(Semester $s, string $jenis, float $angka): Nilai
    {
        return Nilai::create(['siswa_id' => $this->siswa->id, 'kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'jenis' => $jenis, 'nilai' => $angka, 'semester_id' => $s->id]);
    }

    public function test_tanpa_semester_aktif_dihitung_dari_tanggal_lalu_dibuat_saat_dibutuhkan(): void
    {
        Semester::query()->delete(); // setUp sudah membuat rombel, yang otomatis membuat semester aktif
        TahunAjaran::query()->delete();
        $a = Akademik::aktif();
        $this->assertSame(['2026/2027', 'Ganjil', null], [$a['tahun_ajaran'], $a['semester'], $a['semester_id']]);
        $this->assertSame(0, Semester::count(), 'membaca tidak menulis');

        $s = Akademik::semesterAktif();
        $this->assertSame(1, TahunAjaran::count());
        $this->assertSame(['Ganjil', 'Genap'], TahunAjaran::first()->semester->pluck('jenis')->all());
        $this->assertTrue($s->aktif);
        $this->assertSame($s->id, Akademik::aktif()['semester_id']);
        $this->assertSame($s->id, Akademik::semesterAktif()->id, 'tidak membuat dobel');
        $this->assertSame(1, Semester::where('aktif', true)->count());

        $this->assertSame(['2026/2027', 'Ganjil'], array_values(array_intersect_key(Akademik::aktif(Carbon::parse('2026-03-01')), ['tahun_ajaran' => 1, 'semester' => 1])), 'tetap memakai semester aktif tabel');
    }

    public function test_halaman_hanya_untuk_admin_dan_menampilkan_semester_aktif(): void
    {
        $this->actingAs($this->admin)->get('/admin/tahun-ajaran')->assertOk()->assertSee('2026/2027')->assertSee('Semester Ganjil')->assertSee('Bobot Nilai Akhir');

        $guru = User::factory()->create(['role' => 'guru']);
        $this->actingAs($guru)->get('/admin/tahun-ajaran')->assertRedirect(route('guru/dashboard'));
        $this->actingAs($guru)->postJson('/admin/tahun-ajaran', ['nama' => '2027/2028'])->assertForbidden();
    }

    public function test_tambah_tahun_ajaran_membuat_dua_semester_dengan_kkm_terakhir(): void
    {
        $this->actingAs($this->admin)->get('/admin/tahun-ajaran'); // membuat semester aktif
        Semester::query()->update(['kkm' => 75]);

        $this->postJson('/admin/tahun-ajaran', ['nama' => '2027/2028'])->assertCreated();

        $ta = TahunAjaran::where('nama', '2027/2028')->firstOrFail();
        [$ganjil, $genap] = [$ta->semester[0], $ta->semester[1]];
        $this->assertSame(['Ganjil', '2027-07-01', '2027-12-31', 75, false], [$ganjil->jenis, $ganjil->mulai->toDateString(), $ganjil->selesai->toDateString(), $ganjil->kkm, $ganjil->aktif]);
        $this->assertSame(['Genap', '2028-01-01', '2028-06-30'], [$genap->jenis, $genap->mulai->toDateString(), $genap->selesai->toDateString()]);
    }

    public function test_validasi_tahun_ajaran(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/admin/tahun-ajaran', ['nama' => '2027-2028'])->assertStatus(422)->assertJsonValidationErrors('nama');
        $this->postJson('/admin/tahun-ajaran', ['nama' => '2027/2029'])->assertStatus(422)->assertJsonValidationErrors('nama');
        $this->postJson('/admin/tahun-ajaran', ['nama' => '2027/2028'])->assertCreated();
        $this->postJson('/admin/tahun-ajaran', ['nama' => '2027/2028'])->assertStatus(422)->assertJsonValidationErrors('nama');
    }

    public function test_mengganti_semester_aktif_mengubah_data_yang_tampil(): void
    {
        $this->actingAs($this->admin);
        $ganjil = Akademik::semesterAktif();
        $genap = $ganjil->tahunAjaran->semester->firstWhere('jenis', 'Genap');
        $dasar = ['kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'hari' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00'];
        Jadwal::create($dasar + ['semester_id' => $ganjil->id, 'ruang' => 'Ganjil-1']);
        Jadwal::create($dasar + ['semester_id' => $genap->id, 'ruang' => 'Genap-1']);

        $this->getJson('/admin/jadwal/data?draw=1&start=0&length=10')->assertJsonCount(1, 'data')->assertJsonPath('data.0.ruang', 'Ganjil-1');

        $this->postJson("/admin/semester/{$genap->id}/aktifkan")->assertOk();

        $this->assertSame([false, true], [$ganjil->fresh()->aktif, $genap->fresh()->aktif]);
        $this->assertSame(1, Semester::where('aktif', true)->count());
        $this->getJson('/admin/jadwal/data?draw=1&start=0&length=10')->assertJsonPath('data.0.ruang', 'Genap-1');
        $this->get('/admin/dashboard')->assertSee('TA 2026/2027 · Semester Genap');
    }

    public function test_nilai_disimpan_ke_semester_aktif_dan_boleh_sama_di_semester_lain(): void
    {
        $this->actingAs($this->admin);
        $ganjil = Akademik::semesterAktif();
        $genap = $ganjil->tahunAjaran->semester->firstWhere('jenis', 'Genap');

        $this->postJson('/admin/nilai', ['kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'jenis' => 'uh', 'items' => [['siswa_id' => $this->siswa->id, 'nilai' => 80]]])->assertOk();
        $this->assertSame($ganjil->id, Nilai::firstOrFail()->semester_id);

        $this->nilai($genap, 'uh', 90); // siswa, mapel, jenis sama di semester lain: sah
        $this->assertSame(2, Nilai::count());
        $this->getJson('/admin/nilai/siswa?kelas_id='.$this->kelas->id.'&mapel_id='.$this->mapel->id.'&jenis=uh')->assertJsonPath('siswa.0.nilai', 80);
    }

    public function test_kkm_per_semester_dipakai_di_rapor(): void
    {
        $this->actingAs($this->admin);
        $s = Akademik::semesterAktif();
        foreach (['tugas', 'uh', 'uts', 'uas'] as $j) {
            $this->nilai($s, $j, 72);
        }

        $this->get('/admin/rapor/'.$this->siswa->id)->assertOk()->assertSee('Tuntas')->assertDontSee('Belum tuntas');

        $this->putJson("/admin/semester/{$s->id}", ['mulai' => '2026-07-01', 'selesai' => '2026-12-31', 'kkm' => 80])->assertOk();

        $this->get('/admin/rapor/'.$this->siswa->id)->assertOk()->assertSee('Belum tuntas')->assertSee('KKM');
        $this->get('/admin/rapor/'.$this->siswa->id.'?tahun_ajaran=2026/2027&semester=Genap')->assertOk()->assertSee('Belum ada nilai');
        $this->get('/admin/rapor/'.$this->siswa->id.'?tahun_ajaran=2020/2021&semester=Ganjil')->assertOk()->assertSee('Belum ada nilai');
        $this->get('/admin/rapor/'.$this->siswa->id.'?tahun_ajaran=salah')->assertNotFound();
    }

    public function test_validasi_ubah_semester(): void
    {
        $this->actingAs($this->admin);
        $s = Akademik::semesterAktif();

        $this->putJson("/admin/semester/{$s->id}", ['mulai' => '2026-12-31', 'selesai' => '2026-07-01', 'kkm' => 70])->assertStatus(422)->assertJsonValidationErrors('selesai');
        $this->putJson("/admin/semester/{$s->id}", ['mulai' => '2026-07-01', 'selesai' => '2026-12-31', 'kkm' => 101])->assertStatus(422)->assertJsonValidationErrors('kkm');
    }

    public function test_hapus_tahun_ajaran_hanya_bila_tidak_aktif_dan_tidak_dipakai(): void
    {
        $this->actingAs($this->admin);
        $aktif = Akademik::semesterAktif()->tahunAjaran;
        $this->postJson('/admin/tahun-ajaran', ['nama' => '2027/2028']);
        $this->postJson('/admin/tahun-ajaran', ['nama' => '2028/2029']);
        $dipakai = TahunAjaran::where('nama', '2027/2028')->first();
        $kosong = TahunAjaran::where('nama', '2028/2029')->first();
        $this->nilai($dipakai->semester[0], 'uh', 80);

        $this->deleteJson("/admin/tahun-ajaran/{$aktif->id}")->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'aktif'));
        $this->deleteJson("/admin/tahun-ajaran/{$dipakai->id}")->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'dipakai'));
        $this->deleteJson("/admin/tahun-ajaran/{$kosong->id}")->assertOk();

        $this->assertDatabaseMissing('tahun_ajaran', ['id' => $kosong->id]);
        $this->assertSame(0, Semester::where('tahun_ajaran_id', $kosong->id)->count());
        $this->assertDatabaseHas('tahun_ajaran', ['id' => $dipakai->id]);
    }

    public function test_bobot_nilai_dari_tabel_menentukan_nilai_akhir(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/tahun-ajaran');
        $this->assertSame(['tugas' => 20, 'uh' => 20, 'uts' => 25, 'uas' => 35], array_map(fn ($b) => $b['bobot'], BobotNilai::peta()));
        $this->assertSame(85.0, Nilai::nilaiAkhir(['uh' => 85]));
        $this->assertSame(81.25, Nilai::nilaiAkhir(['tugas' => 80, 'uh' => 90, 'uts' => 70, 'uas' => 85]));

        $this->putJson('/admin/tahun-ajaran/bobot', ['bobot' => ['tugas' => 10, 'uh' => 10, 'uts' => 30, 'uas' => 50]])->assertOk();
        $this->assertSame(80.5, Nilai::nilaiAkhir(['tugas' => 80, 'uh' => 90, 'uts' => 70, 'uas' => 85]));

        $this->putJson('/admin/tahun-ajaran/bobot', ['bobot' => ['tugas' => 10, 'uh' => 10, 'uts' => 30, 'uas' => 30]])->assertStatus(422)->assertJsonValidationErrors('bobot.tugas');
        $this->putJson('/admin/tahun-ajaran/bobot', ['bobot' => ['tugas' => 10]])->assertStatus(422);
    }

    public function test_absensi_menempel_ke_semester_menurut_tanggal_dan_rekap_rapor_mengikutinya(): void
    {
        $this->actingAs($this->admin);
        $ganjil = Akademik::semesterAktif();
        $genap = $ganjil->tahunAjaran->semester->firstWhere('jenis', 'Genap');
        $buat = fn (string $tgl, string $status) => Absensi::create(['siswa_id' => $this->siswa->id, 'kelas_id' => $this->kelas->id, 'tanggal' => $tgl, 'status' => $status]);

        $a = $buat('2026-09-01', 'hadir');
        $b = $buat('2027-02-01', 'sakit');
        $luar = $buat('2025-01-01', 'alpa');

        $this->assertSame([$ganjil->id, $genap->id, null], [$a->semester_id, $b->semester_id, $luar->semester_id]);

        $this->get('/admin/rapor/'.$this->siswa->id.'?tahun_ajaran=2026/2027&semester=Genap')->assertOk();
        $this->assertSame(1, \App\Support\Rapor::kehadiran($this->siswa, $genap)['sakit']);
        $this->assertSame(0, \App\Support\Rapor::kehadiran($this->siswa, $ganjil)['sakit']);
    }

    public function test_data_tiap_sekolah_terpisah(): void
    {
        Akademik::semesterAktif();
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMA', 'status' => 'aktif', 'tanpa_batas' => true]);

        SekolahAktif::jalankan($lain, function () {
            $this->assertSame(0, TahunAjaran::count());
            $this->assertNull(Akademik::aktif()['semester_id']);
            TahunAjaran::buat('2026/2027'); // nama sama di sekolah lain tidak bentrok
            $this->assertSame(1, TahunAjaran::count());
            $this->assertSame(['tugas', 'uh', 'uts', 'uas'], array_keys(BobotNilai::peta()));
        });

        $this->assertSame(1, TahunAjaran::count());
        $this->assertSame(2, Semester::count());
        $this->assertNull(Semester::withoutGlobalScopes()->where('sekolah_id', $lain->id)->where('aktif', true)->first());
    }
}
