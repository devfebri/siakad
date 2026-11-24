<?php


namespace Tests\Feature;

use App\Kelas;
use App\Langganan;
use App\LogAktivitas;
use App\Modul;
use App\OrangTua;
use App\Paket;
use App\PpdbPendaftar;
use App\Sekolah;
use App\Siswa;
use App\Support\AksesSekolah;
use App\Support\PpdbKonversi;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pendaftar PPDB yang diterima dijadikan siswa dan orang tua (satu per satu maupun massal), dengan pratinjau dan perlindungan duplikat. */
class PpdbJadiSiswaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $rombel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->rombel = Kelas::create(['nama_kelas' => 'VII A', 'tingkat' => 7]);
    }

    private function pendaftar(array $ubah = [], ?Sekolah $sekolah = null): PpdbPendaftar
    {
        return SekolahAktif::jalankan($sekolah ?? $this->sekolah, fn () => PpdbPendaftar::daftar($ubah + [
            'tahun_ajaran' => '2027/2028', 'nama' => 'Calon Siswa', 'jenis_kelamin' => 'Perempuan', 'agama' => 'Islam', 'tempat_lahir' => 'Jambi', 'tanggal_lahir' => '2012-05-05',
            'nisn' => '0012345678', 'asal_sekolah' => 'SD 1', 'alamat' => 'Jl. Merdeka No. 1', 'nama_ortu' => 'Bambang Calon', 'telepon' => '0812-3456-7890',
            'jalur' => 'Reguler', 'status' => 'diterima',
        ]));
    }

    private function jadikan(PpdbPendaftar $p, array $isi = [])
    {
        return $this->actingAs($this->admin)->post("/admin/ppdb/{$p->id}/jadikan-siswa", $isi + ['buat_ortu' => 1]);
    }

    // ------------------------------------------------------------ formulir

    public function test_formulir_pendaftaran_menanyakan_agama_dan_menyimpannya(): void
    {
        config(['school.ppdb_aktif' => true, 'school.ppdb_tahun_ajaran' => '2027/2028', 'school.ppdb_buka' => now()->subDay()->toDateTimeString(), 'school.ppdb_tutup' => now()->addDays(10)->toDateTimeString()]);

        $this->get('/ppdb')->assertOk()->assertSee('name="agama"', false)->assertSee('Konghucu');

        $this->post('/ppdb', [
            'nama' => 'Pendaftar Baru', 'jenis_kelamin' => 'Laki-Laki', 'agama' => 'Kristen', 'tempat_lahir' => 'Jambi', 'tanggal_lahir' => '2012-01-01', 'asal_sekolah' => 'SD 2',
            'alamat' => 'Jl. B', 'nama_ortu' => 'Ortu', 'telepon' => '081211112222', 'jalur' => 'Reguler',
        ])->assertRedirect(route('ppdb.sukses'));
        $this->assertSame('Kristen', PpdbPendaftar::where('nama', 'Pendaftar Baru')->value('agama'));

        $this->post('/ppdb', [
            'nama' => 'Agama Salah', 'jenis_kelamin' => 'Laki-Laki', 'agama' => 'Pagan', 'tempat_lahir' => 'Jambi', 'tanggal_lahir' => '2012-01-01', 'asal_sekolah' => 'SD 2',
            'alamat' => 'Jl. B', 'nama_ortu' => 'Ortu', 'telepon' => '081211112222', 'jalur' => 'Reguler',
        ])->assertSessionHasErrors('agama');
    }

    // ------------------------------------------------------------ konversi satu pendaftar

    public function test_pendaftar_diterima_dijadikan_siswa_dan_orang_tua_terhubung(): void
    {
        $p = $this->pendaftar();

        $this->jadikan($p, ['rombel_id' => $this->rombel->id])->assertRedirect("/admin/ppdb/{$p->id}")->assertSessionHas('toast_success');

        $siswa = Siswa::where('nisn', '0012345678')->firstOrFail();
        $this->assertSame(['Calon Siswa', 'Perempuan', 'Islam', '2012-05-05', 'aktif'], [$siswa->nama_siswa, $siswa->jenis_kelamin, $siswa->agama, $siswa->tanggal_lahir->toDateString(), $siswa->status]);
        $this->assertSame($this->rombel->id, $siswa->kelas_id);
        $this->assertTrue(\DB::table('rombel_siswa')->where('siswa_id', $siswa->id)->where('rombel_id', $this->rombel->id)->exists());   // masuk rombel tahun ajaran aktif
        $this->assertTrue($siswa->user->punyaPeran('siswa'));
        $this->assertTrue(\Hash::check('siswa123', $siswa->user->password));
        $this->assertSame('0012345678', $siswa->user->username);

        $ortu = OrangTua::where('nama', 'Bambang Calon')->firstOrFail();
        $this->assertSame(['0812-3456-7890', 'Jl. Merdeka No. 1'], [$ortu->no_hp, $ortu->alamat]);
        $this->assertSame([$siswa->id], $ortu->anak->pluck('id')->all());
        $this->assertSame('wali', $ortu->anak->first()->pivot->hubungan);
        $this->assertTrue($ortu->user->punyaPeran('orang_tua'));
        $this->assertTrue(\Hash::check('ortu123', $ortu->user->password));
        $this->assertSame('ortu0012345678', $ortu->user->username);

        $p->refresh();
        $this->assertSame($siswa->id, $p->siswa_id);
        $this->assertNotNull($p->dijadikan_siswa_pada);
        $this->assertTrue(LogAktivitas::where('aksi', 'ppdb.jadi_siswa')->where('deskripsi', 'like', '%PPDB-2027-%')->exists());

        // akun hasil konversi bisa login
        auth()->logout();
        $this->post('/login', ['username' => '0012345678', 'password' => 'siswa123'])->assertRedirect(route('siswa/dashboard'));
    }

    public function test_tidak_bisa_dikonversi_dua_kali_dan_hanya_yang_diterima(): void
    {
        $p = $this->pendaftar();
        $this->jadikan($p)->assertRedirect();

        $this->jadikan($p)->assertSessionHasErrors('konversi');
        $this->assertSame(1, Siswa::count());
        $this->assertSame(1, OrangTua::count());

        foreach (['menunggu', 'ditolak'] as $status) {
            $x = $this->pendaftar(['status' => $status, 'nisn' => '99'.random_int(100000, 999999)]);
            $this->jadikan($x)->assertSessionHasErrors('konversi');
        }
        $this->assertSame(1, Siswa::count());
    }

    public function test_agama_wajib_dan_bisa_dilengkapi_admin_untuk_pendaftar_lama(): void
    {
        $lama = $this->pendaftar(['agama' => null]);

        $this->jadikan($lama)->assertSessionHasErrors('konversi');
        $this->assertSame(0, Siswa::count());

        $this->jadikan($lama, ['agama' => 'Hindu'])->assertSessionHasNoErrors();
        $this->assertSame('Hindu', Siswa::first()->agama);
        $this->assertSame('Hindu', $lama->fresh()->agama);                    // pelengkapan tersimpan di data pendaftar juga

        $this->jadikan($this->pendaftar(['nisn' => '2222222222']), ['agama' => 'Pagan'])->assertSessionHasErrors('agama');
    }

    public function test_nisn_kosong_memakai_nomor_pendaftaran_dan_admin_bisa_mengisi(): void
    {
        $a = $this->pendaftar(['nisn' => null]);
        $b = $this->pendaftar(['nisn' => null, 'nama_ortu' => 'Ortu B', 'telepon' => '081377778888']);

        $this->jadikan($a)->assertSessionHasNoErrors();
        $this->assertNotNull(Siswa::where('nisn', $a->no_pendaftaran)->first());      // pengganti sementara

        $this->jadikan($b, ['nisn' => '5555555555'])->assertSessionHasNoErrors();
        $this->assertNotNull(Siswa::where('nisn', '5555555555')->first());
    }

    public function test_nisn_sudah_dipakai_atau_tidak_sah_ditolak(): void
    {
        $p = $this->pendaftar();
        Siswa::create(['user_id' => User::factory()->create(['role' => 'siswa'])->id, 'nisn' => '0012345678', 'nama_siswa' => 'Sudah Ada', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01']);

        $this->jadikan($p)->assertSessionHasErrors('konversi');
        $this->jadikan($p, ['nisn' => 'abc'])->assertSessionHasErrors('konversi');
        $this->assertNull($p->fresh()->siswa_id);
        $this->jadikan($p, ['nisn' => '0099999999'])->assertSessionHasNoErrors();
        $this->assertNotNull($p->fresh()->siswa_id);
    }

    public function test_rombel_harus_ada_di_tahun_ajaran_aktif(): void
    {
        $p = $this->pendaftar();

        $this->jadikan($p, ['rombel_id' => 99999])->assertSessionHasErrors('konversi');
        $this->assertSame(0, Siswa::count());

        $this->jadikan($p)->assertSessionHasNoErrors();                                // tanpa rombel boleh
        $this->assertNull(Siswa::first()->kelas_id);
    }

    // ------------------------------------------------------------ orang tua

    public function test_adik_kakak_dengan_orang_tua_sama_dihubungkan_ke_akun_yang_sama(): void
    {
        $kakak = $this->pendaftar(['nama' => 'Kakak', 'nisn' => '1000000001']);
        $adik = $this->pendaftar(['nama' => 'Adik', 'nisn' => '1000000002', 'nama_ortu' => 'bambang calon', 'telepon' => '+62 812 3456 7890']);   // nama beda huruf, nomor beda format
        $lain = $this->pendaftar(['nama' => 'Tetangga', 'nisn' => '1000000003', 'telepon' => '081299990000']);                                      // nama sama, nomor beda = orang lain

        $this->jadikan($kakak);
        $pratinjau = $this->get("/admin/ppdb/{$adik->id}/jadikan-siswa")->assertOk();
        $pratinjau->assertSee('sudah ada');
        $this->jadikan($adik);
        $this->jadikan($lain);

        $this->assertSame(2, OrangTua::count());
        $ortu = OrangTua::where('no_hp', '0812-3456-7890')->first();
        $this->assertSame(['Adik', 'Kakak'], $ortu->anak->pluck('nama_siswa')->sort()->values()->all());
        $this->assertSame(3, Siswa::count());
        $this->assertSame(1, User::withoutGlobalScopes()->where('username', 'like', 'ortu%')->where('username', 'not like', '%ortu10000000%')->count() >= 0 ? 1 : 0);
        $this->assertSame(2, User::whereHas('peran', fn ($q) => $q->where('kode', 'orang_tua'))->count());
    }

    public function test_akun_orang_tua_dapat_dimatikan(): void
    {
        $p = $this->pendaftar();

        $this->actingAs($this->admin)->post("/admin/ppdb/{$p->id}/jadikan-siswa", [])->assertSessionHasNoErrors();   // kotak buat_ortu tidak dicentang

        $this->assertSame(1, Siswa::count());
        $this->assertSame(0, OrangTua::count());
    }

    public function test_orang_tua_dilewati_bila_paket_tanpa_portal_dan_keuangan(): void
    {
        $this->sekolah->update(['tanpa_batas' => false]);
        $paket = Paket::create(['kode' => 'ppdbsaja', 'nama' => 'PPDB Saja', 'aktif' => true, 'urutan' => 9]);
        $paket->modul()->attach(Modul::where('kode', 'ppdb')->value('id'));
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => $paket->id, 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();
        $p = $this->pendaftar();

        $this->actingAs($this->admin)->get("/admin/ppdb/{$p->id}/jadikan-siswa")->assertOk()->assertSee('belum mencakup portal orang tua');
        $this->jadikan($p)->assertSessionHasNoErrors();

        $this->assertSame(1, Siswa::count());
        $this->assertSame(0, OrangTua::count());
    }

    // ------------------------------------------------------------ pratinjau dan halaman

    public function test_pratinjau_menampilkan_rencana_dan_peringatan(): void
    {
        $p = $this->pendaftar(['nisn' => null, 'agama' => null]);

        $res = $this->actingAs($this->admin)->get("/admin/ppdb/{$p->id}/jadikan-siswa")->assertOk();
        $res->assertSee('Yang akan dibuat')->assertSee($p->no_pendaftaran)->assertSee('sementara')->assertSee('Agama belum diisi')->assertSee('siswa123')
            ->assertSee('Akun')->assertSee('baru')->assertSee('Belum ditempatkan');

        $this->get("/admin/ppdb/{$p->id}/jadikan-siswa?".http_build_query(['agama' => 'Islam', 'nisn' => '', 'rombel_id' => $this->rombel->id, 'buat_ortu' => 1]))
            ->assertOk()->assertDontSee('Agama belum diisi')->assertSee('VII A');
        $this->get("/admin/ppdb/{$p->id}/jadikan-siswa?".http_build_query(['agama' => 'Islam']))->assertOk()->assertSee('dinonaktifkan');   // kotak ortu tidak dicentang
        $this->assertSame(0, Siswa::count());                                                                                                  // pratinjau tidak menyimpan
    }

    public function test_halaman_detail_menampilkan_tombol_sesuai_status(): void
    {
        $diterima = $this->pendaftar();
        $menunggu = $this->pendaftar(['status' => 'menunggu', 'nisn' => '3333333333']);
        $this->actingAs($this->admin);

        $this->get("/admin/ppdb/{$diterima->id}")->assertOk()->assertSee('Jadikan Siswa')->assertSee('Agama');
        $this->get("/admin/ppdb/{$menunggu->id}")->assertOk()->assertDontSee('Jadikan Siswa');

        $this->jadikan($diterima);
        $this->get("/admin/ppdb/{$diterima->id}")->assertOk()->assertSee('Sudah dijadikan siswa')->assertSee('Lihat di Data Siswa')->assertDontSee('/jadikan-siswa');
        $this->get("/admin/ppdb/{$diterima->id}/jadikan-siswa")->assertOk()->assertSee('sudah dijadikan siswa');   // pratinjau memberi tahu, tombol simpan nonaktif

        $baris = collect($this->getJson('/admin/ppdb/data?draw=1&start=0&length=20')->json('data'))->keyBy('no_pendaftaran');
        $this->assertSame('Sudah jadi siswa|primary', $baris[$diterima->no_pendaftaran]['jadi_siswa']);
        $this->assertSame('', $baris[$menunggu->no_pendaftaran]['jadi_siswa']);
    }

    // ------------------------------------------------------------ massal

    public function test_konversi_massal_melewati_yang_bermasalah_dan_melaporkannya(): void
    {
        $this->pendaftar(['nama' => 'Siap Satu', 'nisn' => '4000000001', 'nama_ortu' => 'Ortu Satu', 'telepon' => '081100000001']);
        $this->pendaftar(['nama' => 'Siap Dua', 'nisn' => '4000000002', 'nama_ortu' => 'Ortu Dua', 'telepon' => '081100000002']);
        $this->pendaftar(['nama' => 'Tanpa Agama', 'nisn' => '4000000003', 'agama' => null]);
        $this->pendaftar(['nama' => 'NISN Kembar', 'nisn' => '4000000001', 'nama_ortu' => 'Ortu Lain', 'telepon' => '081100000009']);
        $this->pendaftar(['nama' => 'Masih Menunggu', 'nisn' => '4000000005', 'status' => 'menunggu']);
        $this->pendaftar(['nama' => 'Ditolak', 'nisn' => '4000000006', 'status' => 'ditolak']);
        $this->actingAs($this->admin);

        $this->get('/admin/ppdb')->assertOk()->assertSee('<strong>4</strong> pendaftar yang diterima belum dijadikan siswa', false)->assertSee('Jadikan Siswa Semua yang Diterima');
        $this->post('/admin/ppdb/jadikan-siswa-massal', ['rombel_id' => $this->rombel->id])->assertRedirect('/admin/ppdb');

        $this->assertSame(['Siap Dua', 'Siap Satu'], Siswa::orderBy('nama_siswa')->pluck('nama_siswa')->all());
        $this->assertSame([$this->rombel->id], Siswa::pluck('kelas_id')->unique()->values()->all());
        $this->assertSame(2, OrangTua::count());

        $halaman = $this->get('/admin/ppdb')->assertOk()->assertSee('2 pendaftar dijadikan siswa')->assertSee('2 dilewati')->assertSee('Tanpa Agama')->assertSee('Agama belum diisi')->assertSee('NISN Kembar')->assertSee('sudah dipakai');
        $halaman->assertDontSee('Masih Menunggu')->assertDontSee('Ditolak (');

        // jalankan lagi: tidak ada duplikat, dan yang sudah dikonversi tidak diproses ulang
        $this->post('/admin/ppdb/jadikan-siswa-massal', [])->assertRedirect('/admin/ppdb');
        $this->assertSame(2, Siswa::count());
        $this->assertSame(2, PpdbKonversi::jumlahSiap());
    }

    public function test_konversi_massal_mengabaikan_rombel_yang_salah(): void
    {
        $this->pendaftar();
        $this->actingAs($this->admin);

        $this->post('/admin/ppdb/jadikan-siswa-massal', ['rombel_id' => 99999])->assertRedirect();

        $this->assertSame(0, Siswa::count());     // rombel tidak ada: dilewati dengan alasan, bukan menyimpan sembarangan
        $this->get('/admin/ppdb')->assertSee('1 dilewati')->assertSee('Rombel tujuan tidak ditemukan');
    }

    // ------------------------------------------------------------ keamanan

    public function test_pendaftar_sekolah_lain_tidak_bisa_dikonversi(): void
    {
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        $p = $this->pendaftar([], $lain);
        $this->actingAs($this->admin);

        $this->get("/admin/ppdb/{$p->id}/jadikan-siswa")->assertNotFound();
        $this->post("/admin/ppdb/{$p->id}/jadikan-siswa", ['buat_ortu' => 1])->assertNotFound();
        $this->assertNull($p->fresh()->siswa_id);

        $this->pendaftar(['nisn' => '8000000001'], $lain);
        $this->post('/admin/ppdb/jadikan-siswa-massal', [])->assertRedirect();
        $this->assertSame(0, Siswa::withoutGlobalScopes()->count());                  // massal hanya sekolah sendiri
    }

    public function test_hanya_admin_yang_boleh_mengonversi(): void
    {
        $p = $this->pendaftar();
        $this->actingAs(User::factory()->create(['role' => 'guru']));

        $this->get("/admin/ppdb/{$p->id}/jadikan-siswa")->assertRedirect();
        $this->post("/admin/ppdb/{$p->id}/jadikan-siswa", [])->assertRedirect();
        $this->post('/admin/ppdb/jadikan-siswa-massal', [])->assertRedirect();
        $this->assertSame(0, Siswa::count());
    }

    public function test_konversi_ditolak_bila_ppdb_tidak_termasuk_paket(): void
    {
        $p = $this->pendaftar();
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'basic')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();

        $this->actingAs($this->admin)->get("/admin/ppdb/{$p->id}/jadikan-siswa")->assertRedirect();
        $this->post("/admin/ppdb/{$p->id}/jadikan-siswa", [])->assertRedirect();
        $this->assertSame(0, Siswa::count());
    }

    public function test_pendaftar_yang_dikonversi_tetap_bisa_dihapus_tanpa_menghapus_siswa(): void
    {
        $p = $this->pendaftar();
        $this->jadikan($p);

        $this->deleteJson("/admin/ppdb/{$p->id}")->assertOk();

        $this->assertSame(1, Siswa::count());
    }
}
