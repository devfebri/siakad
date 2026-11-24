<?php

namespace Tests\Feature;

use App\Berita;
use App\Kategori;
use App\PpdbPendaftar;
use App\ProgramUnggulan;
use App\Support\Ppdb;
use App\Testimoni;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PpdbSitusTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->publicDir = sys_get_temp_dir().'/smpn-test-public-'.uniqid();
        File::makeDirectory($this->publicDir, 0777, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function bukaPpdb(): void
    {
        config([
            'school.ppdb_aktif' => true,
            'school.ppdb_tahun_ajaran' => '2027/2028',
            'school.ppdb_buka' => now()->subDay()->toDateTimeString(),
            'school.ppdb_tutup' => now()->addDays(10)->toDateTimeString(),
        ]);
    }

    private function dt(array $extra = []): string
    {
        return http_build_query($extra + ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    private function formulir(array $extra = []): array
    {
        return $extra + [
            'nama' => 'Calon Siswa', 'jenis_kelamin' => 'Perempuan', 'tempat_lahir' => 'Jambi', 'tanggal_lahir' => '2011-05-20',
            'nisn' => '0012345678', 'asal_sekolah' => 'SMP Negeri 2', 'alamat' => 'Jl. Merdeka No. 1', 'nama_ortu' => 'Bapak Calon',
            'telepon' => '081234567890', 'email' => 'ortu@contoh.test', 'jalur' => 'Reguler', 'peminatan' => 'IPA', 'nilai_rata' => '85.5',
        ];
    }

    // ------------------------------------------------------------------ status PPDB

    public function test_status_ppdb_mengikuti_pengaturan_dan_tanggal(): void
    {
        $this->assertSame('nonaktif', Ppdb::status()['state']);

        config(['school.ppdb_aktif' => true, 'school.ppdb_buka' => now()->addDays(2)->toDateTimeString()]);
        $this->assertSame('belum', Ppdb::status()['state']);

        $this->bukaPpdb();
        $this->assertSame('buka', Ppdb::status()['state']);
        $this->assertTrue(Ppdb::status()['bisa_daftar']);

        config(['school.ppdb_tutup' => now()->subHour()->toDateTimeString()]);
        $this->assertSame('tutup', Ppdb::status()['state']);
    }

    public function test_halaman_ppdb_menampilkan_formulir_saat_dibuka_dan_hitung_mundur(): void
    {
        $this->bukaPpdb();

        $this->get('/ppdb')->assertOk()->assertSee('Formulir Pendaftaran')->assertSee('data-countdown', false)->assertSee('2027/2028');
        $this->get('/')->assertOk()->assertSee('data-countdown', false);
    }

    public function test_formulir_tidak_tampil_dan_pendaftaran_ditolak_saat_ditutup(): void
    {
        $this->get('/ppdb')->assertOk()->assertDontSee('Formulir Pendaftaran')->assertSee('Belum dibuka');

        $this->post('/ppdb', $this->formulir())->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('ppdb_pendaftar', 0);
    }

    // ------------------------------------------------------------------ pendaftaran

    public function test_pendaftaran_berhasil_membuat_nomor_dan_menyimpan_berkas(): void
    {
        $this->bukaPpdb();

        $this->post('/ppdb', $this->formulir(['berkas' => UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf')]))
            ->assertRedirect(route('ppdb.sukses'));

        $p = PpdbPendaftar::firstOrFail();
        $this->assertSame(sprintf('PPDB-2027-%04d', $p->id), $p->no_pendaftaran);
        $this->assertSame('menunggu', $p->status);
        $this->assertSame('2027/2028', $p->tahun_ajaran);
        $this->assertFileExists($this->publicDir.'/'.$p->berkas);

        $this->get('/ppdb/sukses')->assertOk()->assertSee($p->no_pendaftaran);
        $this->post('/ppdb', $this->formulir(['nama' => 'Kedua']));
        $this->assertSame(2, PpdbPendaftar::count());
        $this->assertNotSame(PpdbPendaftar::first()->no_pendaftaran, PpdbPendaftar::latest('id')->first()->no_pendaftaran);
    }

    public function test_halaman_sukses_tanpa_pendaftaran_dialihkan(): void
    {
        $this->get('/ppdb/sukses')->assertRedirect(route('ppdb'));
    }

    public function test_validasi_pendaftaran(): void
    {
        $this->bukaPpdb();

        $this->post('/ppdb', [])->assertSessionHasErrors(['nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'asal_sekolah', 'alamat', 'nama_ortu', 'telepon', 'jalur']);
        $this->post('/ppdb', $this->formulir(['tanggal_lahir' => '2999-01-01']))->assertSessionHasErrors('tanggal_lahir');
        $this->post('/ppdb', $this->formulir(['nisn' => 'abc']))->assertSessionHasErrors('nisn');
        $this->post('/ppdb', $this->formulir(['telepon' => 'xx']))->assertSessionHasErrors('telepon');
        $this->post('/ppdb', $this->formulir(['jalur' => 'Rahasia']))->assertSessionHasErrors('jalur');
        $this->post('/ppdb', $this->formulir(['nilai_rata' => '150']))->assertSessionHasErrors('nilai_rata');
        $this->post('/ppdb', $this->formulir(['berkas' => UploadedFile::fake()->create('x.php', 5, 'text/x-php')]))->assertSessionHasErrors('berkas');
        $this->assertDatabaseCount('ppdb_pendaftar', 0);
    }

    public function test_pendaftaran_dibatasi_laju(): void
    {
        $this->bukaPpdb();
        for ($i = 0; $i < 10; $i++) {
            $this->post('/ppdb', []);
        }
        $this->post('/ppdb', $this->formulir())->assertStatus(429);
    }

    // ------------------------------------------------------------------ cek status

    public function test_cek_status_memerlukan_nomor_dan_tanggal_lahir_yang_cocok(): void
    {
        $this->bukaPpdb();
        $this->post('/ppdb', $this->formulir());
        $p = PpdbPendaftar::firstOrFail();
        $p->update(['status' => 'diterima', 'catatan' => 'Selamat bergabung']);

        $this->get('/ppdb/cek')->assertOk();
        $this->post('/ppdb/cek', ['no_pendaftaran' => strtolower($p->no_pendaftaran), 'tanggal_lahir' => '2011-05-20'])
            ->assertOk()->assertSee('Diterima')->assertSee('Selamat bergabung')->assertSee('Calon Siswa');
        $this->post('/ppdb/cek', ['no_pendaftaran' => $p->no_pendaftaran, 'tanggal_lahir' => '2011-05-21'])
            ->assertOk()->assertSee('Data tidak ditemukan')->assertDontSee('Selamat bergabung');
        $this->post('/ppdb/cek', ['no_pendaftaran' => 'PPDB-2027-9999', 'tanggal_lahir' => '2011-05-20'])->assertSee('Data tidak ditemukan');
        $this->post('/ppdb/cek', [])->assertSessionHasErrors(['no_pendaftaran', 'tanggal_lahir']);
    }

    // ------------------------------------------------------------------ admin PPDB

    private function seedPendaftar(): void
    {
        $this->bukaPpdb();
        foreach (['A', 'B', 'C'] as $n => $nama) {
            $this->post('/ppdb', $this->formulir(['nama' => "Siswa $nama", 'jalur' => $n === 2 ? 'Prestasi' : 'Reguler']));
        }
    }

    public function test_admin_melihat_memfilter_dan_memverifikasi_pendaftar(): void
    {
        $this->seedPendaftar();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/ppdb')->assertOk()->assertSee('Menunggu verifikasi');
        $this->getJson('/admin/ppdb/data?'.$this->dt())->assertJsonPath('recordsTotal', 3)->assertJsonPath('data.0.nama', 'Siswa C')->assertJsonPath('data.0.status', 'Menunggu|warning');

        $c = PpdbPendaftar::where('nama', 'Siswa C')->firstOrFail();
        $this->get("/admin/ppdb/{$c->id}")->assertOk()->assertSee($c->no_pendaftaran);
        $this->putJson("/admin/ppdb/{$c->id}/status", ['status' => 'diterima', 'catatan' => 'Lolos seleksi'])->assertOk()
            ->assertJsonPath('redirect', route('admin/ppdb.show', $c)); // halaman detail dimuat ulang supaya perubahan langsung terlihat
        $this->putJson('/admin/ppdb/'.PpdbPendaftar::where('nama', 'Siswa A')->value('id').'/status', ['status' => 'ditolak'])->assertOk();
        $this->putJson("/admin/ppdb/{$c->id}/status", ['status' => 'bebas'])->assertStatus(422)->assertJsonValidationErrors('status');

        $this->getJson('/admin/ppdb/data?'.$this->dt(['status' => 'diterima']))->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.status', 'Diterima|success');
        $this->getJson('/admin/ppdb/data?'.$this->dt(['jalur' => 'Prestasi']))->assertJsonPath('recordsFiltered', 1);
        $this->getJson('/admin/ppdb/data?'.$this->dt(['search' => ['value' => 'Siswa B']]))->assertJsonPath('recordsFiltered', 1);
    }

    public function test_lencana_dan_notifikasi_pendaftar_menunggu_di_panel_admin(): void
    {
        $this->seedPendaftar();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/dashboard')
            ->assertOk()->assertSee('3 pendaftar PPDB menunggu');
    }

    public function test_hapus_pendaftar_menghapus_berkas(): void
    {
        $this->bukaPpdb();
        $this->post('/ppdb', $this->formulir(['berkas' => UploadedFile::fake()->create('kk.pdf', 10, 'application/pdf')]));
        $p = PpdbPendaftar::firstOrFail();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->deleteJson("/admin/ppdb/{$p->id}")->assertOk();
        $this->assertDatabaseCount('ppdb_pendaftar', 0);
        $this->assertFileDoesNotExist($this->publicDir.'/'.$p->berkas);
    }

    public function test_ekspor_csv_memfilter_dan_menetralkan_rumus(): void
    {
        $this->bukaPpdb();
        $this->post('/ppdb', $this->formulir(['nama' => '=HYPERLINK("http://jahat.test")']));
        $this->post('/ppdb', $this->formulir(['nama' => 'Normal']));
        PpdbPendaftar::where('nama', 'Normal')->update(['status' => 'diterima']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $semua = $this->get('/admin/ppdb/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $semua);
        $this->assertStringNotContainsString(',=HYPERLINK', $semua);
        $this->assertStringContainsString('Normal', $semua);

        $diterima = $this->get('/admin/ppdb/export?status=diterima')->streamedContent();
        $this->assertStringContainsString('Normal', $diterima);
        $this->assertStringNotContainsString('HYPERLINK', $diterima);
    }

    public function test_hanya_admin_yang_mengelola_ppdb(): void
    {
        $this->seedPendaftar();
        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->getJson('/admin/ppdb/data')->assertForbidden();
        $this->putJson('/admin/ppdb/1/status', ['status' => 'diterima'])->assertForbidden();
        $this->get('/admin/ppdb/export')->assertRedirect();
    }

    // ------------------------------------------------------------------ situs publik

    public function test_beranda_menampilkan_konten_dari_database(): void
    {
        config(['school.hero_judul' => 'Slogan Uji Coba', 'school.stat_prestasi' => 120]);
        ProgramUnggulan::create(['judul' => 'Kelas Sains Uji', 'deskripsi' => 'Lab lengkap', 'ikon' => 'flask', 'urutan' => 1, 'aktif' => true]);
        ProgramUnggulan::create(['judul' => 'Program Tersembunyi', 'ikon' => 'book', 'urutan' => 2, 'aktif' => false]);
        Testimoni::create(['nama' => 'Rina Alumni', 'peran' => 'Alumni 2022', 'isi' => 'Sekolah yang hebat sekali.', 'urutan' => 1, 'aktif' => true]);
        Berita::create(['berita_judul' => 'Berita Pertama Uji', 'berita_isi' => '<p>isi</p>', 'kategori_id' => Kategori::create(['kategori_nama' => 'Umum'])->id, 'author' => 'A']);

        $this->get('/')->assertOk()
            ->assertSee('Slogan Uji Coba')->assertSee('120+')->assertSee('Kelas Sains Uji')->assertDontSee('Program Tersembunyi')
            ->assertSee('Rina Alumni')->assertSee('Berita Pertama Uji')->assertSee('Daftar Sekarang');
    }

    public function test_alamat_lama_dialihkan_ke_alamat_baru(): void
    {
        $this->get('/artikel')->assertRedirect('/berita')->assertStatus(301);
        $this->get('/artikel/judul-lama')->assertRedirect(route('berita.show', 'judul-lama'));
        $this->get('/artikel/kategori/Umum')->assertRedirect(route('berita', ['kategori' => 'Umum']));
        $this->get('/tentangkami')->assertRedirect(route('profil').'#tentangkami');
        $this->get('/strukturorganisasi')->assertRedirect(route('profil').'#struktur');
        $this->get('/guru')->assertRedirect(route('profil').'#guru');
        $this->get('/gallery')->assertRedirect('/galeri');
        $this->get('/hubungikami')->assertRedirect('/kontak');
    }

    public function test_pencarian_dan_filter_berita_publik(): void
    {
        $umum = Kategori::create(['kategori_nama' => 'Umum']);
        $prestasi = Kategori::create(['kategori_nama' => 'Prestasi']);
        Berita::create(['berita_judul' => 'Juara Catur', 'berita_isi' => 'x', 'kategori_id' => $prestasi->id, 'author' => 'A']);
        Berita::create(['berita_judul' => 'Rapat Orang Tua', 'berita_isi' => 'x', 'kategori_id' => $umum->id, 'author' => 'A']);

        $judul = fn (string $url) => $this->get($url)->assertOk()->viewData('berita')->pluck('berita_judul')->all();

        $this->assertSame(['Juara Catur'], $judul('/berita?q=Catur'));
        $this->assertSame(['Rapat Orang Tua'], $judul('/berita?kategori=Umum'));
        $this->assertSame([], $judul('/berita?q=%25')); // tanda % dianggap teks biasa, bukan wildcard
        $this->get('/berita?q=%25')->assertSee('Belum ada berita yang cocok');
    }

    public function test_halaman_publik_tidak_menampilkan_html_berbahaya_dari_data_lama(): void
    {
        $kat = Kategori::create(['kategori_nama' => 'Umum']);
        $b = Berita::create(['berita_judul' => 'Data Lama', 'berita_isi' => '<p>aman</p><script>alert(1)</script><img src="x" onerror="alert(2)">', 'kategori_id' => $kat->id, 'author' => 'A']);

        $this->get('/berita/'.$b->berita_slug)->assertOk()->assertSee('aman')->assertDontSee('<script>alert(1)', false)->assertDontSee('onerror', false);
    }

    public function test_menu_dan_footer_memakai_identitas_sekolah(): void
    {
        config(['school.name' => 'SMA Contoh Nusantara', 'school.alamat' => 'Jl. Pendidikan 5', 'school.instagram' => 'https://instagram.com/sman', 'school.telepon' => '0741-123']);

        $this->get('/kontak')->assertOk()->assertSee('SMA Contoh Nusantara')->assertSee('Jl. Pendidikan 5')->assertSee('https://instagram.com/sman', false)->assertSee('0741-123');
        $this->get('/')->assertSee('Beranda')->assertSee('Profil')->assertSee('Akademik')->assertSee('Berita')->assertSee('Galeri')->assertSee('PPDB')->assertSee('Kontak');
    }
}
