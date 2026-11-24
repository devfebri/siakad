<?php

namespace Tests\Feature;

use App\Absensi;
use App\Alumni;
use App\Guru;
use App\Jadwal;
use App\Kelas;
use App\Kenaikan;
use App\LogAktivitas;
use App\Mapel;
use App\OrangTua;
use App\Sekolah;
use App\Siswa;
use App\Support\Akademik;
use App\Support\ProsesTahunan;
use App\Support\SekolahAktif;
use App\Support\Tingkat;
use App\TahunAjaran;
use App\UsulanKenaikan;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Proses tahunan: tahun ajaran baru (draf), usulan, kenaikan, kelulusan, aktivasi, dan tahun ajaran ditutup. */
class ProsesTahunanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private TahunAjaran $lama;
    private Kelas $x;
    private Kelas $xi;
    private Kelas $xii;
    private Mapel $mapel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->lama = Akademik::semesterAktif()->tahunAjaran;
        $this->x = Kelas::create(['nama_kelas' => 'X A']);
        $this->xi = Kelas::create(['nama_kelas' => 'XI A']);
        $this->xii = Kelas::create(['nama_kelas' => 'XII A']);
        $this->mapel = Mapel::create(['mapel_nama' => 'Matematika']);
    }

    private function siswa(string $nisn, Kelas $kelas): Siswa
    {
        return Siswa::create(['nisn' => $nisn, 'nama_siswa' => "Siswa $nisn", 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2009-01-01', 'kelas_id' => $kelas->id, 'user_id' => User::factory()->create(['role' => 'siswa'])->id]);
    }

    private function guru(string $nama): Guru
    {
        return Guru::create(['nip_guru' => 'n'.$nama, 'nama_guru' => $nama, 'jenis_kelamin' => 'Laki-laki', 'no_hp' => '0812', 'tanggal_lahir' => '1980-01-01', 'mapel_id' => $this->mapel->id, 'user_id' => User::factory()->create(['role' => 'guru'])->id]);
    }

    private function bukaTahunBaru(array $extra = []): TahunAjaran
    {
        $this->actingAs($this->admin)->postJson('/admin/proses-tahunan/buka', ['nama' => '2027/2028'] + $extra)->assertCreated();

        return TahunAjaran::where('nama', '2027/2028')->firstOrFail();
    }

    private function rombelBaru(TahunAjaran $ta, string $nama): Kelas
    {
        return Kelas::where('tahun_ajaran_id', $ta->id)->where('nama_kelas', $nama)->firstOrFail();
    }

    // ------------------------------------------------------------ helper tingkat

    public function test_tingkat_dari_kolom_atau_nama_rombel(): void
    {
        foreach ([['VII A', 7, 'A'], ['7B', 7, 'B'], ['X IPA 1', 10, 'IPA 1'], ['XI-2', 11, '2'], ['IX B', 9, 'B'], ['XII', 12, '']] as [$nama, $angka, $paralel]) {
            $this->assertSame($angka, Tingkat::angka(null, $nama), $nama);
            $this->assertSame($paralel, Tingkat::paralel($nama), $nama);
        }
        $this->assertSame(8, Tingkat::angka('VIII', 'Kelas bebas'));
        $this->assertNull(Tingkat::angka(null, 'Kelas Pelangi'));
        $this->assertSame(12, Tingkat::akhir(), 'jenjang SMA');
        $this->assertSame(9, Tingkat::akhir(new Sekolah(['jenjang' => 'SMP'])));
        $this->assertSame(6, Tingkat::akhir(new Sekolah(['jenjang' => 'MI'])));
    }

    // ------------------------------------------------------------ buka tahun ajaran baru

    public function test_buka_tahun_baru_menyalin_rombel_wali_dan_jadwal_sebagai_draf(): void
    {
        $budi = $this->guru('Budi');
        $this->x->update(['wali_guru_id' => $budi->id]);
        Jadwal::create(['kelas_id' => $this->x->id, 'mapel_id' => $this->mapel->id, 'guru_id' => $budi->id, 'hari' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00', 'semester_id' => Akademik::semesterAktif()->id]);

        $baru = $this->bukaTahunBaru(['salin_wali' => 1, 'salin_jadwal' => 1]);

        $this->assertSame(['draf', $this->lama->id], [$baru->status, $baru->dari_tahun_id]);
        $this->assertSame('aktif', $this->lama->fresh()->status);
        $this->assertEqualsCanonicalizing(['X A', 'XI A', 'XII A'], Kelas::where('tahun_ajaran_id', $baru->id)->pluck('nama_kelas')->all());
        $this->assertSame($budi->id, $this->rombelBaru($baru, 'X A')->wali_guru_id);
        $this->assertSame(1, Jadwal::whereIn('semester_id', $baru->semester->pluck('id'))->count());
        $this->assertSame(0, DB::table('rombel_siswa')->where('tahun_ajaran_id', $baru->id)->count(), 'tanpa anggota');
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'tahun.buka', 'sekolah_id' => $this->sekolah->id]);

        $this->actingAs($this->admin)->get('/admin/proses-tahunan')->assertOk()->assertSee('2026/2027 → 2027/2028')->assertSee('Rombel X A');
    }

    public function test_buka_tahun_baru_divalidasi_dan_hanya_satu_draf(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/admin/proses-tahunan/buka', ['nama' => '27-28'])->assertStatus(422)->assertJsonValidationErrors('nama');
        $this->postJson('/admin/proses-tahunan/buka', ['nama' => '2027/2029'])->assertStatus(422);
        $this->postJson('/admin/proses-tahunan/buka', ['nama' => '2026/2027'])->assertStatus(422); // sudah ada
        $this->postJson('/admin/proses-tahunan/buka', ['nama' => '2025/2026'])->assertStatus(422); // mundur

        $this->postJson('/admin/proses-tahunan/buka', ['nama' => '2027/2028'])->assertCreated();
        $this->postJson('/admin/proses-tahunan/buka', ['nama' => '2028/2029'])->assertStatus(422)->assertJsonValidationErrors('nama');
    }

    // ------------------------------------------------------------ usulan wali kelas

    public function test_wali_kelas_mengusulkan_sesuai_tingkat_dan_hanya_untuk_rombelnya(): void
    {
        $budi = $this->guru('Budi');
        $sari = $this->guru('Sari');
        $this->x->update(['wali_guru_id' => $budi->id]);
        $this->xii->update(['wali_guru_id' => $sari->id]);
        Kelas::sinkronkanPeranWali();
        $a = $this->siswa('1', $this->x);
        $b = $this->siswa('2', $this->x);
        $lulusan = $this->siswa('3', $this->xii);

        $this->actingAs($budi->user->fresh());
        $this->get('/guru/usulan')->assertOk()->assertSee('Rombel X A')->assertSee('Siswa 1')->assertDontSee('Siswa 3');
        $this->putJson("/guru/usulan/{$this->x->id}", ['usulan' => [$a->id => 'naik', $b->id => 'tinggal'], 'catatan' => [$b->id => 'Nilai kurang']])->assertOk();
        $this->assertSame(['naik', 'tinggal', 'Nilai kurang'], [UsulanKenaikan::where('siswa_id', $a->id)->value('usulan'), UsulanKenaikan::where('siswa_id', $b->id)->value('usulan'), UsulanKenaikan::where('siswa_id', $b->id)->value('catatan')]);

        // mengubah dan mengosongkan usulan
        $this->putJson("/guru/usulan/{$this->x->id}", ['usulan' => [$a->id => '', $b->id => 'naik']])->assertOk();
        $this->assertSame(1, UsulanKenaikan::count());

        $this->putJson("/guru/usulan/{$this->x->id}", ['usulan' => [$a->id => 'lulus']])->assertStatus(422); // bukan tingkat akhir
        $this->putJson("/guru/usulan/{$this->xii->id}", ['usulan' => [$lulusan->id => 'lulus']])->assertForbidden(); // bukan wali rombel itu
        $this->actingAs($sari->user->fresh())->putJson("/guru/usulan/{$this->xii->id}", ['usulan' => [$lulusan->id => 'lulus', $a->id => 'lulus']])->assertOk();
        $this->assertSame(['lulus'], UsulanKenaikan::where('rombel_id', $this->xii->id)->pluck('usulan')->all(), 'siswa rombel lain diabaikan');

        $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/guru/usulan')->assertRedirect(route('guru/dashboard'));
    }

    // ------------------------------------------------------------ kenaikan

    public function test_proses_rombel_menurut_usulan_menempatkan_siswa_di_rombel_tahun_baru(): void
    {
        $naik = $this->siswa('1', $this->x);
        $tinggal = $this->siswa('2', $this->x);
        $tanpa = $this->siswa('3', $this->x);
        UsulanKenaikan::create(['siswa_id' => $naik->id, 'tahun_ajaran_id' => $this->lama->id, 'rombel_id' => $this->x->id, 'usulan' => 'naik']);
        UsulanKenaikan::create(['siswa_id' => $tinggal->id, 'tahun_ajaran_id' => $this->lama->id, 'rombel_id' => $this->x->id, 'usulan' => 'tinggal']);
        $baru = $this->bukaTahunBaru();

        $res = $this->postJson("/admin/proses-tahunan/rombel/{$this->x->id}", [])->assertOk();
        $this->assertStringContainsString('2 siswa diproses, 1 dilewati', $res->json('message'));

        $this->assertSame($this->rombelBaru($baru, 'XI A')->id, Kenaikan::where('siswa_id', $naik->id)->value('rombel_tujuan_id'), 'naik ke tingkat berikutnya dengan paralel sama');
        $this->assertSame($this->rombelBaru($baru, 'X A')->id, Kenaikan::where('siswa_id', $tinggal->id)->value('rombel_tujuan_id'));
        $this->assertDatabaseHas('rombel_siswa', ['siswa_id' => $naik->id, 'tahun_ajaran_id' => $baru->id, 'rombel_id' => $this->rombelBaru($baru, 'XI A')->id]);
        $this->assertSame($this->x->id, $naik->fresh()->kelas_id, 'rombel aktif tidak berubah sebelum aktivasi');
        $this->assertSame(0, Kenaikan::where('siswa_id', $tanpa->id)->count());

        // opsi: siswa tanpa usulan dianggap naik
        $this->postJson("/admin/proses-tahunan/rombel/{$this->x->id}", ['tanpa_usulan' => 1])->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, '1 siswa diproses'));
        $this->assertSame('naik', Kenaikan::where('siswa_id', $tanpa->id)->value('hasil'));
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'kenaikan.proses']);
    }

    public function test_proses_per_siswa_mengubah_hasil_dan_menolak_tujuan_yang_salah(): void
    {
        $s = $this->siswa('1', $this->x);
        $lulusan = $this->siswa('2', $this->xii);
        $baru = $this->bukaTahunBaru();
        $this->actingAs($this->admin);

        $this->postJson("/admin/proses-tahunan/siswa/{$s->id}", ['hasil' => 'naik'])->assertOk();
        $this->assertSame('naik', Kenaikan::where('siswa_id', $s->id)->value('hasil'));

        // ubah hasil menjadi tinggal: rombel lama di tahun baru diganti, tidak dobel
        $this->postJson("/admin/proses-tahunan/siswa/{$s->id}", ['hasil' => 'tinggal'])->assertOk();
        $this->assertSame(1, Kenaikan::where('siswa_id', $s->id)->count());
        $this->assertSame([$this->rombelBaru($baru, 'X A')->id], DB::table('rombel_siswa')->where('siswa_id', $s->id)->where('tahun_ajaran_id', $baru->id)->pluck('rombel_id')->all());

        // tujuan salah tingkat, kelulusan di tingkat bukan akhir, naik dari tingkat akhir
        $this->postJson("/admin/proses-tahunan/siswa/{$s->id}", ['hasil' => 'naik', 'tujuan_id' => $this->rombelBaru($baru, 'XII A')->id])->assertStatus(422)->assertJsonValidationErrors('siswa');
        $this->postJson("/admin/proses-tahunan/siswa/{$s->id}", ['hasil' => 'lulus'])->assertStatus(422);
        $this->postJson("/admin/proses-tahunan/siswa/{$lulusan->id}", ['hasil' => 'naik'])->assertStatus(422);
        $this->postJson("/admin/proses-tahunan/siswa/{$lulusan->id}", ['hasil' => 'lulus', 'no_ijazah' => 'DN-123'])->assertOk();
        $this->assertSame('DN-123', Kenaikan::where('siswa_id', $lulusan->id)->value('no_ijazah'));

        // batalkan satu siswa
        $this->deleteJson("/admin/proses-tahunan/siswa/{$s->id}")->assertOk();
        $this->assertSame(0, Kenaikan::where('siswa_id', $s->id)->count());
        $this->assertSame(0, DB::table('rombel_siswa')->where('siswa_id', $s->id)->where('tahun_ajaran_id', $baru->id)->count());
    }

    public function test_batal_semua_dan_batal_tahun_draf(): void
    {
        $a = $this->siswa('1', $this->x);
        $b = $this->siswa('2', $this->xi);
        $baru = $this->bukaTahunBaru();
        $this->postJson("/admin/proses-tahunan/siswa/{$a->id}", ['hasil' => 'naik'])->assertOk();
        $this->postJson("/admin/proses-tahunan/siswa/{$b->id}", ['hasil' => 'tinggal'])->assertOk();

        $this->deleteJson('/admin/proses-tahunan/semua')->assertOk()->assertJsonPath('message', fn ($m) => str_starts_with($m, '2 hasil'));
        $this->assertSame(0, Kenaikan::count());
        $this->assertSame(0, DB::table('rombel_siswa')->where('tahun_ajaran_id', $baru->id)->count());

        $this->deleteJson('/admin/proses-tahunan/tahun')->assertOk();
        $this->assertDatabaseMissing('tahun_ajaran', ['id' => $baru->id]);
        $this->assertSame(0, Kelas::where('tahun_ajaran_id', $baru->id)->count());
        $this->assertSame('aktif', $this->lama->fresh()->status);
        $this->assertSame($this->x->id, $a->fresh()->kelas_id, 'data tahun aktif tidak tersentuh');
    }

    // ------------------------------------------------------------ aktivasi dan kelulusan

    public function test_aktivasi_ditolak_selama_ada_siswa_belum_diproses_dengan_daftarnya(): void
    {
        $a = $this->siswa('1', $this->x);
        $b = $this->siswa('2', $this->xi);
        $baru = $this->bukaTahunBaru();
        $this->postJson("/admin/proses-tahunan/siswa/{$a->id}", ['hasil' => 'naik'])->assertOk();

        $res = $this->postJson('/admin/proses-tahunan/aktifkan')->assertStatus(409);
        $this->assertStringContainsString('1 siswa belum diproses', $res->json('message'));
        $this->assertSame([['id' => $b->id, 'nama' => 'Siswa 2', 'rombel' => 'XI A']], $res->json('belum_diproses'));
        $this->assertSame('draf', $baru->fresh()->status);

        // juga lewat menu Tahun Ajaran
        $this->postJson('/admin/semester/'.$baru->semester[0]->id.'/aktifkan')->assertStatus(409)->assertJsonCount(1, 'belum_diproses');
        $this->assertSame('aktif', $this->lama->fresh()->status);
        $this->get('/admin/proses-tahunan')->assertOk()->assertSee('1 siswa belum diproses');
    }

    public function test_aktivasi_menerapkan_kenaikan_menutup_tahun_lama_dan_meluluskan(): void
    {
        $naik = $this->siswa('1', $this->x);
        $tinggal = $this->siswa('2', $this->xi);
        $lulus = $this->siswa('3', $this->xii);
        $tidakLulus = $this->siswa('4', $this->xii);
        $ortuLulus = OrangTua::create(['user_id' => User::factory()->create(['role' => 'orang_tua'])->id, 'nama' => 'Ortu Lulus']);
        $ortuLulus->hubungkan($lulus, 'ibu');
        $ortuCampur = OrangTua::create(['user_id' => User::factory()->create(['role' => 'orang_tua'])->id, 'nama' => 'Ortu Campur']);
        $ortuCampur->hubungkan($tidakLulus, 'ayah');
        $ortuCampur->hubungkan($naik, 'ayah');

        $baru = $this->bukaTahunBaru();
        $this->postJson("/admin/proses-tahunan/siswa/{$naik->id}", ['hasil' => 'naik'])->assertOk();
        $this->postJson("/admin/proses-tahunan/siswa/{$tinggal->id}", ['hasil' => 'tinggal'])->assertOk();
        $this->postJson("/admin/proses-tahunan/siswa/{$lulus->id}", ['hasil' => 'lulus', 'no_ijazah' => 'DN-001'])->assertOk();
        $this->postJson("/admin/proses-tahunan/siswa/{$tidakLulus->id}", ['hasil' => 'tidak_lulus'])->assertOk();

        $this->assertSame(0, Alumni::count(), 'alumni baru dibuat saat aktivasi');
        $this->postJson('/admin/proses-tahunan/aktifkan')->assertOk();

        // tahun ajaran
        $this->assertSame(['ditutup', 'aktif'], [$this->lama->fresh()->status, $baru->fresh()->status]);
        $this->assertTrue($baru->semester()->where('jenis', 'Ganjil')->first()->aktif);
        // kenaikan
        $this->assertSame([$this->rombelBaru($baru, 'XI A')->id, $this->rombelBaru($baru, 'XI A')->id], [$naik->fresh()->kelas_id, $tinggal->fresh()->kelas_id]);
        // kelulusan
        $alumni = Alumni::with('siswa')->orderBy('siswa_id')->get();
        $this->assertSame([[2027, 'lulus', 'DN-001'], [2027, 'tidak_lulus', null]], $alumni->map(fn ($a) => [$a->angkatan, $a->status, $a->no_ijazah])->all());
        $this->assertSame(['lulus', null, 'tidak_lulus'], [$lulus->fresh()->status, $lulus->fresh()->kelas_id, $tidakLulus->fresh()->status]);
        $this->assertFalse($lulus->user->fresh()->aktif);
        $this->assertTrue($naik->user->fresh()->aktif);
        // akun orang tua: nonaktif hanya bila tak punya anak aktif lain
        $this->assertFalse($ortuLulus->user->fresh()->aktif);
        $this->assertFalse($ortuCampur->fresh()->anak()->where('siswa.status', 'aktif')->doesntExist());
        $this->assertTrue($ortuCampur->user->fresh()->aktif);

        foreach (['tahun.aktifkan', 'kelulusan', 'kelulusan.ortu'] as $aksi) {
            $this->assertDatabaseHas('log_aktivitas', ['aksi' => $aksi, 'sekolah_id' => $this->sekolah->id]);
        }

        // proses tidak dapat dibatalkan lagi
        $this->deleteJson("/admin/proses-tahunan/siswa/{$naik->id}")->assertStatus(409);
        $this->get('/admin/siswa')->assertOk();
        $this->getJson('/admin/siswa/data?draw=1&start=0&length=10')->assertJsonPath('recordsTotal', 2)->assertJsonMissing(['nisn' => '3']);
    }

    public function test_akun_lulusan_tidak_bisa_login_dan_sesi_aktif_diakhiri(): void
    {
        $lulus = $this->siswa('3', $this->xii);
        $lulus->user->update(['username' => 'lulusan', 'password' => bcrypt('rahasia')]);
        $this->bukaTahunBaru();
        $this->postJson("/admin/proses-tahunan/siswa/{$lulus->id}", ['hasil' => 'lulus'])->assertOk();
        $this->postJson('/admin/proses-tahunan/aktifkan')->assertOk();

        auth()->logout();
        $this->post('/login', ['username' => 'lulusan', 'password' => 'rahasia'])->assertSessionHasErrors('username');
        $this->assertGuest();

        // sesi yang sudah berjalan juga diputus
        $this->actingAs($lulus->user->fresh());
        $this->get('/siswa/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ------------------------------------------------------------ tahun ajaran ditutup

    public function test_tahun_ajaran_ditutup_bersifat_baca_saja(): void
    {
        $s = $this->siswa('1', $this->x);
        $semesterLama = Akademik::semesterAktif();
        $jadwal = Jadwal::create(['kelas_id' => $this->x->id, 'mapel_id' => $this->mapel->id, 'hari' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00', 'semester_id' => $semesterLama->id, 'ruang' => 'R1']);
        $this->bukaTahunBaru();
        $this->postJson("/admin/proses-tahunan/siswa/{$s->id}", ['hasil' => 'naik'])->assertOk();
        $this->postJson("/admin/proses-tahunan/siswa/{$this->siswa('2', $this->xii)->id}", ['hasil' => 'lulus'])->assertOk();
        $this->postJson("/admin/proses-tahunan/siswa/{$this->siswa('3', $this->xi)->id}", ['hasil' => 'naik'])->assertOk();
        $this->postJson('/admin/proses-tahunan/aktifkan')->assertOk();

        $this->putJson("/admin/jadwal/{$jadwal->id}", ['kelas_id' => $this->x->id, 'mapel_id' => $this->mapel->id, 'hari' => 2, 'jam_mulai' => '07:00', 'jam_selesai' => '08:00'])->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'ditutup'));
        $this->deleteJson("/admin/jadwal/{$jadwal->id}")->assertStatus(409);
        $this->assertNotNull(Jadwal::find($jadwal->id));

        $this->postJson('/admin/absensi', ['kelas_id' => $this->x->id, 'tanggal' => $semesterLama->mulai->toDateString(), 'items' => [['siswa_id' => $s->id, 'status' => 'hadir']]])->assertStatus(409);
        $this->assertSame(0, Absensi::count());

        $this->putJson("/admin/kelas/{$this->x->id}", ['nama_kelas' => 'X Z'])->assertStatus(409);
        $this->deleteJson("/admin/kelas/{$this->x->id}")->assertStatus(409);
        $this->postJson("/admin/kelas/{$this->x->id}/anggota", ['siswa' => [$s->id]])->assertStatus(409);
        $this->assertSame('X A', $this->x->fresh()->nama_kelas);

        $this->get('/admin/tahun-ajaran')->assertOk()->assertSee('Ditutup');
    }

    // ------------------------------------------------------------ alumni

    public function test_data_alumni_dengan_filter_pencarian_dan_ekspor(): void
    {
        $this->actingAs($this->admin);
        $a = $this->siswa('1', $this->xii);
        $b = $this->siswa('2', $this->xii);
        Alumni::create(['siswa_id' => $a->id, 'angkatan' => 2025, 'tanggal_lulus' => '2025-06-20', 'status' => 'lulus', 'no_ijazah' => 'A-1']);
        Alumni::create(['siswa_id' => $b->id, 'angkatan' => 2026, 'tanggal_lulus' => '2026-06-20', 'status' => 'tidak_lulus']);
        $dt = 'draw=1&start=0&length=10';

        $this->get('/admin/alumni')->assertOk()->assertSee('Angkatan 2025')->assertSee('Angkatan 2026');
        $this->getJson("/admin/alumni/data?$dt")->assertJsonPath('recordsTotal', 2);
        $this->getJson("/admin/alumni/data?$dt&angkatan=2025")->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.no_ijazah', 'A-1')->assertJsonPath('data.0.status', 'Lulus|success');
        $this->getJson("/admin/alumni/data?$dt&search[value]=Siswa 2")->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.angkatan', 2026);

        $res = $this->get('/admin/alumni/export?angkatan=2025')->assertOk();
        $this->assertStringContainsString('data-alumni.xlsx', $res->headers->get('content-disposition'));

        $kepsek = User::factory()->create(['role' => 'kepsek']);
        $this->actingAs($kepsek)->get('/kepsek/alumni')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'guru']))->getJson('/admin/alumni/data')->assertForbidden();
    }

    public function test_data_proses_tahunan_terpisah_antar_sekolah(): void
    {
        $this->siswa('1', $this->xii);
        $this->bukaTahunBaru();
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMA', 'status' => 'aktif', 'tanpa_batas' => true]);

        SekolahAktif::jalankan($lain, function () {
            $this->assertNull(ProsesTahunan::tahunBaru());
            $this->assertSame(0, Kenaikan::count());
            $this->assertSame(0, Alumni::count());
            $admin = User::factory()->create(['role' => 'admin']);
            $this->actingAs($admin)->get('/admin/proses-tahunan?sekolah=lain')->assertOk()->assertDontSee('2026/2027 → 2027/2028');
            $this->postJson('/admin/proses-tahunan/aktifkan?sekolah=lain')->assertStatus(409);
        });

        $this->assertSame('draf', TahunAjaran::where('nama', '2027/2028')->value('status'));
        $this->assertSame(0, LogAktivitas::where('sekolah_id', $lain->id)->count());
    }

    public function test_hanya_admin_yang_memproses_tahunan(): void
    {
        foreach (['guru', 'kepsek', 'siswa'] as $peran) {
            $this->actingAs(User::factory()->create(['role' => $peran]));
            $this->postJson('/admin/proses-tahunan/buka', ['nama' => '2027/2028'])->assertForbidden();
            $this->postJson('/admin/proses-tahunan/aktifkan')->assertForbidden();
        }
        $this->assertSame(0, TahunAjaran::where('status', 'draf')->count());
    }
}
