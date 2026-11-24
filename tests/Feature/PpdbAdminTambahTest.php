<?php

namespace Tests\Feature;

use App\PpdbPendaftar;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PpdbAdminTambahTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->publicDir = sys_get_temp_dir().'/smpn-ppdb-public-'.uniqid();
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

    private function payload(array $extra = []): array
    {
        return $extra + [
            'nama' => 'Siti Rahma', 'jenis_kelamin' => 'Perempuan', 'tempat_lahir' => 'Jambi', 'tanggal_lahir' => '2010-05-10',
            'nisn' => '1234567890', 'asal_sekolah' => 'SMP Negeri 2', 'alamat' => 'Jl. Melati No. 5', 'nama_ortu' => 'Budi Rahman',
            'telepon' => '081234567890', 'jalur' => 'Reguler', 'peminatan' => 'IPA', 'nilai_rata' => 85.5,
        ];
    }

    public function test_tombol_dan_formulir_tambah_pendaftar_tampil_untuk_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/ppdb')->assertOk()->assertSee('Tambah Pendaftar')->assertSee(route('admin/ppdb.create'));
        $this->get('/admin/ppdb/create')->assertOk()->assertSee('Simpan Pendaftar')->assertSee('Nama lengkap')->assertSee('Status awal');
    }

    public function test_admin_menambah_pendaftar_dan_diarahkan_ke_detail(): void
    {
        $admin = $this->admin();
        $res = $this->actingAs($admin)->postJson('/admin/ppdb', $this->payload(['catatan' => 'Datang langsung']))->assertCreated();

        $p = PpdbPendaftar::firstOrFail();
        $this->assertSame('Siti Rahma', $p->nama);
        $this->assertSame('menunggu', $p->status);
        $this->assertSame('Datang langsung', $p->catatan);
        $this->assertMatchesRegularExpression('/^PPDB-\d{4}-\d{4}$/', $p->no_pendaftaran);
        $this->assertSame(route('admin/ppdb.show', $p), $res->json('redirect'));
        $this->assertStringContainsString($p->no_pendaftaran, $res->json('message'));

        $this->get($res->json('redirect'))->assertOk()->assertSee('Siti Rahma');
        $this->get('/admin/ppdb')->assertOk();
    }

    public function test_admin_bisa_menambah_walau_pendaftaran_online_ditutup_dan_mengatur_status_awal(): void
    {
        \App\Setting::put(['ppdb_aktif' => '0']);

        $this->actingAs($this->admin())->postJson('/admin/ppdb', $this->payload(['status' => 'diterima']))->assertCreated();
        $this->assertSame('diterima', PpdbPendaftar::firstOrFail()->status);

        // formulir publik tetap tertutup
        auth()->logout();
        $this->post('/ppdb', $this->payload())->assertRedirect();
        $this->assertSame(1, PpdbPendaftar::count());
    }

    public function test_berkas_pendukung_disimpan(): void
    {
        $this->actingAs($this->admin())->post('/admin/ppdb', $this->payload(['berkas' => UploadedFile::fake()->create('akte.pdf', 100, 'application/pdf')]), ['Accept' => 'application/json'])->assertCreated();

        $p = PpdbPendaftar::firstOrFail();
        $this->assertStringStartsWith('storage/ppdb/', $p->berkas);
        $this->assertFileExists($this->publicDir.'/'.$p->berkas);
    }

    public function test_validasi_data_pendaftar(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/ppdb', [])->assertStatus(422)->assertJsonValidationErrors(['nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'asal_sekolah', 'alamat', 'nama_ortu', 'telepon', 'jalur']);
        $this->postJson('/admin/ppdb', $this->payload(['nisn' => 'abc']))->assertStatus(422)->assertJsonValidationErrors('nisn');
        $this->postJson('/admin/ppdb', $this->payload(['tanggal_lahir' => now()->addDay()->toDateString()]))->assertStatus(422)->assertJsonValidationErrors('tanggal_lahir');
        $this->postJson('/admin/ppdb', $this->payload(['jalur' => 'Jalur Palsu']))->assertStatus(422)->assertJsonValidationErrors('jalur');
        $this->postJson('/admin/ppdb', $this->payload(['status' => 'aneh']))->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson('/admin/ppdb', $this->payload(['nilai_rata' => 150]))->assertStatus(422)->assertJsonValidationErrors('nilai_rata');
        $this->post('/admin/ppdb', $this->payload(['berkas' => UploadedFile::fake()->create('virus.exe', 10)]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->assertSame(0, PpdbPendaftar::count());
    }

    public function test_hanya_admin_yang_bisa_menambah(): void
    {
        $this->get('/admin/ppdb/create')->assertRedirect();
        $this->postJson('/admin/ppdb', $this->payload())->assertUnauthorized();

        foreach (['guru', 'siswa'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/admin/ppdb/create')->assertRedirect();
            $this->postJson('/admin/ppdb', $this->payload())->assertForbidden();
        }
        $this->assertSame(0, PpdbPendaftar::count());
    }
}
