<?php

namespace Tests\Feature;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\Peran;
use App\Siswa;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Peran pengguna (banyak peran per akun), panel, dan CheckRole. */
class PeranTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(array $peran, array $extra = []): User
    {
        $u = User::factory()->create($extra + ['password' => Hash::make('rahasia')]);
        $u->setPeran($peran);

        return $u;
    }

    private function dt(): string
    {
        return http_build_query(['draw' => 1, 'start' => 0, 'length' => 50, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    // ------------------------------------------------------------ tabel dan model

    public function test_delapan_peran_tersedia(): void
    {
        $this->assertEquals(
            ['super_admin', 'admin', 'kepsek', 'guru', 'wali_kelas', 'bendahara', 'siswa', 'orang_tua'],
            Peran::orderBy('id')->pluck('kode')->all(),
        );
        $this->assertFalse(Schema::hasColumn('users', 'role'), 'kolom users.role sudah diganti tabel peran');
        $this->assertTrue(Schema::hasTable('peran_user'));
    }

    public function test_akun_bisa_punya_banyak_peran(): void
    {
        $u = $this->user(['guru', 'wali_kelas']);

        $this->assertTrue($u->punyaPeran('guru'));
        $this->assertTrue($u->punyaPeran('admin', 'wali_kelas'), 'cukup salah satu');
        $this->assertFalse($u->punyaPeran('admin', 'siswa'));
        $this->assertEqualsCanonicalizing(['guru', 'wali_kelas'], $u->kodePeran());

        $u->tambahPeran('bendahara');
        $u->tambahPeran('bendahara'); // tidak ganda
        $this->assertCount(3, $u->fresh()->kodePeran());
        $this->assertSame(3, DB::table('peran_user')->where('user_id', $u->id)->count());

        $u->setPeran(['siswa']);
        $this->assertSame(['siswa'], $u->fresh()->kodePeran());

        $this->assertSame([$u->id], User::denganPeran('siswa')->pluck('id')->all());
        $this->assertSame(0, User::denganPeran('admin')->count());
    }

    public function test_atribut_kompatibilitas_role_menambah_peran(): void
    {
        $u = User::factory()->create(['role' => 'guru']);
        $this->assertSame(['guru'], $u->kodePeran());

        $dibuat = User::create(['name' => 'X', 'email' => 'x@c.test', 'password' => 'x', 'role' => 'admin']);
        $this->assertSame(['admin'], $dibuat->fresh()->kodePeran());

        $dibuat->update(['role' => 'guru']); // menambah, tidak menimpa
        $this->assertEqualsCanonicalizing(['admin', 'guru'], $dibuat->fresh()->kodePeran());

        $this->expectException(\InvalidArgumentException::class);
        User::factory()->create(['role' => 'peran_ngawur']);
    }

    public function test_migrasi_memindahkan_role_lama_ke_tabel_peran(): void
    {
        Schema::table('users', fn ($t) => $t->string('role', 50)->default('siswa'));
        foreach (['admin', 'guru', 'siswa', 'ngawur'] as $i => $role) {
            DB::table('users')->insert(['sekolah_id' => $this->sekolah->id, 'name' => $role, 'username' => "u$i", 'email' => "$role@c.test", 'password' => 'x', 'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('peran_user')->delete();

        (require base_path('database/migrations/2026_11_12_000001_create_peran_tables.php'))->up();

        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $peran = fn (string $email) => User::where('email', "$email@c.test")->first()->kodePeran();
        $this->assertSame(['admin'], $peran('admin'));
        $this->assertSame(['guru'], $peran('guru'));
        $this->assertSame(['siswa'], $peran('siswa'));
        $this->assertSame([], $peran('ngawur'), 'role tak dikenal tidak diberi peran');
    }

    // ------------------------------------------------------------ login, panel, CheckRole

    public function test_login_diarahkan_ke_panel_sesuai_peran(): void
    {
        $this->user(['admin'], ['username' => 'a']);
        $this->user(['wali_kelas'], ['username' => 'w']);

        $this->post('/login', ['username' => 'a', 'password' => 'rahasia'])->assertRedirect(route('admin/dashboard'));
        $this->post('/logout');
        $this->post('/login', ['username' => 'w', 'password' => 'rahasia'])->assertRedirect(route('guru/dashboard'));
    }

    public function test_akun_tanpa_peran_panel_tidak_bisa_masuk(): void
    {
        $this->user([], ['username' => 'kosong']); // tanpa peran apa pun

        foreach (['kosong'] as $username) {
            $this->from('/login')->post('/login', ['username' => $username, 'password' => 'rahasia'])->assertSessionHasErrors('username');
            $this->assertGuest();
        }
    }

    public function test_wali_kelas_membuka_panel_guru_dan_guru_tidak_membuka_panel_admin(): void
    {
        $wali = $this->user(['wali_kelas']);
        $this->actingAs($wali)->get('/guru/dashboard')->assertOk();
        $this->get('/guru/jadwal')->assertOk();
        $this->get('/admin/dashboard')->assertRedirect(route('guru/dashboard'));
        $this->getJson('/admin/pengguna/data')->assertForbidden();

        $guru = $this->user(['guru']);
        $this->actingAs($guru)->get('/admin/dashboard')->assertRedirect(route('guru/dashboard'));
        $this->actingAs($this->user(['siswa']))->get('/guru/dashboard')->assertRedirect(route('siswa/dashboard'));
    }

    public function test_akun_admin_dan_guru_dapat_membuka_kedua_panel_dengan_menu_masing_masing(): void
    {
        $dua = $this->user(['admin', 'guru']);
        $this->actingAs($dua);

        $this->assertSame(['admin', 'guru'], $dua->panelTersedia());
        $this->get('/admin/dashboard')->assertOk()->assertSee('Data Siswa');
        $this->get('/guru/dashboard')->assertOk()->assertDontSee('Data Siswa')->assertSee('Jadwal Mengajar'); // panel mengikuti awalan alamat
        $this->get('/guru/profile')->assertOk();
    }

    public function test_ganti_panel_disimpan_di_sesi_dan_dibatasi_pada_panel_yang_dimiliki(): void
    {
        $dua = $this->user(['admin', 'guru']);
        $this->actingAs($dua);

        $this->post('/ganti-panel/guru')->assertRedirect(route('guru/dashboard'));
        $this->assertSame('guru', session('panel_aktif'));
        $this->get('/')->assertOk()->assertSee(route('guru/dashboard')); // tombol Dashboard di situs publik mengikuti panel aktif

        $this->post('/ganti-panel/siswa')->assertForbidden();
        $this->post('/ganti-panel/ngawur')->assertForbidden();

        auth()->logout();
        $this->post('/ganti-panel/admin')->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------ menu Pengguna: ubah peran

    private function admin(): User
    {
        return $this->user(['admin'], ['username' => 'root']);
    }

    public function test_admin_mengubah_peran_pengguna(): void
    {
        $admin = $this->admin();
        $target = $this->user(['guru']);

        $this->actingAs($admin)->putJson("/admin/pengguna/{$target->id}/peran", ['peran' => ['guru', 'wali_kelas', 'bendahara']])->assertOk();
        $this->assertEqualsCanonicalizing(['guru', 'wali_kelas', 'bendahara'], $target->fresh()->kodePeran());

        $this->putJson("/admin/pengguna/{$target->id}/peran", ['peran' => []])->assertStatus(422)->assertJsonValidationErrors('peran');
        $this->putJson("/admin/pengguna/{$target->id}/peran", ['peran' => ['super_admin']])->assertStatus(422);
        $this->putJson("/admin/pengguna/{$target->id}/peran", ['peran' => ['dewa']])->assertStatus(422);
    }

    public function test_peran_super_admin_tidak_tersentuh_dan_tidak_bisa_diberikan_admin_sekolah(): void
    {
        $admin = $this->admin();
        $target = $this->user(['super_admin', 'guru']);

        $this->actingAs($admin)->putJson("/admin/pengguna/{$target->id}/peran", ['peran' => ['siswa']])->assertOk();

        $this->assertEqualsCanonicalizing(['super_admin', 'siswa'], $target->fresh()->kodePeran());
    }

    public function test_pencabutan_peran_dilindungi(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        // diri sendiri dan admin terakhir
        $this->putJson("/admin/pengguna/{$admin->id}/peran", ['peran' => ['guru']])->assertStatus(409);
        $lain = $this->user(['admin']);
        $this->putJson("/admin/pengguna/{$lain->id}/peran", ['peran' => ['guru']])->assertOk(); // masih ada admin lain (diri sendiri)

        // peran guru/siswa yang terhubung data
        $mapel = Mapel::create(['mapel_nama' => 'MTK']);
        $uGuru = $this->user(['guru']);
        Guru::create(['user_id' => $uGuru->id, 'nip_guru' => '1', 'nama_guru' => 'G', 'mapel_id' => $mapel->id, 'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '0', 'tanggal_lahir' => '1980-01-01']);
        $this->putJson("/admin/pengguna/{$uGuru->id}/peran", ['peran' => ['bendahara']])->assertStatus(409);

        $uSiswa = $this->user(['siswa']);
        Siswa::create(['user_id' => $uSiswa->id, 'nisn' => '11111', 'nama_siswa' => 'S', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => Kelas::create(['nama_kelas' => 'X'])->id]);
        $this->putJson("/admin/pengguna/{$uSiswa->id}/peran", ['peran' => ['orang_tua']])->assertStatus(409);
        $this->putJson("/admin/pengguna/{$uSiswa->id}/peran", ['peran' => ['siswa', 'orang_tua']])->assertOk(); // menambah boleh
    }

    public function test_daftar_pengguna_menampilkan_semua_peran_dan_bisa_difilter(): void
    {
        $admin = $this->admin();
        $dua = $this->user(['guru', 'bendahara'], ['name' => 'Bu Dua']);
        $this->user(['siswa'], ['name' => 'Siswa Satu']);

        $this->actingAs($admin);
        $semua = $this->getJson('/admin/pengguna/data?'.$this->dt())->assertOk();
        $baris = collect($semua->json('data'))->firstWhere('name', 'Bu Dua');
        $this->assertStringStartsWith('Guru, Bendahara|', $baris['role']);
        $this->assertEqualsCanonicalizing(['guru', 'bendahara'], $baris['peran']);

        $this->assertSame(['Bu Dua'], collect($this->getJson('/admin/pengguna/data?role=bendahara&'.$this->dt())->json('data'))->pluck('name')->all());
        $this->assertCount(1, $this->getJson('/admin/pengguna/data?role=siswa&'.$this->dt())->json('data'));
        $this->get('/admin/pengguna')->assertOk()->assertSee('Semua peran')->assertSee('Ubah Peran')->assertSee('Wali Kelas');
        $this->assertNotNull($dua);
    }

    public function test_reset_password_admin_wajib_diisi_dan_guru_siswa_kembali_ke_password_awal(): void
    {
        $admin = $this->admin();
        $guru = $this->user(['guru']);
        $adminGuru = $this->user(['admin', 'guru']);
        $this->actingAs($admin);

        $this->putJson("/admin/pengguna/{$guru->id}/password", [])->assertOk();
        $this->assertTrue(Hash::check('guru123', $guru->fresh()->password));
        $this->putJson("/admin/pengguna/{$adminGuru->id}/password", [])->assertStatus(422); // ada peran admin: wajib isi
        $this->putJson("/admin/pengguna/{$adminGuru->id}/password", ['password' => 'baru1234'])->assertOk();
    }

    public function test_admin_terakhir_dilindungi_berdasarkan_peran(): void
    {
        $admin = $this->admin();
        $adminGuru = $this->user(['admin', 'guru']);

        $this->actingAs($admin)->deleteJson("/admin/pengguna/{$adminGuru->id}")->assertOk(); // masih ada 1 admin tersisa
        $this->assertDatabaseMissing('users', ['id' => $adminGuru->id]);
        $this->deleteJson("/admin/pengguna/{$admin->id}")->assertStatus(409); // diri sendiri
    }
}
