<?php

namespace Tests\Feature;

use App\Berita;
use App\Kategori;
use App\Pengaturan;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BeritaPengaturanTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // public/ dialihkan ke folder sementara, jadi manifest build tidak ditemukan

        $this->publicDir = sys_get_temp_dir().'/smpn-test-public-'.uniqid();
        File::makeDirectory($this->publicDir, 0777, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function dt(array $extra = []): string
    {
        return http_build_query($extra + ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'berita_judul' => 'Juara Lomba Sains',
            'kategori_id' => Kategori::firstOrCreate(['kategori_nama' => 'Prestasi'])->id,
            'berita_isi' => '<p>Selamat kepada <strong>para juara</strong>.</p>',
        ];
    }

    // ------------------------------------------------------------------ berita

    public function test_admin_menulis_berita_dengan_gambar_slug_dan_tampil_di_publik(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/berita', $this->payload(['berita_gambar' => UploadedFile::fake()->image('a.jpg')->size(100)]), ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('redirect', route('admin/berita'));

        $berita = Berita::firstOrFail();
        $this->assertSame('juara-lomba-sains', $berita->berita_slug);
        $this->assertSame($admin->name, $berita->author);
        $this->assertFileExists($this->publicDir.'/storage/berita/files/'.$berita->berita_gambar);
        $this->assertSame(asset('storage/berita/files/'.$berita->berita_gambar), $berita->gambar_url);

        auth()->logout();
        $this->get('/berita/juara-lomba-sains')->assertOk()->assertSee('para juara', false)->assertSee($berita->gambar_url, false);
        $this->assertSame(1, $berita->fresh()->berita_views);
        $this->get('/berita')->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_html_berbahaya_dibersihkan_saat_disimpan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/admin/berita', $this->payload([
            'berita_isi' => '<p onclick="x()">Halo</p><script>alert(1)</script><a href="javascript:alert(2)">klik</a><img src=x onerror=alert(3)><strong>tebal</strong>',
        ]))->assertCreated();

        $isi = Berita::firstOrFail()->berita_isi;
        $this->assertStringNotContainsString('<script', $isi);
        $this->assertStringNotContainsString('onclick', $isi);
        $this->assertStringNotContainsString('javascript:', $isi);
        $this->assertStringNotContainsString('onerror', $isi);
        $this->assertStringContainsString('<strong>tebal</strong>', $isi);
        $this->assertStringContainsString('Halo', $isi);
    }

    public function test_validasi_berita(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/admin/berita', [])->assertStatus(422)->assertJsonValidationErrors(['berita_judul', 'kategori_id', 'berita_isi']);
        $this->postJson('/admin/berita', $this->payload(['kategori_id' => 999]))->assertStatus(422)->assertJsonValidationErrors('kategori_id');
        $this->postJson('/admin/berita', $this->payload(['berita_gambar' => UploadedFile::fake()->create('x.php', 5, 'text/x-php')]))->assertStatus(422)->assertJsonValidationErrors('berita_gambar');
    }

    public function test_ubah_berita_mengganti_gambar_dan_menghapus_yang_lama(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/admin/berita', $this->payload(['berita_gambar' => UploadedFile::fake()->image('a.jpg')->size(50)]), ['Accept' => 'application/json'])->assertCreated();
        $berita = Berita::firstOrFail();
        $lama = $berita->berita_gambar;

        $this->post("/admin/berita/{$berita->id}", $this->payload(['_method' => 'PUT', 'berita_judul' => 'Judul Baru', 'berita_gambar' => UploadedFile::fake()->image('b.png')->size(50)]), ['Accept' => 'application/json'])->assertOk();

        $berita->refresh();
        $this->assertSame('Judul Baru', $berita->berita_judul);
        $this->assertFileDoesNotExist($this->publicDir.'/storage/berita/files/'.$lama);
        $this->assertFileExists($this->publicDir.'/storage/berita/files/'.$berita->berita_gambar);

        // tanpa gambar baru: gambar lama dipertahankan
        $this->putJson("/admin/berita/{$berita->id}", $this->payload())->assertOk();
        $this->assertFileExists($this->publicDir.'/storage/berita/files/'.$berita->fresh()->berita_gambar);

        $this->deleteJson("/admin/berita/{$berita->id}")->assertOk();
        $this->assertFileDoesNotExist($this->publicDir.'/storage/berita/files/'.$berita->berita_gambar);
        $this->assertDatabaseCount('berita', 0);
    }

    public function test_gambar_lama_di_folder_kategori_tetap_tampil_dan_ikut_terhapus(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $kategori = Kategori::create(['kategori_nama' => 'Prestasi']);
        File::makeDirectory($this->publicDir.'/storage/berita/Prestasi', 0777, true);
        File::put($this->publicDir.'/storage/berita/Prestasi/lama.jpg', 'x');
        $berita = Berita::create(['berita_judul' => 'Lama', 'berita_isi' => 'i', 'kategori_id' => $kategori->id, 'author' => 'A', 'berita_gambar' => 'lama.jpg']);

        $this->assertSame(asset('storage/berita/Prestasi/lama.jpg'), $berita->gambar_url);
        $this->actingAs($admin)->deleteJson("/admin/berita/{$berita->id}")->assertOk();
        $this->assertFileDoesNotExist($this->publicDir.'/storage/berita/Prestasi/lama.jpg');
    }

    public function test_guru_hanya_mengelola_berita_miliknya(): void
    {
        $guru = User::factory()->create(['role' => 'guru', 'name' => 'Guru A']);
        $lain = User::factory()->create(['role' => 'guru', 'name' => 'Guru B']);

        $this->actingAs($guru)->postJson('/guru/berita', $this->payload(['berita_judul' => 'Milik A']))->assertCreated();
        $this->actingAs($lain)->postJson('/guru/berita', $this->payload(['berita_judul' => 'Milik B']))->assertCreated();
        $milikA = Berita::where('berita_judul', 'Milik A')->firstOrFail();

        $this->getJson('/guru/berita/data?'.$this->dt())->assertJsonCount(1, 'data')->assertJsonPath('data.0.berita_judul', 'Milik B');
        $this->get("/guru/berita/{$milikA->id}/edit")->assertForbidden();
        $this->putJson("/guru/berita/{$milikA->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/guru/berita/{$milikA->id}")->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/admin/berita/data?'.$this->dt())->assertJsonCount(2, 'data');
    }

    public function test_siswa_tidak_bisa_mengakses_berita_admin_dan_artikel_tidak_ada_404(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'siswa']))->getJson('/admin/berita/data')->assertForbidden();
        auth()->logout();
        $this->get('/berita/tidak-ada')->assertNotFound();
    }

    public function test_pencarian_berita_lewat_kategori(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/admin/berita', $this->payload(['berita_judul' => 'A', 'kategori_id' => Kategori::create(['kategori_nama' => 'Olahraga'])->id]))->assertCreated();
        $this->postJson('/admin/berita', $this->payload(['berita_judul' => 'B']))->assertCreated();

        $this->getJson('/admin/berita/data?'.$this->dt(['search' => ['value' => 'Olahraga']]))->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.berita_judul', 'A');
    }

    // ------------------------------------------------------------------ profil sekolah

    public function test_halaman_profil_sekolah_membuat_baris_yang_belum_ada(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/pengaturan')->assertOk()->assertSee('Struktur Organisasi');

        $this->assertSame(8, Pengaturan::count());
        $this->assertSame('Tentang Kami', Pengaturan::find(1)->judul);
        auth()->logout();
        $this->get('/profil')->assertOk();
    }

    public function test_ubah_halaman_profil_dengan_gambar_dan_html_dibersihkan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/pengaturan')->assertOk();

        $this->post('/admin/pengaturan/1', [
            '_method' => 'PUT', 'judul' => 'Tentang Sekolah', 'deskripsi' => '<p>Selamat datang</p><script>x()</script>',
            'gambar' => UploadedFile::fake()->image('g.jpg')->size(100),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['message', 'preview']);

        $row = Pengaturan::find(1);
        $this->assertSame('Tentang Sekolah', $row->judul);
        $this->assertStringNotContainsString('<script', $row->deskripsi);
        $this->assertFileExists($this->publicDir.'/storage/pengaturan/tentangkami/'.$row->gambar);
        $lama = $row->gambar;

        $this->post('/admin/pengaturan/1', ['_method' => 'PUT', 'judul' => 'X', 'gambar' => UploadedFile::fake()->image('h.jpg')->size(50)], ['Accept' => 'application/json'])->assertOk();
        $this->assertFileDoesNotExist($this->publicDir.'/storage/pengaturan/tentangkami/'.$lama);

        auth()->logout();
        $this->get('/profil')->assertOk();
    }

    public function test_halaman_tanpa_gambar_menolak_unggahan_dan_id_di_luar_daftar_404(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/pengaturan')->assertOk();

        // Sejarah (id 3) tidak punya gambar: berkas diabaikan
        $this->post('/admin/pengaturan/3', ['_method' => 'PUT', 'judul' => 'Sejarah', 'gambar' => UploadedFile::fake()->image('g.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->assertNull(Pengaturan::find(3)->gambar);

        $this->putJson('/admin/pengaturan/1', [])->assertStatus(422)->assertJsonValidationErrors('judul');

        DB::table('pengaturan')->insert(['sekolah_id' => $this->sekolah->id, 'id' => 99, 'judul' => 'Lain', 'created_at' => now(), 'updated_at' => now()]);
        $this->putJson('/admin/pengaturan/99', ['judul' => 'x'])->assertNotFound();
    }
}
