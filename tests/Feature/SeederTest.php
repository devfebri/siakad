<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Seeder menyalin foto contoh ke folder publik; arahkan ke folder sementara.
        $this->publicDir = sys_get_temp_dir().'/smpn-seed-public-'.uniqid();
        \Illuminate\Support\Facades\File::makeDirectory($this->publicDir, 0777, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    public function test_seeder_idempoten_dan_membuat_akun_tiap_role(): void
    {
        $this->seed();
        $jumlah = User::count();
        $this->seed();

        $this->assertSame($jumlah, User::count());
        $this->assertSame(1, User::denganPeran('admin')->count());
        // satu akun per peran (guru juga dipegang akun wali kelas)
        foreach (['kepsek', 'wali_kelas', 'siswa', 'orang_tua', 'bendahara'] as $peran) {
            $this->assertSame(1, User::denganPeran($peran)->count(), $peran);
        }
        $this->assertSame(2, User::denganPeran('guru')->count());
    }

    #[DataProvider('akun')]
    public function test_akun_seeder_dapat_login_dan_membuka_halamannya(string $username, string $role, array $halaman): void
    {
        $this->seed();

        $this->post('/login', ['username' => $username, 'password' => 'password'])
            ->assertRedirect(route("$role/dashboard"));

        foreach ($halaman as $url) {
            $this->get($url)->assertOk();
        }
    }

    public static function akun(): array
    {
        return [
            'admin' => ['admin', 'admin', [
                '/admin/dashboard', '/admin/profile', '/admin/guru', '/admin/siswa', '/admin/kelas',
                '/admin/berita', '/admin/pengaturan', '/admin/gallery',
            ]],
            'guru' => ['guru', 'guru', [
                '/guru/dashboard', '/guru/profile', '/guru/jadwal', '/guru/absensi',
            ]],
            'siswa' => ['siswa', 'siswa', [
                '/siswa/dashboard', '/siswa/profile', '/siswa/kelas', '/siswa/jadwal',
            ]],
        ];
    }

    public function test_halaman_publik_menampilkan_data_seeder(): void
    {
        $this->seed();

        foreach (['/', '/berita', '/pengumuman', '/agenda', '/galeri', '/profil'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/berita/siswa-smpn-1-muaro-jambi-juara-olimpiade-sains')->assertOk();
    }

    public function test_seeder_situs_mengisi_foto_dan_profil_yang_bisa_diedit_admin(): void
    {
        $this->seed();

        $this->assertFileExists(public_path('storage/situs/demo-hero.jpg'));
        $this->assertSame('storage/situs/demo-hero.jpg', \App\Setting::get('hero_gambar'));
        $this->assertSame(8, \App\Pengaturan::whereNotNull('judul')->count());
        $this->assertStringContainsString('berdiri sejak 1985', \App\Pengaturan::find(1)->deskripsi);
        $this->assertSame(8, \App\Gallery::count());
        $this->assertSame(2, \App\Testimoni::whereNotNull('foto')->count());
        $this->assertNotNull(\App\Berita::orderBy('id')->first()->berita_gambar);
        $this->get('/')->assertOk()->assertSee('demo-hero.jpg')->assertSee('Nabila Putri');

        // Admin bisa mengubah profil; seeder ulang tidak menimpa hasil editan.
        $admin = User::denganPeran('admin')->first();
        $this->actingAs($admin)->putJson('/admin/pengaturan/1', ['judul' => 'Tentang Sekolah Kami', 'deskripsi' => '<p>Teks baru</p>'])->assertOk();
        $this->seed();
        $this->assertSame('Tentang Sekolah Kami', \App\Pengaturan::find(1)->judul);
    }

    public function test_admin_mengunggah_dan_mengganti_foto_testimoni(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/testimoni', [
            'nama' => 'Dina', 'isi' => 'Bagus sekali.', 'aktif' => 1, 'foto' => \Illuminate\Http\UploadedFile::fake()->image('a.jpg', 200, 200),
        ], ['Accept' => 'application/json'])->assertCreated();

        $t = \App\Testimoni::firstOrFail();
        $lama = $t->foto;
        $this->assertFileExists(public_path('storage/testimoni/'.$lama));

        $this->actingAs($admin)->post('/admin/testimoni/'.$t->id, [
            '_method' => 'PUT', 'nama' => 'Dina', 'isi' => 'Bagus.', 'foto' => \Illuminate\Http\UploadedFile::fake()->image('b.jpg', 200, 200),
        ], ['Accept' => 'application/json'])->assertOk();

        $baru = $t->fresh()->foto;
        $this->assertNotSame($lama, $baru);
        $this->assertFileDoesNotExist(public_path('storage/testimoni/'.$lama));

        $this->actingAs($admin)->deleteJson('/admin/testimoni/'.$t->id)->assertOk();
        $this->assertFileDoesNotExist(public_path('storage/testimoni/'.$baru));
    }
}
