<?php

namespace Tests\Feature;

use App\Unduhan;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UnduhanTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->publicDir = sys_get_temp_dir().'/smpn-unduhan-public-'.uniqid();
        File::makeDirectory($this->publicDir, 0777, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function pdf(string $nama = 'formulir.pdf', int $kb = 50): UploadedFile
    {
        return UploadedFile::fake()->create($nama, $kb, 'application/pdf');
    }

    private function tambah(array $extra = [], ?UploadedFile $berkas = null): Unduhan
    {
        $this->actingAs($this->admin())->post('/admin/unduhan', $extra + ['judul' => 'Formulir Pendaftaran', 'kategori' => 'Formulir', 'aktif' => 1, 'berkas' => $berkas ?? $this->pdf()], ['Accept' => 'application/json'])->assertCreated();
        auth()->logout();

        return Unduhan::latest('id')->firstOrFail();
    }

    // ------------------------------------------------------------ admin

    public function test_admin_menambah_berkas_dan_tampil_di_menu_dan_daftar(): void
    {
        $u = $this->tambah(['deskripsi' => 'Isi lalu cetak', 'urutan' => 2]);

        $this->assertSame('formulir.pdf', $u->nama_asli);
        $this->assertSame('Formulir', $u->kategori);
        $this->assertTrue($u->aktif);
        $this->assertGreaterThan(0, $u->ukuran);
        $this->assertFileExists($this->publicDir.'/storage/unduhan/'.$u->berkas);
        $this->assertNotSame('formulir.pdf', $u->berkas, 'nama berkas di server diacak');

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/unduhan')->assertOk()->assertSee('Tambah Berkas')->assertSee('Lihat di Situs');
        $this->get('/admin/dashboard')->assertOk()->assertSee('Unduhan');
        $data = $this->getJson('/admin/unduhan/data?draw=1&start=0&length=10&search[value]=&columns[0][data]=&order[0][column]=0&order[0][dir]=asc')->assertOk();
        $this->assertSame(1, $data->json('recordsTotal'));
        $this->assertSame('Formulir Pendaftaran', $data->json('data.0.judul'));
        $this->assertStringContainsString('/storage/unduhan/'.$u->berkas, $data->json('data.0.file_url'));
    }

    public function test_validasi_berkas_unduhan(): void
    {
        $this->actingAs($this->admin());
        $ok = ['judul' => 'X', 'kategori' => 'Umum'];
        $json = ['Accept' => 'application/json'];

        $this->post('/admin/unduhan', $ok, $json)->assertStatus(422)->assertJsonValidationErrors('berkas'); // wajib saat tambah
        $this->post('/admin/unduhan', ['judul' => '', 'kategori' => ''] + ['berkas' => $this->pdf()], $json)->assertStatus(422)->assertJsonValidationErrors(['judul', 'kategori']);
        $this->post('/admin/unduhan', $ok + ['berkas' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')], $json)->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->post('/admin/unduhan', $ok + ['berkas' => UploadedFile::fake()->create('virus.exe', 10)], $json)->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->post('/admin/unduhan', $ok + ['berkas' => UploadedFile::fake()->create('halaman.html', 10, 'text/html')], $json)->assertStatus(422);

        // berkas PHP yang diberi ekstensi .pdf ditolak karena isinya dibaca
        $tmp = tempnam(sys_get_temp_dir(), 'ud');
        file_put_contents($tmp, '<?php echo 1;');
        $this->post('/admin/unduhan', $ok + ['berkas' => new UploadedFile($tmp, 'menyamar.pdf', 'application/pdf', null, true)], $json)->assertStatus(422);
        @unlink($tmp);

        $this->post('/admin/unduhan', $ok + ['berkas' => UploadedFile::fake()->create('batas.pdf', 10240, 'application/pdf')], $json)->assertCreated();
        $this->assertSame(1, Unduhan::count());
    }

    public function test_ubah_tanpa_berkas_baru_mempertahankan_berkas_dan_ganti_berkas_menghapus_yang_lama(): void
    {
        $u = $this->tambah();
        $admin = $this->admin();

        $this->actingAs($admin)->put("/admin/unduhan/{$u->id}", ['judul' => 'Judul Baru', 'kategori' => 'Panduan'], ['Accept' => 'application/json'])->assertOk();
        $u->refresh();
        $this->assertSame(['Judul Baru', 'Panduan', false], [$u->judul, $u->kategori, $u->aktif]); // aktif tidak dikirim = disembunyikan
        $this->assertFileExists($u->path);

        $lama = $u->path;
        $this->post("/admin/unduhan/{$u->id}", ['_method' => 'PUT', 'judul' => 'Judul Baru', 'kategori' => 'Panduan', 'aktif' => 1, 'berkas' => $this->pdf('versi-2.pdf')], ['Accept' => 'application/json'])->assertOk();
        $u->refresh();
        $this->assertSame('versi-2.pdf', $u->nama_asli);
        $this->assertFileDoesNotExist($lama);
        $this->assertFileExists($u->path);

        $this->deleteJson("/admin/unduhan/{$u->id}")->assertOk();
        $this->assertDatabaseMissing('unduhan', ['id' => $u->id]);
        $this->assertFileDoesNotExist($u->path);
    }

    public function test_hanya_admin_mengelola_unduhan(): void
    {
        $u = $this->tambah();

        $this->get('/admin/unduhan')->assertRedirect();
        $this->postJson('/admin/unduhan', [])->assertUnauthorized();
        foreach (['guru', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/admin/unduhan')->assertRedirect();
            $this->postJson('/admin/unduhan', [])->assertForbidden();
            $this->deleteJson("/admin/unduhan/{$u->id}")->assertForbidden();
        }
        $this->assertSame(1, Unduhan::count());
    }

    // ------------------------------------------------------------ situs publik

    public function test_halaman_unduhan_publik_hanya_menampilkan_berkas_aktif(): void
    {
        $this->tambah(['judul' => 'Brosur PPDB', 'kategori' => 'Brosur', 'deskripsi' => 'Informasi lengkap']);
        $this->tambah(['judul' => 'Dokumen Rahasia', 'aktif' => 0]);

        $this->get('/unduhan')->assertOk()->assertSee('Brosur PPDB')->assertSee('Informasi lengkap')->assertSee('PDF')->assertSee('Unduh')->assertDontSee('Dokumen Rahasia');
        $this->get('/')->assertOk()->assertSee(route('unduhan')); // menu navbar
    }

    public function test_pencarian_dan_filter_kategori(): void
    {
        $this->tambah(['judul' => 'Formulir Mutasi', 'kategori' => 'Formulir']);
        $this->tambah(['judul' => 'Panduan Siswa Baru', 'kategori' => 'Panduan', 'deskripsi' => 'Tata tertib']);

        $this->get('/unduhan?q=mutasi')->assertSee('Formulir Mutasi')->assertDontSee('Panduan Siswa Baru');
        $this->get('/unduhan?q=tertib')->assertSee('Panduan Siswa Baru')->assertDontSee('Formulir Mutasi');
        $this->get('/unduhan?kategori=Panduan')->assertSee('Panduan Siswa Baru')->assertDontSee('Formulir Mutasi');
        $this->get('/unduhan?q=tidakada')->assertOk()->assertSee('Tidak ada berkas yang cocok');
        $this->get('/unduhan?q=%25')->assertOk(); // karakter wildcard tidak merusak pencarian
    }

    public function test_urutan_dan_paginasi(): void
    {
        foreach (range(1, 14) as $i) {
            Unduhan::create(['judul' => "Berkas $i", 'kategori' => 'Umum', 'berkas' => "f$i.pdf", 'nama_asli' => "f$i.pdf", 'mime' => 'application/pdf', 'ukuran' => 1024, 'urutan' => $i, 'aktif' => true]);
        }

        $this->get('/unduhan')->assertOk()->assertSee('Berkas 1')->assertSee('Berkas 12')->assertDontSee('Berkas 13');
        $this->get('/unduhan?page=2')->assertSee('Berkas 13')->assertSee('Berkas 14');
    }

    public function test_unduh_berkas_menambah_hitungan_dan_memakai_nama_asli(): void
    {
        $u = $this->tambah(['judul' => 'Formulir A'], $this->pdf('Formulir Mutasi Siswa.pdf'));

        $res = $this->get(route('unduhan.unduh', $u))->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
        $this->assertStringContainsString('Formulir Mutasi Siswa.pdf', $res->headers->get('content-disposition'));
        $res->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('unduhan.unduh', $u))->assertOk();
        $this->assertSame(2, $u->fresh()->jumlah_unduh);
        $this->get('/unduhan')->assertSee('2× diunduh');
    }

    public function test_unduh_berkas_tersembunyi_atau_hilang_404(): void
    {
        $u = $this->tambah();

        $u->update(['aktif' => false]);
        $this->get(route('unduhan.unduh', $u))->assertNotFound();
        $this->assertSame(0, $u->fresh()->jumlah_unduh);

        $u->update(['aktif' => true]);
        File::delete($u->path);
        $this->get(route('unduhan.unduh', $u))->assertNotFound();
        $this->get('/unduhan/99999/unduh')->assertNotFound();
    }
}
