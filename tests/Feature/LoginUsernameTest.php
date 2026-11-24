<?php

namespace Tests\Feature;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\Siswa;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginUsernameTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->publicDir = sys_get_temp_dir().'/smpn-user-public-'.uniqid();
        File::makeDirectory($this->publicDir, 0777, true);
        $this->app->usePublicPath($this->publicDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        parent::tearDown();
    }

    private function user(array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => 'guru', 'username' => 'pak.budi', 'email' => 'budi@sekolah.test', 'password' => Hash::make('rahasia')]);
    }

    // ------------------------------------------------------------ halaman & proses login

    public function test_form_login_memakai_username_bukan_email(): void
    {
        $this->get('/login')->assertOk()->assertSee('Username')->assertSee('name="username"', false)->assertDontSee('name="email"', false)->assertDontSee('type="email"', false);
    }

    public function test_login_dengan_username(): void
    {
        $this->user();

        $this->post('/login', ['username' => 'pak.budi', 'password' => 'rahasia'])->assertRedirect(route('guru/dashboard'));
        $this->assertAuthenticated();
    }

    public function test_email_tidak_bisa_dipakai_untuk_login(): void
    {
        $this->user();

        $this->from('/login')->post('/login', ['username' => 'budi@sekolah.test', 'password' => 'rahasia'])
            ->assertRedirect('/login')->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        $this->assertGuest();
        $this->post('/login', ['email' => 'budi@sekolah.test', 'password' => 'rahasia'])->assertSessionHasErrors('username');
    }

    public function test_username_tidak_peka_huruf_besar_dan_spasi(): void
    {
        $this->user();

        $this->post('/login', ['username' => '  Pak.BUDI ', 'password' => 'rahasia'])->assertRedirect(route('guru/dashboard'));
        $this->assertAuthenticated();
    }

    public function test_password_salah_dan_username_tidak_ada_sama_pesannya(): void
    {
        $this->user();

        $this->from('/login')->post('/login', ['username' => 'pak.budi', 'password' => 'salah'])->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        $this->from('/login')->post('/login', ['username' => 'tidak.ada', 'password' => 'rahasia'])->assertSessionHasErrors(['username' => 'Username atau password salah.']);
        $this->post('/login', ['username' => str_repeat('a', 51), 'password' => 'x'])->assertSessionHasErrors('username');
        $this->post('/login', [])->assertSessionHasErrors(['username', 'password']);
        $this->assertGuest();
    }

    public function test_username_yang_diketik_tetap_terisi_setelah_gagal(): void
    {
        $this->user();

        $this->from('/login')->post('/login', ['username' => 'pak.budi', 'password' => 'salah']);
        $this->get('/login')->assertSee('value="pak.budi"', false);
    }

    // ------------------------------------------------------------ pembuatan username

    public function test_akun_tanpa_username_mendapat_username_otomatis_yang_unik(): void
    {
        $a = User::factory()->create(['username' => null, 'email' => 'Siti.Aminah@a.test']);
        $b = User::factory()->create(['username' => null, 'email' => 'siti.aminah@b.test']);
        $c = User::factory()->create(['username' => null, 'email' => 'siti.aminah@c.test']);

        $this->assertSame('siti.aminah', $a->username);
        $this->assertSame('siti.aminah2', $b->username);
        $this->assertSame('siti.aminah3', $c->username);
    }

    public function test_pembersihan_dan_username_unik(): void
    {
        $this->assertSame('budisantoso', User::bersihkan(' Budi Santoso! '));
        $this->assertSame('a.b_c-d', User::bersihkan('A.B_C-D'));
        $this->assertSame('', User::bersihkan('***'));
        $this->assertSame('usera', User::usernameUnik('a'), 'terlalu pendek diberi awalan');
        $this->assertSame('user', User::usernameUnik('***'));

        $this->user(['username' => 'dewi']);
        $this->assertSame('dewi2', User::usernameUnik('Dewi'));
        $this->assertSame('dewi', User::usernameUnik('Dewi', User::where('username', 'dewi')->value('id')), 'milik sendiri diabaikan');
    }

    public function test_username_selalu_disimpan_huruf_kecil(): void
    {
        $u = User::factory()->create(['username' => ' ADMIN.Sekolah ']);

        $this->assertSame('admin.sekolah', $u->fresh()->username);
    }

    // ------------------------------------------------------------ admin: pengguna

    public function test_admin_membuat_dan_mengubah_username_pengguna(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'root']);
        $this->actingAs($admin);

        $this->postJson('/admin/pengguna', ['name' => 'Admin Dua', 'username' => 'Admin.Dua', 'email' => 'dua@x.test', 'password' => 'rahasia1'])->assertCreated();
        $dua = User::where('email', 'dua@x.test')->firstOrFail();
        $this->assertSame('admin.dua', $dua->username);

        $this->postJson('/admin/pengguna', ['name' => 'X', 'username' => 'admin.dua', 'email' => 'x@x.test', 'password' => 'rahasia1'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->postJson('/admin/pengguna', ['name' => 'X', 'username' => 'ab', 'email' => 'x@x.test', 'password' => 'rahasia1'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->postJson('/admin/pengguna', ['name' => 'X', 'username' => 'ada spasi', 'email' => 'x@x.test', 'password' => 'rahasia1'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->postJson('/admin/pengguna', ['name' => 'X', 'email' => 'x@x.test', 'password' => 'rahasia1'])->assertStatus(422)->assertJsonValidationErrors('username');

        $this->putJson("/admin/pengguna/{$dua->id}", ['name' => 'Admin Dua', 'username' => 'dua.baru', 'email' => 'dua@x.test'])->assertOk();
        $this->assertSame('dua.baru', $dua->fresh()->username);
        $this->putJson("/admin/pengguna/{$dua->id}", ['name' => 'Admin Dua', 'username' => 'root', 'email' => 'dua@x.test'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->putJson("/admin/pengguna/{$dua->id}", ['name' => 'Admin Dua', 'username' => 'dua.baru', 'email' => 'dua@x.test'])->assertOk(); // username sendiri tidak dianggap bentrok

        $this->getJson('/admin/pengguna/data?draw=1&start=0&length=10&search[value]=dua.baru&columns[0][data]=&order[0][column]=0&order[0][dir]=asc')
            ->assertOk()->assertJsonPath('data.0.username', 'dua.baru');

        auth()->logout();
        $this->post('/login', ['username' => 'dua.baru', 'password' => 'rahasia1'])->assertRedirect(route('admin/dashboard'));
    }

    // ------------------------------------------------------------ admin: guru & siswa

    private function guruPayload(array $extra = []): array
    {
        $mapel = Mapel::firstOrCreate(['mapel_nama' => 'Matematika']);

        return $extra + ['nip_guru' => '198001012005', 'nama_guru' => 'Budi', 'email' => 'budi@sekolah.test', 'mapel_id' => $mapel->id, 'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '0812', 'tanggal_lahir' => '1980-01-01'];
    }

    public function test_guru_baru_memakai_nip_sebagai_username_bawaan_atau_username_pilihan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/admin/guru', $this->guruPayload())->assertCreated();
        $this->assertSame('198001012005', User::where('email', 'budi@sekolah.test')->value('username'));

        $this->postJson('/admin/guru', $this->guruPayload(['nip_guru' => '199001012010', 'email' => 'ani@sekolah.test', 'username' => 'Bu.Ani']))->assertCreated();
        $this->assertSame('bu.ani', User::where('email', 'ani@sekolah.test')->value('username'));

        $this->postJson('/admin/guru', $this->guruPayload(['nip_guru' => '199101012010', 'email' => 'cici@sekolah.test', 'username' => 'bu.ani']))->assertStatus(422)->assertJsonValidationErrors('username');
        $this->postJson('/admin/guru', $this->guruPayload(['nip_guru' => '199101012010', 'email' => 'cici@sekolah.test', 'username' => 'Bu Cici']))->assertStatus(422)->assertJsonValidationErrors('username');

        auth()->logout();
        $this->post('/login', ['username' => 'bu.ani', 'password' => 'guru123'])->assertRedirect(route('guru/dashboard'));
        auth()->logout();
        $this->post('/login', ['username' => '198001012005', 'password' => 'guru123'])->assertRedirect(route('guru/dashboard'));
    }

    public function test_admin_mengubah_username_guru_lewat_form_edit(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/admin/guru', $this->guruPayload())->assertCreated();
        $guru = Guru::firstOrFail();

        $this->putJson("/admin/guru/{$guru->id}", $this->guruPayload(['username' => 'pak.budi']))->assertOk();
        $this->assertSame('pak.budi', $guru->user->fresh()->username);

        // dikosongkan saat edit = username tetap
        $this->putJson("/admin/guru/{$guru->id}", $this->guruPayload(['username' => '']))->assertOk();
        $this->assertSame('pak.budi', $guru->user->fresh()->username);
    }

    public function test_siswa_baru_memakai_nisn_sebagai_username_bawaan(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $kelas = Kelas::create(['nama_kelas' => 'VII A']);
        $payload = ['nisn' => '0012345678', 'nama_siswa' => 'Andi', 'email' => 'andi@sekolah.test', 'kelas_id' => $kelas->id, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01'];

        $this->postJson('/admin/siswa', $payload)->assertCreated();
        $this->assertSame('0012345678', User::where('email', 'andi@sekolah.test')->value('username'));

        $this->postJson('/admin/siswa', ['nisn' => '0099999999', 'email' => 'budi2@sekolah.test', 'username' => '0012345678'] + $payload)->assertStatus(422)->assertJsonValidationErrors('username');

        auth()->logout();
        $this->post('/login', ['username' => '0012345678', 'password' => 'siswa123'])->assertRedirect(route('siswa/dashboard'));
        $this->assertSame(1, Siswa::count());
    }

    public function test_daftar_guru_siswa_dan_form_menampilkan_username(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/admin/guru', $this->guruPayload(['username' => 'pak.budi']))->assertCreated();

        $this->get('/admin/guru')->assertOk()->assertSee('Username (untuk login)')->assertSee('data-col="username"', false);
        $this->get('/admin/siswa')->assertOk()->assertSee('Username (untuk login)');
        $this->get('/admin/pengguna')->assertOk()->assertSee('Username (untuk login)');
        $this->getJson('/admin/guru/data?draw=1&start=0&length=10&search[value]=pak.budi&columns[0][data]=&order[0][column]=0&order[0][dir]=asc')
            ->assertOk()->assertJsonPath('data.0.username', 'pak.budi');
    }
}
