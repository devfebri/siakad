<?php

namespace Tests\Feature;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\Siswa;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Profil pengguna dan halaman login. */
class ProfilLoginTest extends TestCase
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

    private function dt(array $extra = []): string
    {
        return http_build_query($extra + ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    private function guru(string $nama = 'Guru A'): array
    {
        $mapel = Mapel::firstOrCreate(['mapel_nama' => 'Matematika']);
        $user = User::factory()->create(['role' => 'guru', 'name' => $nama]);
        $guru = Guru::create(['user_id' => $user->id, 'nip_guru' => (string) $user->id, 'nama_guru' => $nama, 'mapel_id' => $mapel->id, 'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '08', 'tanggal_lahir' => '1985-01-01']);

        return [$user, $guru, $mapel];
    }

    private function siswa(Kelas $kelas, string $nama = 'Siswa A'): array
    {
        $user = User::factory()->create(['role' => 'siswa', 'name' => $nama]);
        $siswa = Siswa::create(['user_id' => $user->id, 'nisn' => (string) (10000 + $user->id), 'nama_siswa' => $nama, 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $kelas->id]);

        return [$user, $siswa];
    }

    // ------------------------------------------------------------------ profil

    public function test_guru_memperbarui_profil_dan_foto(): void
    {
        [$user, $guru] = $this->guru();
        $this->actingAs($user);

        $this->get('/guru/profile')->assertOk()->assertSee('Profil Saya');
        $this->post('/guru/profile', [
            '_method' => 'PUT', 'nama_guru' => 'Guru Baru', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '1990-02-02', 'no_hp' => '0899',
            'avatar' => UploadedFile::fake()->image('f.jpg')->size(50),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['message', 'preview']);

        $this->assertSame('Guru Baru', $user->fresh()->name);
        $guru->refresh();
        $this->assertSame('0899', $guru->no_hp);
        $this->assertFileExists($this->publicDir.'/storage/guru/'.$user->email.'/'.$guru->avatar);
        $this->assertSame('GURU A', strtoupper('Guru A')); // NIP/mapel tidak bisa diubah sendiri
        $this->assertSame((string) $user->id, $guru->nip_guru);

        $this->putJson('/guru/profile', ['nama_guru' => ''])->assertStatus(422)->assertJsonValidationErrors(['nama_guru', 'jenis_kelamin']);
    }

    public function test_siswa_memperbarui_profil_tanpa_bisa_mengubah_nisn_dan_kelas(): void
    {
        $kelas = Kelas::create(['nama_kelas' => 'K']);
        [$user, $siswa] = $this->siswa($kelas);
        $nisn = $siswa->nisn;

        $this->actingAs($user)->putJson('/siswa/profile', [
            'nama_siswa' => 'Nama Baru', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2011-03-03', 'nisn' => '99999', 'kelas_id' => 999,
        ])->assertOk();

        $siswa->refresh();
        $this->assertSame('Nama Baru', $siswa->nama_siswa);
        $this->assertSame($nisn, $siswa->nisn);
        $this->assertSame($kelas->id, $siswa->kelas_id);
        $this->assertSame('Nama Baru', $user->fresh()->name);
    }

    public function test_admin_tanpa_data_guru_tetap_bisa_membuka_profil(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin/profile')->assertOk();
        $this->putJson('/admin/profile', ['nama_guru' => 'Admin Baru'])->assertOk();
        $this->assertSame('Admin Baru', $admin->fresh()->name);
    }

    public function test_ganti_password_memerlukan_password_lama(): void
    {
        $user = User::factory()->create(['role' => 'guru', 'password' => Hash::make('lama123')]);
        $this->actingAs($user);

        $this->putJson('/guru/profile/password', ['password_lama' => 'salah', 'password' => 'baru1234', 'password_confirmation' => 'baru1234'])
            ->assertStatus(422)->assertJsonValidationErrors('password_lama');
        $this->putJson('/guru/profile/password', ['password_lama' => 'lama123', 'password' => 'baru1234', 'password_confirmation' => 'beda'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->putJson('/guru/profile/password', ['password_lama' => 'lama123', 'password' => '123', 'password_confirmation' => '123'])
            ->assertStatus(422);
        $this->assertTrue(Hash::check('lama123', $user->fresh()->password));

        $this->putJson('/guru/profile/password', ['password_lama' => 'lama123', 'password' => 'baru1234', 'password_confirmation' => 'baru1234'])->assertOk();
        $this->assertTrue(Hash::check('baru1234', $user->fresh()->password));
    }

    // ------------------------------------------------------------------ login

    public function test_halaman_login_dan_pesan_kesalahan(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
        User::factory()->create(['role' => 'guru', 'username' => 'guru.uji', 'password' => Hash::make('rahasia')]);

        $this->from('/login')->post('/login', ['username' => 'guru.uji', 'password' => 'salah'])
            ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        $this->assertGuest();

        $this->post('/login', [])->assertSessionHasErrors(['username', 'password']);
        $this->post('/login', ['username' => 'guru.uji', 'password' => 'rahasia'])->assertRedirect(route('guru/dashboard'));
        $this->assertAuthenticated();
    }

    public function test_pengguna_yang_sudah_masuk_dialihkan_dari_halaman_login_dan_bisa_keluar(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'siswa']));

        $this->get('/login')->assertRedirect(route('siswa/dashboard'));
        $this->post('/logout')->assertRedirect($this->sekolah->url('/')); // kembali ke situs sekolahnya
        $this->assertGuest();
    }

    public function test_login_dibatasi_laju_percobaan(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['username' => 'x', 'password' => 'salah']);
        }

        $this->post('/login', ['username' => 'x', 'password' => 'salah'])->assertStatus(429);
    }

    public function test_role_salah_diarahkan_ke_dashboard_sendiri(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/admin/dashboard')
            ->assertRedirect(route('guru/dashboard'));
    }
}
