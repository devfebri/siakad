<?php

namespace Tests\Feature;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\Sekolah;
use App\Semester;
use App\Siswa;
use App\Support\Akademik;
use App\Support\SekolahAktif;
use App\TahunAjaran;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Rombel (kelas per tahun ajaran), keanggotaan per tahun ajaran, dan wali kelas. */
class RombelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private TahunAjaran $ta1;
    private TahunAjaran $ta2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->ta1 = Akademik::semesterAktif()->tahunAjaran;
        $this->ta2 = TahunAjaran::buat('2099/2100');
    }

    private function guru(string $nama): Guru
    {
        $mapel = Mapel::firstOrCreate(['mapel_nama' => 'Umum']);

        return Guru::create(['nip_guru' => 'n'.$nama, 'nama_guru' => $nama, 'jenis_kelamin' => 'Laki-laki', 'no_hp' => '0812', 'tanggal_lahir' => '1980-01-01', 'mapel_id' => $mapel->id, 'user_id' => User::factory()->create(['role' => 'guru'])->id]);
    }

    private function siswa(string $nisn, ?Kelas $kelas = null): Siswa
    {
        return Siswa::create(['nisn' => $nisn, 'nama_siswa' => "Siswa $nisn", 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2009-01-01', 'kelas_id' => $kelas?->id, 'user_id' => User::factory()->create(['role' => 'siswa'])->id]);
    }

    public function test_rombel_baru_masuk_tahun_ajaran_aktif_dan_nama_unik_per_tahun_ajaran(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A', 'tingkat' => 'X'])->assertCreated();
        $this->assertSame($this->ta1->id, Kelas::firstOrFail()->tahun_ajaran_id);

        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A'])->assertStatus(422)->assertJsonValidationErrors('nama_kelas');
        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A', 'tahun_ajaran_id' => $this->ta2->id])->assertCreated(); // nama sama di tahun ajaran lain sah
        $this->assertSame(2, Kelas::count());
    }

    public function test_daftar_rombel_disaring_per_tahun_ajaran(): void
    {
        $this->actingAs($this->admin);
        Kelas::create(['nama_kelas' => 'A-sekarang']);
        Kelas::create(['nama_kelas' => 'B-depan', 'tahun_ajaran_id' => $this->ta2->id]);
        $dt = 'draw=1&start=0&length=10';

        $this->getJson("/admin/kelas/data?$dt")->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.nama_kelas', 'A-sekarang');
        $this->getJson("/admin/kelas/data?$dt&tahun_ajaran_id={$this->ta2->id}")->assertJsonPath('data.0.nama_kelas', 'B-depan')->assertJsonPath('data.0.tahun_ajaran', '2099/2100');
        $this->get('/admin/kelas')->assertOk()->assertSee('TA 2099/2100');
    }

    public function test_wali_kelas_mendapat_peran_dan_hanya_satu_rombel_per_tahun_ajaran(): void
    {
        $this->actingAs($this->admin);
        $budi = $this->guru('Budi');
        $sari = $this->guru('Sari');

        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A', 'wali_guru_id' => $budi->id])->assertCreated();
        $this->assertTrue($budi->user->fresh()->punyaPeran('wali_kelas'));
        $this->assertTrue($budi->user->fresh()->punyaPeran('guru'), 'peran guru tetap ada');
        $this->assertFalse($sari->user->fresh()->punyaPeran('wali_kelas'));

        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-B', 'wali_guru_id' => $budi->id])->assertStatus(422)->assertJsonValidationErrors('wali_guru_id');
        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-B', 'wali_guru_id' => $budi->id, 'tahun_ajaran_id' => $this->ta2->id])->assertCreated(); // tahun ajaran lain

        $a = Kelas::where('nama_kelas', 'X-A')->first();
        $this->putJson("/admin/kelas/{$a->id}", ['nama_kelas' => 'X-A', 'wali_guru_id' => $sari->id])->assertOk();
        $this->assertTrue($sari->user->fresh()->punyaPeran('wali_kelas'));
        $this->assertTrue($budi->user->fresh()->punyaPeran('wali_kelas'), 'masih wali di rombel tahun ajaran berikutnya');

        Kelas::where('nama_kelas', 'X-B')->first()->delete();
        $this->putJson("/admin/kelas/{$a->id}", ['nama_kelas' => 'X-A', 'wali_guru_id' => null])->assertOk();
        $this->assertFalse($sari->user->fresh()->punyaPeran('wali_kelas'));
        $this->assertSame('X-A', $a->fresh()->nama_kelas);
    }

    public function test_siswa_tercatat_di_rombel_siswa_dan_pindah_kelas_menggantikan(): void
    {
        $a = Kelas::create(['nama_kelas' => 'X-A']);
        $b = Kelas::create(['nama_kelas' => 'X-B']);
        $s = $this->siswa('1001', $a);

        $this->assertSame([$a->id], DB::table('rombel_siswa')->where('siswa_id', $s->id)->pluck('rombel_id')->all());
        $s->update(['kelas_id' => $b->id]);
        $this->assertSame([$b->id], DB::table('rombel_siswa')->where('siswa_id', $s->id)->pluck('rombel_id')->all());
        $this->assertSame([0, 1], [$a->anggota()->count(), $b->anggota()->count()]);

        $s->update(['kelas_id' => null]);
        $this->assertSame(0, DB::table('rombel_siswa')->where('siswa_id', $s->id)->count());
    }

    public function test_tambah_dan_keluarkan_anggota_dari_halaman_rombel(): void
    {
        $this->actingAs($this->admin);
        $a = Kelas::create(['nama_kelas' => 'X-A']);
        $b = Kelas::create(['nama_kelas' => 'X-B']);
        $s1 = $this->siswa('1001', $b);
        $s2 = $this->siswa('1002');

        $this->get("/admin/kelas/open/{$a->id}")->assertOk()->assertSee('Siswa 1002')->assertDontSee('Siswa 1001');
        $this->postJson("/admin/kelas/{$a->id}/anggota", ['siswa' => [$s1->id, $s2->id]])->assertOk();
        $this->assertSame([$a->id, $a->id], [$s1->fresh()->kelas_id, $s2->fresh()->kelas_id], 'dipindah dari X-B');
        $this->assertSame(0, $b->anggota()->count());
        $this->get("/admin/kelas/open/{$a->id}")->assertOk()->assertSee('Siswa 1001')->assertSee('Siswa 1002');

        $this->deleteJson("/admin/kelas/{$a->id}/anggota/{$s2->id}")->assertOk();
        $this->assertNull($s2->fresh()->kelas_id);
        $this->assertSame(1, $a->anggota()->count());
        $this->postJson("/admin/kelas/{$a->id}/anggota", ['siswa' => []])->assertStatus(422);
    }

    public function test_ganti_tahun_ajaran_aktif_membuat_siswa_mengikuti_rombel_baru_dan_riwayat_tetap(): void
    {
        $this->actingAs($this->admin);
        $lama = Kelas::create(['nama_kelas' => 'VII-A']);
        $baru = Kelas::create(['nama_kelas' => 'VIII-A', 'tahun_ajaran_id' => $this->ta2->id]);
        $naik = $this->siswa('1001', $lama);
        $tinggal = $this->siswa('1002', $lama);
        $baru->tempatkan($naik); // ditempatkan di rombel tahun ajaran depan (belum aktif)

        $this->assertSame($lama->id, $naik->fresh()->kelas_id, 'kelas aktif tidak berubah sebelum tahun ajaran diaktifkan');

        $this->postJson('/admin/semester/'.$this->ta2->semester[0]->id.'/aktifkan')->assertOk();

        $this->assertSame($baru->id, $naik->fresh()->kelas_id);
        $this->assertNull($tinggal->fresh()->kelas_id, 'belum ditempatkan di tahun ajaran baru');
        $this->assertSame(1, $lama->anggota()->count() - 1, 'riwayat rombel lama tetap lengkap (2 anggota)');
        $this->getJson('/admin/jadwal/grid?kelas_id='.$baru->id)->assertOk();
        $this->get('/admin/jadwal')->assertOk()->assertSee('VIII-A')->assertDontSee('VII-A');

        $this->postJson('/admin/semester/'.$this->ta1->semester[0]->id.'/aktifkan')->assertOk();
        $this->assertSame([$lama->id, $lama->id], [$naik->fresh()->kelas_id, $tinggal->fresh()->kelas_id]);
    }

    public function test_hapus_rombel_ditolak_bila_ada_anggota_walau_tahun_ajaran_lampau(): void
    {
        $this->actingAs($this->admin);
        $k = Kelas::create(['nama_kelas' => 'Lama', 'tahun_ajaran_id' => $this->ta2->id]);
        $k->tempatkan($this->siswa('1001'));

        $this->deleteJson("/admin/kelas/{$k->id}")->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, '1 siswa'));
        $k->keluarkan(Siswa::first());
        $this->deleteJson("/admin/kelas/{$k->id}")->assertOk();
    }

    public function test_salin_rombel_ke_tahun_ajaran_lain(): void
    {
        $this->actingAs($this->admin);
        $budi = $this->guru('Budi');
        Kelas::create(['nama_kelas' => 'X-A', 'tingkat' => 'X', 'wali_guru_id' => $budi->id]);
        Kelas::create(['nama_kelas' => 'X-B', 'tingkat' => 'X']);
        Kelas::create(['nama_kelas' => 'X-A', 'tahun_ajaran_id' => $this->ta2->id]); // sudah ada: dilewati

        $this->postJson('/admin/kelas/salin', ['dari' => $this->ta1->id, 'ke' => $this->ta2->id])->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, '1 rombel'));

        $baru = Kelas::where('tahun_ajaran_id', $this->ta2->id)->orderBy('nama_kelas')->get();
        $this->assertSame(['X-A', 'X-B'], $baru->pluck('nama_kelas')->all());
        $this->assertNull($baru[1]->wali_guru_id);
        $this->postJson('/admin/kelas/salin', ['dari' => $this->ta1->id, 'ke' => $this->ta1->id])->assertStatus(422);
    }

    public function test_guru_dan_siswa_hanya_melihat_dan_data_sekolah_lain_ditolak(): void
    {
        $k = Kelas::create(['nama_kelas' => 'X-A']);
        $s = $this->siswa('1001', $k);
        $guru = $this->guru('Budi');

        $this->actingAs($guru->user)->get('/guru/kelas')->assertOk();
        $this->actingAs($guru->user)->getJson('/guru/kelas/data?draw=1&start=0&length=10')->assertJsonPath('data.0.nama_kelas', 'X-A');
        $this->actingAs($guru->user)->get("/guru/kelas/open/{$k->id}")->assertOk()->assertSee('Siswa 1001')->assertDontSee('Keluarkan dari rombel');
        $this->actingAs($guru->user)->postJson('/admin/kelas', ['nama_kelas' => 'Z'])->assertForbidden();
        $this->actingAs($s->user)->get('/siswa/kelas')->assertOk()->assertSee('X-A');

        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMA', 'status' => 'aktif', 'tanpa_batas' => true]);
        $asing = SekolahAktif::jalankan($lain, fn () => Siswa::create(['nisn' => '9', 'nama_siswa' => 'Asing', 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2009-01-01', 'user_id' => User::factory()->create(['role' => 'siswa'])->id]));
        $this->actingAs($this->admin)->postJson("/admin/kelas/{$k->id}/anggota", ['siswa' => [$asing->id]])->assertStatus(422);
        $this->assertSame(1, $k->anggota()->count());
    }
}
