<?php

namespace Tests\Feature;

use App\Absensi;
use App\Guru;
use App\Kelas;
use App\Langganan;
use App\Mapel;
use App\Nilai;
use App\OrangTua;
use App\Paket;
use App\Sekolah;
use App\Siswa;
use App\Support\AksesSekolah;
use App\Support\Akademik;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Peran kepala sekolah, wali kelas, dan orang tua beserta portal orang tua. */
class OrangTuaKepsekTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Kelas $kelas;
    private Mapel $mapel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->kelas = Kelas::create(['nama_kelas' => 'X-A']);
        $this->mapel = Mapel::create(['mapel_nama' => 'Matematika']);
    }

    private function siswa(string $nisn, ?Kelas $kelas = null): Siswa
    {
        return Siswa::create(['nisn' => $nisn, 'nama_siswa' => "Anak $nisn", 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2009-01-01', 'kelas_id' => ($kelas ?? $this->kelas)->id, 'user_id' => User::factory()->create(['role' => 'siswa'])->id]);
    }

    private function guru(string $nama): Guru
    {
        return Guru::create(['nip_guru' => 'n'.$nama, 'nama_guru' => $nama, 'jenis_kelamin' => 'Laki-laki', 'no_hp' => '0812', 'tanggal_lahir' => '1980-01-01', 'mapel_id' => $this->mapel->id, 'user_id' => User::factory()->create(['role' => 'guru'])->id]);
    }

    private function ortu(string $username, array $anak = [], string $hubungan = 'ibu'): OrangTua
    {
        $user = User::factory()->create(['role' => 'orang_tua', 'username' => $username, 'name' => "Ortu $username"]);
        $o = OrangTua::create(['user_id' => $user->id, 'nama' => "Ortu $username", 'no_hp' => '0813']);
        foreach ($anak as $s) {
            $o->hubungkan($s, $hubungan);
        }

        return $o;
    }

    // ------------------------------------------------------------ kepala sekolah

    public function test_kepsek_masuk_ke_panel_sendiri_dan_hanya_membaca(): void
    {
        $kepsek = User::factory()->create(['role' => 'kepsek', 'username' => 'kepsek1']);
        $s = $this->siswa('1001');
        Absensi::create(['siswa_id' => $s->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today(), 'status' => 'hadir']);

        $this->post('/login', ['username' => 'kepsek1', 'password' => 'password'])->assertRedirect(route('kepsek/dashboard'));
        $this->actingAs($kepsek);

        $this->get('/kepsek/dashboard')->assertOk()->assertSee('Dashboard Kepala Sekolah')->assertSee('Kehadiran Hari Ini');
        $this->get('/kepsek/kelas')->assertOk()->assertSee('Rombongan Belajar');
        $this->getJson('/kepsek/kelas/data?draw=1&start=0&length=10')->assertOk()->assertJsonPath('data.0.siswa_count', 1);
        $this->get("/kepsek/kelas/open/{$this->kelas->id}")->assertOk()->assertSee('Anak 1001')->assertDontSee('Keluarkan dari rombel');
        $this->get('/kepsek/kehadiran')->assertOk()->assertSee('X-A');
        $this->get('/kepsek/rapor/'.$s->id)->assertOk()->assertSee('LAPORAN HASIL BELAJAR');

        // tanpa akses tulis maupun panel lain
        $this->postJson('/kepsek/kelas', ['nama_kelas' => 'Z'])->assertStatus(405);
        $this->postJson('/admin/kelas', ['nama_kelas' => 'Z'])->assertForbidden();
        $this->get('/admin/dashboard')->assertRedirect(route('kepsek/dashboard'));
        $this->get('/guru/dashboard')->assertRedirect(route('kepsek/dashboard'));
    }

    public function test_akun_dengan_peran_kepsek_dan_guru_bisa_pindah_panel(): void
    {
        $guru = $this->guru('Budi');
        $guru->user->tambahPeran('kepsek');
        $this->actingAs($guru->user->fresh());

        $this->assertSame(['kepsek', 'guru'], $guru->user->fresh()->panelTersedia());
        $this->get('/guru/dashboard')->assertOk()->assertSee('Buka panel Kepala Sekolah');
        $this->post('/ganti-panel/kepsek')->assertRedirect(route('kepsek/dashboard'));
    }

    // ------------------------------------------------------------ wali kelas

    public function test_wali_kelas_melihat_siswa_rombelnya_dengan_kehadiran_nilai_dan_orang_tua(): void
    {
        $budi = $this->guru('Budi');
        $this->kelas->update(['wali_guru_id' => $budi->id]);
        Kelas::sinkronkanPeranWali();
        $s = $this->siswa('1001');
        Absensi::create(['siswa_id' => $s->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today(), 'status' => 'sakit']);
        $this->ortu('ibu.ani', [$s]);
        $semester = Akademik::semesterAktif();
        Nilai::create(['siswa_id' => $s->id, 'kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'jenis' => 'uh', 'nilai' => 88, 'semester_id' => $semester->id]);

        $this->actingAs($budi->user->fresh());
        $this->get('/guru/dashboard')->assertOk()->assertSee('Kelas Wali');
        $this->get('/guru/wali')->assertOk()->assertSee('Rombel X-A')->assertSee('Anak 1001')->assertSee('Ortu ibu.ani')->assertSee('88,0')->assertSee('S 1');
    }

    public function test_guru_biasa_tidak_melihat_menu_dan_halaman_wali(): void
    {
        $sari = $this->guru('Sari');
        $this->actingAs($sari->user);

        $this->get('/guru/dashboard')->assertOk()->assertDontSee('Kelas Wali');
        $this->get('/guru/wali')->assertRedirect(route('guru/dashboard'));
    }

    public function test_wali_tanpa_rombel_aktif_melihat_pesan_kosong(): void
    {
        $budi = $this->guru('Budi');
        $budi->user->tambahPeran('wali_kelas');

        $this->actingAs($budi->user->fresh())->get('/guru/wali')->assertOk()->assertSee('belum ditetapkan sebagai wali kelas');
    }

    // ------------------------------------------------------------ admin: akun orang tua

    public function test_admin_membuat_orang_tua_dan_orang_tua_bisa_login_dengan_username(): void
    {
        $s = $this->siswa('1001');
        $this->actingAs($this->admin);

        $this->postJson('/admin/orang-tua', ['nama' => 'Bu Sari', 'username' => 'bu.sari', 'no_hp' => '0812', 'siswa_id' => $s->id, 'hubungan' => 'ibu'])->assertCreated();

        $ortu = OrangTua::firstOrFail();
        $this->assertTrue($ortu->user->punyaPeran('orang_tua'));
        $this->assertSame('bu.sari@ortu.invalid', $ortu->user->email);
        $this->assertSame(['ibu'], $ortu->anak->pluck('pivot.hubungan')->all());

        auth()->logout();
        $this->post('/login', ['username' => 'bu.sari', 'password' => 'ortu123'])->assertRedirect(route('ortu/dashboard'));
    }

    public function test_validasi_dan_keunikan_orang_tua(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/admin/orang-tua', [])->assertStatus(422)->assertJsonValidationErrors(['nama', 'username']);
        $this->postJson('/admin/orang-tua', ['nama' => 'A', 'username' => 'Ada Spasi'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->postJson('/admin/orang-tua', ['nama' => 'Ayah', 'username' => 'ayah1'])->assertCreated();
        $this->postJson('/admin/orang-tua', ['nama' => 'Ayah Dua', 'username' => 'ayah1'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->postJson('/admin/orang-tua', ['nama' => 'Tanpa email 2', 'username' => 'ayah2'])->assertCreated(); // email placeholder tidak bentrok
        $this->assertSame(2, OrangTua::count());
    }

    public function test_ubah_hapus_dan_kelola_anak(): void
    {
        $this->actingAs($this->admin);
        [$a, $b] = [$this->siswa('1001'), $this->siswa('1002')];
        $o = $this->ortu('wali1', [$a], 'wali');

        $this->putJson("/admin/orang-tua/{$o->id}", ['nama' => 'Nama Baru', 'username' => 'wali1', 'password' => 'rahasia1'])->assertOk();
        $this->assertSame('Nama Baru', $o->user->fresh()->name);
        $this->assertTrue(\Hash::check('rahasia1', $o->user->fresh()->password));

        $this->get("/admin/orang-tua/{$o->id}")->assertOk()->assertSee('Anak 1001')->assertSee('Anak 1002'); // calon
        $this->postJson("/admin/orang-tua/{$o->id}/anak", ['siswa_id' => $b->id, 'hubungan' => 'ayah'])->assertOk();
        $this->assertSame(2, $o->anak()->count());
        $this->postJson("/admin/orang-tua/{$o->id}/anak", ['siswa_id' => $b->id, 'hubungan' => 'xx'])->assertStatus(422);
        $this->deleteJson("/admin/orang-tua/{$o->id}/anak/{$a->id}")->assertOk();
        $this->assertSame([$b->id], $o->anak()->pluck('siswa.id')->all());
        $this->getJson('/admin/orang-tua/data?draw=1&start=0&length=10')->assertJsonPath('data.0.anak_count', 1);

        $this->deleteJson("/admin/orang-tua/{$o->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['username' => 'wali1']);
        $this->assertDatabaseCount('orang_tua_siswa', 0);
        $this->assertNotNull(Siswa::find($a->id), 'data siswa tidak ikut terhapus');
    }

    public function test_orang_tua_tidak_bisa_dihubungkan_dengan_siswa_sekolah_lain(): void
    {
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMA', 'status' => 'aktif', 'tanpa_batas' => true]);
        $asing = SekolahAktif::jalankan($lain, function () {
            $k = Kelas::create(['nama_kelas' => 'Z']);

            return Siswa::create(['nisn' => '9', 'nama_siswa' => 'Asing', 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2009-01-01', 'kelas_id' => $k->id, 'user_id' => User::factory()->create(['role' => 'siswa'])->id]);
        });
        $o = $this->ortu('wali1');

        $this->actingAs($this->admin)->postJson("/admin/orang-tua/{$o->id}/anak", ['siswa_id' => $asing->id, 'hubungan' => 'ayah'])->assertStatus(422);
        $this->assertSame(0, $o->anak()->count());
    }

    // ------------------------------------------------------------ portal orang tua

    public function test_portal_menampilkan_anak_wali_kelas_dan_kehadiran_bulan_ini(): void
    {
        $budi = $this->guru('Budi');
        $this->kelas->update(['wali_guru_id' => $budi->id]);
        $s = $this->siswa('1001');
        Absensi::create(['siswa_id' => $s->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today(), 'status' => 'hadir']);
        $o = $this->ortu('wali1', [$s]);

        $this->actingAs($o->user);
        $this->get('/ortu/dashboard')->assertOk()->assertSee('Anak 1001')->assertSee('Budi')->assertSee('100%')->assertSee('Nilai &amp; Rapor', false);
        $this->getJson('/ortu/kehadiran/data?bulan='.now()->format('Y-m'))->assertOk()->assertJsonPath('ringkasan.hadir', 1);
        $this->get('/ortu/kehadiran')->assertOk()->assertSee('Anak 1001');
    }

    public function test_memilih_anak_mengubah_data_yang_tampil_dan_anak_orang_lain_ditolak(): void
    {
        [$a, $b, $c] = [$this->siswa('1001'), $this->siswa('1002'), $this->siswa('1003')];
        Absensi::create(['siswa_id' => $a->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today(), 'status' => 'alpa']);
        Absensi::create(['siswa_id' => $b->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today(), 'status' => 'sakit']);
        $o = $this->ortu('wali1', [$a, $b]);
        $this->ortu('wali2', [$c]);
        $bulan = now()->format('Y-m');

        $this->actingAs($o->user);
        $this->get('/ortu/dashboard')->assertOk()->assertSee('Anak 1001')->assertSee('Anak 1002');
        $this->getJson("/ortu/kehadiran/data?bulan=$bulan")->assertJsonPath('ringkasan.alpa', 1)->assertJsonPath('ringkasan.sakit', 0); // anak pertama (urut nama)

        $this->post("/ortu/anak/{$b->id}")->assertRedirect();
        $this->getJson("/ortu/kehadiran/data?bulan=$bulan")->assertJsonPath('ringkasan.sakit', 1)->assertJsonPath('ringkasan.alpa', 0);

        $this->post("/ortu/anak/{$c->id}")->assertForbidden();
        $this->getJson("/ortu/kehadiran/data?bulan=$bulan")->assertJsonPath('ringkasan.sakit', 1); // pilihan tidak berubah
        $this->get('/ortu/dashboard')->assertDontSee('Anak 1003');
    }

    public function test_nilai_dan_rapor_anak_dengan_kkm_semester(): void
    {
        $s = $this->siswa('1001');
        $semester = Akademik::semesterAktif();
        $semester->update(['kkm' => 80]);
        foreach (['tugas', 'uh', 'uts', 'uas'] as $j) {
            Nilai::create(['siswa_id' => $s->id, 'kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'jenis' => $j, 'nilai' => 75, 'semester_id' => $semester->id]);
        }
        $o = $this->ortu('wali1', [$s]);

        $this->actingAs($o->user)->get('/ortu/nilai')->assertOk()->assertSee('Anak 1001')->assertSee('Matematika')->assertSee('Belum tuntas')->assertSee('LAPORAN HASIL BELAJAR');
        $this->get('/ortu/nilai?tahun_ajaran=salah')->assertNotFound();
    }

    public function test_orang_tua_tanpa_anak_dan_pembatasan_akses(): void
    {
        $o = $this->ortu('kosong');
        $this->actingAs($o->user);

        $this->get('/ortu/dashboard')->assertOk()->assertSee('Belum ada siswa yang terhubung');
        $this->get('/ortu/kehadiran')->assertNotFound();
        $this->get('/ortu/nilai')->assertNotFound();
        foreach (['/admin/dashboard', '/guru/dashboard', '/siswa/dashboard', '/kepsek/dashboard'] as $url) {
            $this->get($url)->assertRedirect(route('ortu/dashboard'));
        }
        $this->getJson('/admin/siswa/data')->assertForbidden();
        $this->getJson('/siswa/kehadiran/data?bulan='.now()->format('Y-m'))->assertForbidden();
    }

    public function test_profil_orang_tua_dan_ganti_password(): void
    {
        $o = $this->ortu('wali1');
        $this->actingAs($o->user);

        $this->get('/ortu/profile')->assertOk()->assertSee('wali1');
        $this->putJson('/ortu/profile', ['nama' => 'Nama Baru', 'no_hp' => '0899'])->assertOk();
        $this->assertSame(['Nama Baru', '0899'], [$o->fresh()->nama, $o->fresh()->no_hp]);
        $this->assertSame('Nama Baru', $o->user->fresh()->name);

        $this->putJson('/ortu/profile/password', ['password_lama' => 'salah', 'password' => 'baru1234', 'password_confirmation' => 'baru1234'])->assertStatus(422);
        $this->putJson('/ortu/profile/password', ['password_lama' => 'password', 'password' => 'baru1234', 'password_confirmation' => 'baru1234'])->assertOk();
        $this->assertTrue(\Hash::check('baru1234', $o->user->fresh()->password));
    }

    // ------------------------------------------------------------ paket langganan

    public function test_portal_orang_tua_lengkap_hanya_untuk_paket_premium(): void
    {
        $s = $this->siswa('1001');
        $o = $this->ortu('wali1', [$s]);
        $this->sekolah->update(['tanpa_batas' => false]);
        $langganan = fn (string $paket) => Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', $paket)->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDays(5), 'berakhir' => today()->addDays(25), 'status' => 'aktif']);

        // Basic: tidak ada panel orang tua sama sekali
        $langganan('basic');
        AksesSekolah::lupakan();
        $this->actingAs($this->admin)->get('/admin/orang-tua')->assertRedirect(route('admin/dashboard'));
        $this->get('/admin/dashboard')->assertOk()->assertDontSee(route('admin/orang-tua'));
        $this->actingAs($o->user)->get('/ortu/dashboard')->assertForbidden();
        $this->getJson('/ortu/kehadiran/data')->assertForbidden();

        // Pro (punya Keuangan): panel orang tua terbuka untuk tagihan, tetapi kehadiran dan nilai tetap khusus Premium
        Langganan::query()->delete();
        $langganan('pro');
        AksesSekolah::lupakan();
        $this->actingAs($this->admin)->get('/admin/orang-tua')->assertOk();
        $this->actingAs($o->user)->get('/ortu/dashboard')->assertOk();
        $this->getJson('/ortu/kehadiran/data')->assertForbidden();
        $this->get('/ortu/nilai')->assertForbidden();

        Langganan::query()->delete();
        $langganan('premium');
        AksesSekolah::lupakan();
        $this->actingAs($o->user)->get('/ortu/dashboard')->assertOk();
        $this->actingAs($this->admin)->get('/admin/orang-tua')->assertOk();
        $this->get('/admin/dashboard')->assertSee(route('admin/orang-tua'));
    }

    public function test_menghapus_peran_orang_tua_dari_akun_yang_punya_data_ditolak(): void
    {
        $o = $this->ortu('wali1');

        $this->actingAs($this->admin)->putJson("/admin/pengguna/{$o->user_id}/peran", ['peran' => ['guru']])->assertStatus(409);
        $this->actingAs($this->admin)->putJson("/admin/pengguna/{$o->user_id}/password", [])->assertOk();
        $this->assertTrue(\Hash::check('ortu123', $o->user->fresh()->password));
    }
}
