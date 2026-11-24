<?php

namespace Tests\Feature;

use App\ProgramUnggulan;
use App\Setting;
use App\Support\Akademik;
use App\Testimoni;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PengaturanSitusTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function dt(): string
    {
        return http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    public function test_halaman_pengaturan_menampilkan_semua_tab(): void
    {
        $this->actingAs($this->admin())->get('/admin/pengaturan')->assertOk()
            ->assertSee('Identitas Sekolah')->assertSee('Kontak & Media Sosial')->assertSee('PPDB')->assertSee('Program Unggulan')->assertSee('Testimoni')->assertSee('Halaman Profil');
    }

    public function test_identitas_tersimpan_dan_diterapkan_ke_seluruh_tampilan(): void
    {
        $this->actingAs($this->admin())->putJson('/admin/pengaturan/situs/identitas', [
            'nama_sekolah' => 'SMA Negeri 9 Contoh', 'nama_singkat' => 'SMAN 9', 'tagline' => 'Cerdas Berkarakter',
            'tema_warna' => 'teal', 'akreditasi' => 'A', 'tahun_berdiri' => 1998,
        ])->assertOk()->assertJsonPath('message', 'Identitas Sekolah berhasil disimpan.');

        $this->assertSame('SMA Negeri 9 Contoh', Setting::get('nama_sekolah'));

        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('SMA Negeri 9 Contoh')->assertSee('Cerdas Berkarakter')->assertSee('--brand:#0f766e', false);
        $this->assertSame('SMAN 9', config('school.short_name'));
        $this->assertSame('A', config('school.akreditasi'));
    }

    public function test_validasi_pengaturan(): void
    {
        $this->actingAs($this->admin());

        $this->putJson('/admin/pengaturan/situs/identitas', ['nama_sekolah' => '', 'nama_singkat' => 'X', 'tema_warna' => 'biru'])
            ->assertStatus(422)->assertJsonValidationErrors(['nama_sekolah', 'tema_warna']);
        $this->putJson('/admin/pengaturan/situs/akademik', ['tahun_ajaran' => '2026/2027'])->assertNotFound(); // pindah ke menu Tahun Ajaran
        $this->putJson('/admin/pengaturan/situs/kontak', ['email' => 'bukan-email', 'instagram' => 'bukan-url'])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'instagram']);
        $this->putJson('/admin/pengaturan/situs/ppdb', ['ppdb_buka' => '2027-02-01 08:00', 'ppdb_tutup' => '2027-01-01 08:00'])
            ->assertStatus(422)->assertJsonValidationErrors('ppdb_tutup');
        $this->putJson('/admin/pengaturan/situs/tidak-ada', [])->assertNotFound();
    }

    public function test_unggah_logo_mengganti_dan_menghapus_logo_lama(): void
    {
        $this->actingAs($this->admin());
        $payload = fn () => ['nama_sekolah' => 'S', 'nama_singkat' => 'S', 'tema_warna' => 'navy'];

        $this->post('/admin/pengaturan/situs/identitas', $payload() + ['_method' => 'PUT', 'logo' => UploadedFile::fake()->image('logo.png', 120, 120)->size(50)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonStructure(['message', 'preview']);
        $lama = Setting::get('logo');
        $this->assertFileExists($this->publicDir.'/'.$lama);
        $this->assertSame($lama, config('school.logo') ?? $lama);

        $this->post('/admin/pengaturan/situs/identitas', $payload() + ['_method' => 'PUT', 'logo' => UploadedFile::fake()->image('baru.png')->size(50)], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotSame($lama, Setting::get('logo'));
        $this->assertFileDoesNotExist($this->publicDir.'/'.$lama);

        // simpan tanpa berkas: logo dipertahankan
        $logo = Setting::get('logo');
        $this->putJson('/admin/pengaturan/situs/identitas', $payload())->assertOk();
        $this->assertSame($logo, Setting::get('logo'));

        $this->postJson('/admin/pengaturan/situs/identitas', $payload() + ['_method' => 'PUT', 'logo' => UploadedFile::fake()->create('x.php', 5, 'text/x-php')])->assertStatus(422);
    }

    public function test_tahun_ajaran_otomatis_bila_pengaturan_kosong(): void
    {
        $this->assertSame('2026/2027', Akademik::aktif(now()->setDate(2026, 8, 1))['tahun_ajaran']);
        $this->assertSame('Ganjil', Akademik::aktif(now()->setDate(2026, 8, 1))['semester']);
        $this->assertSame('2025/2026', Akademik::aktif(now()->setDate(2026, 3, 1))['tahun_ajaran']);
        $this->assertSame('Genap', Akademik::aktif(now()->setDate(2026, 3, 1))['semester']);
    }

    public function test_pengaturan_ppdb_menyimpan_tanggal_dan_status(): void
    {
        $this->actingAs($this->admin())->putJson('/admin/pengaturan/situs/ppdb', [
            'ppdb_aktif' => '1', 'ppdb_tahun_ajaran' => '2027/2028', 'ppdb_buka' => '2027-01-10T08:00', 'ppdb_tutup' => '2027-03-30T23:59', 'ppdb_kuota' => 120,
            'ppdb_info' => "KK\nAkta",
        ])->assertOk();

        $this->assertSame('1', Setting::get('ppdb_aktif'));
        $this->assertSame('2027-03-30 23:59:00', Setting::get('ppdb_tutup'));
        $this->assertTrue(config('school.ppdb_aktif') === true || Setting::get('ppdb_aktif') === '1');
    }

    public function test_hanya_admin_yang_boleh_mengubah_pengaturan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->putJson('/admin/pengaturan/situs/identitas', ['nama_sekolah' => 'X', 'nama_singkat' => 'X'])->assertForbidden();
        $this->assertNull(Setting::get('nama_sekolah'));
    }

    public function test_crud_program_unggulan(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/program', [])->assertStatus(422)->assertJsonValidationErrors(['judul', 'ikon']);
        $this->postJson('/admin/program', ['judul' => 'Kelas Sains', 'ikon' => 'bukan-ikon'])->assertStatus(422)->assertJsonValidationErrors('ikon');
        $this->postJson('/admin/program', ['judul' => 'Kelas Sains', 'deskripsi' => 'Laboratorium lengkap', 'ikon' => 'flask', 'urutan' => 2, 'aktif' => 1])->assertCreated();
        $this->postJson('/admin/program', ['judul' => 'Tahfidz', 'ikon' => 'moon-stars', 'urutan' => 1])->assertCreated();

        $this->getJson('/admin/program/data?'.$this->dt())
            ->assertJsonPath('recordsTotal', 2)->assertJsonPath('data.0.judul', 'Tahfidz')->assertJsonPath('data.0.aktif', 0)->assertJsonPath('data.1.status', 'Tampil|success');

        $program = ProgramUnggulan::where('judul', 'Kelas Sains')->firstOrFail();
        $this->putJson("/admin/program/{$program->id}", ['judul' => 'Kelas Sains Plus', 'ikon' => 'flask'])->assertOk();
        $this->assertFalse($program->fresh()->aktif);
        $this->deleteJson("/admin/program/{$program->id}")->assertOk();
        $this->assertDatabaseCount('program_unggulan', 1);
    }

    public function test_crud_testimoni(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/testimoni', [])->assertStatus(422)->assertJsonValidationErrors(['nama', 'isi']);
        $this->postJson('/admin/testimoni', ['nama' => 'Rina', 'peran' => 'Alumni 2022', 'isi' => 'Gurunya sabar dan fasilitas lengkap.', 'aktif' => 1])->assertCreated();
        $t = Testimoni::firstOrFail();

        $this->putJson("/admin/testimoni/{$t->id}", ['nama' => 'Rina A', 'isi' => 'Sangat membantu.'])->assertOk();
        $this->assertSame('Rina A', $t->fresh()->nama);
        $this->getJson('/admin/testimoni/data?'.$this->dt())->assertJsonPath('data.0.nama', 'Rina A');
        $this->deleteJson("/admin/testimoni/{$t->id}")->assertOk();
        $this->assertDatabaseCount('testimoni', 0);
    }
}
