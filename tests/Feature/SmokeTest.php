<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Smoke test: database dibangun dari migration (sqlite in-memory) dan diisi data minimal,
 * jadi tes ini tidak bergantung pada database lokal atau dump SQL.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $password = Hash::make('password');

        foreach (['admin', 'guru', 'siswa'] as $role) {
            $id = DB::table('users')->insertGetId([
                'sekolah_id' => $this->sekolah->id,
                'name' => ucfirst($role), 'username' => $role, 'email' => "$role@example.test",
                'password' => $password, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('peran_user')->insert(['user_id' => $id, 'peran_id' => DB::table('peran')->where('kode', $role)->value('id'), 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('mapel')->insert(['sekolah_id' => $this->sekolah->id, 'mapel_nama' => 'Matematika', 'created_at' => $now, 'updated_at' => $now]);
        \App\Kelas::create(['nama_kelas' => 'VII A']);
        DB::table('kategori')->insert(['sekolah_id' => $this->sekolah->id, 'kategori_nama' => 'Umum', 'created_at' => $now, 'updated_at' => $now]);
        // Admin juga memiliki data guru (dipakai sidebar dan profil), sama seperti data produksi.
        foreach ([2 => 'Guru Satu', 1 => 'Admin Satu'] as $userId => $nama) {
            DB::table('guru')->insert([
            'sekolah_id' => $this->sekolah->id,
                'nip_guru' => (string) $userId, 'nama_guru' => $nama, 'user_id' => $userId, 'mapel_id' => 1,
                'jenis_kelamin' => 'L', 'no_hp' => '0812', 'tanggal_lahir' => '1990-01-01',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('siswa')->insert([
            'sekolah_id' => $this->sekolah->id,
            'nisn' => '1', 'nama_siswa' => 'Siswa Satu', 'agama' => 'Islam', 'jenis_kelamin' => 'L',
            'tanggal_lahir' => '2010-01-01', 'user_id' => 3, 'kelas_id' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('berita')->insert([
            'sekolah_id' => $this->sekolah->id,
            'berita_judul' => 'Judul Berita', 'berita_isi' => 'Isi berita', 'kategori_id' => 1,
            'author' => 'Admin', 'berita_slug' => 'judul-berita', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('pengumuman')->insert([
            'sekolah_id' => $this->sekolah->id,
            'pengumuman_judul' => 'Info', 'pengumuman_deskripsi' => 'Isi', 'pengumuman_author' => 'Admin',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('agenda')->insert([
            'sekolah_id' => $this->sekolah->id,
            'agenda_nama' => 'Rapat', 'agenda_deskripsi' => 'Rapat guru', 'agenda_mulai' => '2026-01-01',
            'agenda_selesai' => '2026-01-02', 'agenda_tempat' => 'Aula', 'agenda_waktu' => '08:00',
            'agenda_keterangan' => '-', 'agenda_author' => 'Admin', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('gallery')->insert(['sekolah_id' => $this->sekolah->id, 'author' => 'Admin', 'foto' => 'a.jpg', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('timeline')->insert(['sekolah_id' => $this->sekolah->id, 'author' => 'Admin', 'message' => 'membuat berita', 'created_at' => $now, 'updated_at' => $now]);

        for ($i = 1; $i <= 9; $i++) {
            DB::table('pengaturan')->insert([
            'sekolah_id' => $this->sekolah->id,
                'id' => $i, 'nama' => "n$i", 'judul' => "Judul $i", 'deskripsi' => "Deskripsi $i",
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public static function publicPages(): array
    {
        return array_map(fn ($u) => [$u], [
            '/', '/profil', '/akademik', '/berita', '/berita?kategori=Umum', '/berita/judul-berita', '/pengumuman', '/agenda',
            '/galeri', '/kontak', '/ppdb', '/ppdb/cek', '/login',
        ]);
    }

    public static function adminPages(): array
    {
        return array_map(fn ($u) => [$u], [
            '/admin/dashboard', '/admin/profile', '/admin/pengumuman', '/admin/agenda', '/admin/kategori',
            '/admin/berita', '/admin/berita/create', '/admin/berita/1/edit', '/admin/guru', '/admin/siswa',
            '/admin/kelas', '/admin/kelas/open/1', '/admin/mapel', '/admin/pengaturan',
            '/admin/gallery',
        ]);
    }

    public static function guruPages(): array
    {
        return array_map(fn ($u) => [$u], [
            '/guru/dashboard', '/guru/profile', '/guru/berita', '/guru/berita/create', '/guru/kategori', '/guru/kelas', '/guru/kelas/open/1',
            '/guru/mapel',
        ]);
    }

    public static function siswaPages(): array
    {
        return array_map(fn ($u) => [$u], [
            '/siswa/dashboard', '/siswa/profile', '/siswa/kelas',
        ]);
    }

    #[DataProvider('publicPages')]
    public function test_halaman_publik_dapat_dibuka(string $url): void
    {
        $this->get($url)->assertOk();
    }

    #[DataProvider('adminPages')]
    public function test_halaman_admin(string $url): void
    {
        $this->actingAs(User::denganPeran('admin')->first())->get($url)->assertOk();
    }

    #[DataProvider('guruPages')]
    public function test_halaman_guru(string $url): void
    {
        $this->actingAs(User::denganPeran('guru')->first())->get($url)->assertOk();
    }

    #[DataProvider('siswaPages')]
    public function test_halaman_siswa(string $url): void
    {
        $this->actingAs(User::denganPeran('siswa')->first())->get($url)->assertOk();
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_role_lain_tidak_bisa_membuka_halaman_admin(): void
    {
        $this->actingAs(User::denganPeran('siswa')->first())
            ->get('/admin/dashboard')
            ->assertRedirect(route('siswa/dashboard'));
    }

    #[DataProvider('loginRoles')]
    public function test_login_per_role(string $role): void
    {
        $this->post('/login', ['username' => $role, 'password' => 'password'])
            ->assertRedirect(route("$role/dashboard"));
        $this->assertAuthenticated();

        $this->post('/logout');
        $this->assertGuest();
    }

    public static function loginRoles(): array
    {
        return [['admin'], ['guru'], ['siswa']];
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'salah']);
        $this->assertGuest();
    }
}
