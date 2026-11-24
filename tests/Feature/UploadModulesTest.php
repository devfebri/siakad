<?php

namespace Tests\Feature;

use App\Gallery;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Galeri: unggah/unduh berkas. Berkas ditulis ke folder public sementara, bukan public/ asli. */
class UploadModulesTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/smpn-test-public-'.uniqid();
        File::makeDirectory($this->publicDir, 0777, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function dt(): string
    {
        return http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    // ------------------------------------------------------------------ galeri

    public function test_galeri_unggah_tampil_dan_hapus(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post('/admin/gallery', ['foto' => UploadedFile::fake()->image('kegiatan.jpg', 600, 400)], ['Accept' => 'application/json'])
            ->assertCreated();

        $foto = Gallery::firstOrFail();
        $this->assertSame($admin->name, $foto->author);
        $this->assertFileExists($this->publicDir.'/storage/gallery/'.$foto->foto);

        $this->getJson('/admin/gallery/data?'.$this->dt())
            ->assertJsonPath('data.0.id', $foto->id)
            ->assertJsonPath('data.0.url', asset('storage/gallery/'.$foto->foto));

        $this->deleteJson("/admin/gallery/{$foto->id}")->assertOk();
        $this->assertFileDoesNotExist($this->publicDir.'/storage/gallery/'.$foto->foto);
        $this->assertDatabaseCount('gallery', 0);
    }

    public function test_galeri_menolak_berkas_bukan_gambar_dan_terlalu_besar(): void
    {
        $this->actingAs($this->user('admin'));

        $this->postJson('/admin/gallery', ['foto' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php')])
            ->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->postJson('/admin/gallery', ['foto' => UploadedFile::fake()->image('besar.jpg')->size(3000)])
            ->assertStatus(422)->assertJsonValidationErrors('foto');
        $this->postJson('/admin/gallery', [])->assertStatus(422);
        $this->assertDatabaseCount('gallery', 0);
    }

    public function test_galeri_tidak_punya_route_ubah(): void
    {
        $this->actingAs($this->user('admin'))->putJson('/admin/gallery/1', [])->assertStatus(405);
    }
}
