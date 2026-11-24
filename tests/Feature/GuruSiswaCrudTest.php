<?php

namespace Tests\Feature;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\Siswa;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruSiswaCrudTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/smpn-test-public-'.uniqid();
        File::makeDirectory($this->publicDir, 0777, true);
        $this->app->usePublicPath($this->publicDir);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
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

    private function guruPayload(array $extra = []): array
    {
        $mapel = Mapel::firstOrCreate(['mapel_nama' => 'Matematika']);

        return $extra + [
            'nip_guru' => '198001012005', 'nama_guru' => 'Budi', 'email' => 'budi@sekolah.test', 'mapel_id' => $mapel->id,
            'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '0812', 'tanggal_lahir' => '1985-05-05',
        ];
    }

    private function siswaPayload(array $extra = []): array
    {
        $kelas = Kelas::firstOrCreate(['nama_kelas' => 'VII A']);

        return $extra + [
            'nisn' => '0012345', 'nama_siswa' => 'Andi', 'email' => 'andi@sekolah.test', 'kelas_id' => $kelas->id,
            'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-02-02',
        ];
    }

    // ------------------------------------------------------------------ guru

    public function test_tambah_guru_membuat_akun_dengan_password_awal_dan_foto(): void
    {
        $this->post('/admin/guru', $this->guruPayload(['avatar' => UploadedFile::fake()->image('foto.jpg', 200, 200)->size(100)]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'guru123'));

        $user = User::where('email', 'budi@sekolah.test')->firstOrFail();
        $this->assertSame('guru', $user->role);
        $this->assertTrue(Hash::check('guru123', $user->password));

        $guru = Guru::firstOrFail();
        $this->assertSame($user->id, $guru->user_id);
        $this->assertFileExists($this->publicDir.'/storage/guru/budi@sekolah.test/'.$guru->avatar);

        // login dengan password awal berhasil
        auth()->logout();
        $this->post('/login', ['username' => $guru->nip_guru, 'password' => 'guru123'])->assertRedirect(route('guru/dashboard'));
    }

    public function test_validasi_guru(): void
    {
        $this->postJson('/admin/guru', [])->assertStatus(422)->assertJsonValidationErrors(['nip_guru', 'nama_guru', 'email', 'mapel_id', 'jenis_kelamin']);
        $this->postJson('/admin/guru', $this->guruPayload(['email' => 'bukan-email']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/admin/guru', $this->guruPayload(['email' => 'a/../b@x.test']))->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/admin/guru', $this->guruPayload(['tanggal_lahir' => '2999-01-01']))->assertStatus(422)->assertJsonValidationErrors('tanggal_lahir');
        $this->postJson('/admin/guru', $this->guruPayload(['avatar' => UploadedFile::fake()->image('besar.jpg')->size(900)]))->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->postJson('/admin/guru', $this->guruPayload())->assertCreated();
        $this->postJson('/admin/guru', $this->guruPayload())->assertStatus(422)->assertJsonValidationErrors(['nip_guru', 'email']);
        $this->assertDatabaseCount('guru', 1);
    }

    public function test_ubah_guru_menyinkronkan_nama_akun_dan_mengganti_password_opsional(): void
    {
        $this->postJson('/admin/guru', $this->guruPayload())->assertCreated();
        $guru = Guru::firstOrFail();

        $this->putJson("/admin/guru/{$guru->id}", $this->guruPayload(['nama_guru' => 'Budi Baru', 'email' => 'diabaikan@x.test']))->assertOk();
        $user = $guru->user()->first();
        $this->assertSame('Budi Baru', $user->name);
        $this->assertSame('budi@sekolah.test', $user->email, 'Email tidak boleh berubah lewat edit.');
        $this->assertTrue(Hash::check('guru123', $user->password));

        $this->putJson("/admin/guru/{$guru->id}", $this->guruPayload(['password' => 'rahasia99']))->assertOk();
        $this->assertTrue(Hash::check('rahasia99', $user->fresh()->password));

        $this->putJson("/admin/guru/{$guru->id}", $this->guruPayload(['password' => '123']))->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_ganti_foto_guru_menghapus_foto_lama(): void
    {
        $this->post('/admin/guru', $this->guruPayload(['avatar' => UploadedFile::fake()->image('a.jpg')->size(50)]), ['Accept' => 'application/json'])->assertCreated();
        $guru = Guru::firstOrFail();
        $lama = $guru->avatar;

        $this->post("/admin/guru/{$guru->id}", $this->guruPayload(['_method' => 'PUT', 'avatar' => UploadedFile::fake()->image('b.png')->size(50)]), ['Accept' => 'application/json'])->assertOk();

        $baru = $guru->fresh()->avatar;
        $this->assertNotSame($lama, $baru);
        $this->assertFileDoesNotExist($this->publicDir.'/storage/guru/budi@sekolah.test/'.$lama);
        $this->assertFileExists($this->publicDir.'/storage/guru/budi@sekolah.test/'.$baru);
    }

    public function test_hapus_guru_menghapus_akun_dan_foto(): void
    {
        $this->post('/admin/guru', $this->guruPayload(['avatar' => UploadedFile::fake()->image('a.jpg')->size(50)]), ['Accept' => 'application/json'])->assertCreated();
        $guru = Guru::firstOrFail();

        $this->deleteJson("/admin/guru/{$guru->id}")->assertOk();

        $this->assertDatabaseCount('guru', 0);
        $this->assertDatabaseMissing('users', ['email' => 'budi@sekolah.test']);
        $this->assertDirectoryDoesNotExist($this->publicDir.'/storage/guru/budi@sekolah.test');
    }

    public function test_guru_yang_masih_punya_jadwal_tidak_bisa_dihapus(): void
    {
        $this->postJson('/admin/guru', $this->guruPayload())->assertCreated();
        $guru = Guru::firstOrFail();
        $kelas = Kelas::create(['nama_kelas' => 'X']);

        DB::table('jadwal')->insert([
            'sekolah_id' => $this->sekolah->id,
            'kelas_id' => $kelas->id, 'mapel_id' => $guru->mapel_id, 'guru_id' => $guru->id, 'hari' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->deleteJson("/admin/guru/{$guru->id}")->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'jadwal'));
        $this->assertDatabaseHas('users', ['id' => $guru->user_id]);

        DB::table('jadwal')->delete();
        $this->deleteJson("/admin/guru/{$guru->id}")->assertOk();
    }

    public function test_akun_admin_dan_diri_sendiri_tidak_bisa_dihapus_lewat_menu_guru(): void
    {
        $admin = auth()->user();
        $mapel = Mapel::create(['mapel_nama' => 'M']);
        $guruAdmin = Guru::create(['user_id' => $admin->id, 'nip_guru' => '1', 'nama_guru' => 'Admin', 'mapel_id' => $mapel->id, 'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '1', 'tanggal_lahir' => '1980-01-01']);

        $this->deleteJson("/admin/guru/{$guruAdmin->id}")->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_pencarian_guru_lewat_email_dan_nama_mapel(): void
    {
        $this->postJson('/admin/guru', $this->guruPayload())->assertCreated();
        $this->postJson('/admin/guru', $this->guruPayload(['nip_guru' => '2', 'nama_guru' => 'Siti', 'email' => 'siti@sekolah.test', 'mapel_id' => Mapel::create(['mapel_nama' => 'Seni Budaya'])->id]))->assertCreated();

        $this->getJson('/admin/guru/data?'.$this->dt(['search' => ['value' => 'siti@']]))->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.nama_guru', 'Siti');
        $this->getJson('/admin/guru/data?'.$this->dt(['search' => ['value' => 'Seni']]))->assertJsonPath('recordsFiltered', 1);
        $this->getJson('/admin/guru/data?'.$this->dt())->assertJsonPath('recordsTotal', 2)->assertJsonPath('data.0.avatar_url', asset('images/default.png'));
    }

    public function test_hanya_admin_yang_boleh_mengelola_guru(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->getJson('/admin/guru/data')->assertForbidden();
        $this->postJson('/admin/guru', $this->guruPayload())->assertForbidden();
        $this->assertDatabaseCount('guru', 0);
    }

    // ------------------------------------------------------------------ siswa

    public function test_tambah_siswa_membuat_akun_dengan_password_awal(): void
    {
        $this->post('/admin/siswa', $this->siswaPayload(['avatar' => UploadedFile::fake()->image('s.jpg')->size(80)]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'siswa123'));

        $user = User::where('email', 'andi@sekolah.test')->firstOrFail();
        $this->assertSame('siswa', $user->role);
        $this->assertTrue(Hash::check('siswa123', $user->password));
        $siswa = Siswa::firstOrFail();
        $this->assertFileExists($this->publicDir.'/storage/siswa/andi@sekolah.test/'.$siswa->avatar);

        auth()->logout();
        $this->post('/login', ['username' => $siswa->nisn, 'password' => 'siswa123'])->assertRedirect(route('siswa/dashboard'));
    }

    public function test_validasi_siswa(): void
    {
        $this->postJson('/admin/siswa', [])->assertStatus(422)->assertJsonValidationErrors(['nisn', 'nama_siswa', 'email', 'kelas_id', 'agama']);
        $this->postJson('/admin/siswa', $this->siswaPayload(['agama' => 'Lainnya']))->assertStatus(422)->assertJsonValidationErrors('agama');
        $this->postJson('/admin/siswa', $this->siswaPayload(['nisn' => '123']))->assertStatus(422)->assertJsonValidationErrors('nisn');
        $this->postJson('/admin/siswa', $this->siswaPayload(['kelas_id' => 999]))->assertStatus(422)->assertJsonValidationErrors('kelas_id');

        $this->postJson('/admin/siswa', $this->siswaPayload())->assertCreated();
        $this->postJson('/admin/siswa', $this->siswaPayload())->assertStatus(422)->assertJsonValidationErrors(['nisn', 'email']);
    }

    public function test_ubah_pindah_kelas_dan_hapus_siswa(): void
    {
        $this->postJson('/admin/siswa', $this->siswaPayload())->assertCreated();
        $siswa = Siswa::firstOrFail();
        $kelasB = Kelas::create(['nama_kelas' => 'VII B']);

        $this->putJson("/admin/siswa/{$siswa->id}", $this->siswaPayload(['nama_siswa' => 'Andi Baru', 'kelas_id' => $kelasB->id]))->assertOk();
        $this->assertSame($kelasB->id, $siswa->fresh()->kelas_id);
        $this->assertSame('Andi Baru', $siswa->user()->first()->name);

        $this->getJson('/admin/siswa/data?'.$this->dt(['search' => ['value' => 'VII B']]))->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.kelas', 'VII B');

        $this->deleteJson("/admin/siswa/{$siswa->id}")->assertOk();
        $this->assertDatabaseCount('siswa', 0);
        $this->assertDatabaseMissing('users', ['email' => 'andi@sekolah.test']);
    }

    public function test_data_lama_dengan_tanggal_lahir_teks_tidak_merusak_tabel(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'K']);
        $user = User::factory()->create(['role' => 'siswa']);
        DB::table('siswa')->insert([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '99999', 'nama_siswa' => 'Lama', 'agama' => 'Islam', 'jenis_kelamin' => 'L', 'tanggal_lahir' => 'bukan tanggal',
            'user_id' => $user->id, 'kelas_id' => $kelas->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/admin/siswa/data?'.$this->dt())->assertOk()->assertJsonPath('data.0.tanggal_lahir', '');
    }
}
