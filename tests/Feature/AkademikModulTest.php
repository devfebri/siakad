<?php

namespace Tests\Feature;

use App\Absensi;
use App\Guru;
use App\Jadwal;
use App\Kelas;
use App\Mapel;
use App\Nilai;
use App\Siswa;
use App\Support\Akademik;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AkademikModulTest extends TestCase
{
    use RefreshDatabase;

    private Kelas $kelas;
    private Mapel $mapel;
    private Guru $guru;
    private Siswa $siswa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->kelas = Kelas::create(['nama_kelas' => 'X IPA 1']);
        $this->mapel = Mapel::create(['mapel_nama' => 'Matematika']);
        $this->guru = Guru::create(['nip_guru' => '1980', 'nama_guru' => 'Pak Budi', 'jenis_kelamin' => 'Laki-laki', 'no_hp' => '0812', 'tanggal_lahir' => '1980-01-01', 'mapel_id' => $this->mapel->id, 'user_id' => User::factory()->create(['role' => 'guru'])->id]);
        $this->siswa = Siswa::create(['nisn' => '0011', 'nama_siswa' => 'Ani', 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2009-01-01', 'kelas_id' => $this->kelas->id, 'user_id' => User::factory()->create(['role' => 'siswa'])->id]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function jadwal(array $extra = []): array
    {
        return $extra + ['kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'guru_id' => $this->guru->id, 'hari' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '08:30', 'ruang' => 'R1'];
    }

    public function test_halaman_baru_terbuka_untuk_setiap_role(): void
    {
        $admin = $this->admin();
        foreach (['jadwal', 'absensi', 'nilai', 'pengguna', 'dashboard'] as $h) {
            $this->actingAs($admin)->get("/admin/$h")->assertOk();
        }
        $this->actingAs($this->guru->user)->get('/guru/jadwal')->assertOk();
        $this->actingAs($this->guru->user)->get('/guru/absensi')->assertOk();
        $this->actingAs($this->guru->user)->get('/guru/nilai')->assertOk();
        $this->actingAs($this->guru->user)->get('/guru/dashboard')->assertOk();
        foreach (['jadwal', 'kehadiran', 'nilai', 'dashboard'] as $h) {
            $this->actingAs($this->siswa->user)->get("/siswa/$h")->assertOk();
        }
        $this->actingAs($this->siswa->user)->get('/admin/pengguna')->assertRedirect();
    }

    public function test_jadwal_crud_dan_deteksi_bentrok(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/admin/jadwal', $this->jadwal())->assertCreated();
        $this->assertDatabaseCount('jadwal', 1);

        // jam bertumpuk di kelas yang sama
        $this->actingAs($admin)->postJson('/admin/jadwal', $this->jadwal(['jam_mulai' => '08:00', 'jam_selesai' => '09:00', 'guru_id' => null]))->assertStatus(422)->assertJsonValidationErrors('jam_mulai');
        // guru yang sama di kelas lain pada jam yang sama
        $lain = Kelas::create(['nama_kelas' => 'X IPA 2']);
        $this->actingAs($admin)->postJson('/admin/jadwal', $this->jadwal(['kelas_id' => $lain->id]))->assertStatus(422)->assertJsonValidationErrors('guru_id');
        // jam berurutan boleh
        $this->actingAs($admin)->postJson('/admin/jadwal', $this->jadwal(['jam_mulai' => '08:30', 'jam_selesai' => '10:00']))->assertCreated();
        // jam selesai sebelum mulai
        $this->actingAs($admin)->postJson('/admin/jadwal', $this->jadwal(['hari' => 2, 'jam_mulai' => '10:00', 'jam_selesai' => '09:00']))->assertStatus(422);

        $id = Jadwal::first()->id;
        $this->actingAs($admin)->putJson("/admin/jadwal/$id", $this->jadwal(['ruang' => 'Lab']))->assertOk();
        $this->assertSame('Lab', Jadwal::find($id)->ruang);
        $this->actingAs($admin)->deleteJson("/admin/jadwal/$id")->assertOk();
        $this->assertDatabaseMissing('jadwal', ['id' => $id]);
    }

    public function test_grid_jadwal_menurut_role(): void
    {
        $periode = ['semester_id' => Akademik::semesterAktif()->id];
        Jadwal::create($this->jadwal(['jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00', ...$periode]));

        $this->actingAs($this->admin())->getJson('/admin/jadwal/grid?kelas_id='.$this->kelas->id)->assertOk()->assertJsonPath('hari.0.items.0.mapel', 'Matematika');
        $this->actingAs($this->guru->user)->getJson('/guru/jadwal/grid')->assertOk()->assertJsonPath('hari.0.items.0.kelas', 'X IPA 1');
        $this->actingAs($this->siswa->user)->getJson('/siswa/jadwal/grid')->assertOk()->assertJsonPath('hari.0.items.0.guru', 'Pak Budi');
        // admin tanpa kelas terpilih tidak melihat apa pun
        $this->actingAs($this->admin())->getJson('/admin/jadwal/grid')->assertOk()->assertJsonPath('hari.0.items', []);
    }

    public function test_absensi_disimpan_ulang_tanpa_duplikat_dan_menolak_data_salah(): void
    {
        $guru = $this->guru->user;
        $payload = fn (string $status, ?string $tgl = null) => ['kelas_id' => $this->kelas->id, 'tanggal' => $tgl ?? today()->toDateString(), 'items' => [['siswa_id' => $this->siswa->id, 'status' => $status]]];

        $this->actingAs($guru)->postJson('/guru/absensi', $payload('hadir'))->assertOk()->assertJsonPath('jumlah', 1);
        $this->actingAs($guru)->postJson('/guru/absensi', $payload('sakit'))->assertOk();
        $this->assertDatabaseCount('absensi', 1);
        $this->assertSame('sakit', Absensi::first()->status);

        $this->actingAs($guru)->postJson('/guru/absensi', $payload('hadir', today()->addDay()->toDateString()))->assertStatus(422)->assertJsonValidationErrors('tanggal');
        $this->actingAs($guru)->postJson('/guru/absensi', $payload('bolos'))->assertStatus(422);

        // siswa dari kelas lain diabaikan
        $asing = Siswa::create(['nisn' => '0022', 'nama_siswa' => 'Rudi', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '2009-01-01', 'user_id' => User::factory()->create(['role' => 'siswa'])->id, 'kelas_id' => Kelas::create(['nama_kelas' => 'XI'])->id]);
        $res = $this->actingAs($guru)->postJson('/guru/absensi', ['kelas_id' => $this->kelas->id, 'tanggal' => today()->subDay()->toDateString(), 'items' => [['siswa_id' => $asing->id, 'status' => 'hadir']]]);
        $res->assertOk()->assertJsonPath('jumlah', 0);
        $this->assertDatabaseMissing('absensi', ['siswa_id' => $asing->id]);

        $this->actingAs($guru)->getJson('/guru/absensi/siswa?kelas_id='.$this->kelas->id.'&tanggal='.today()->toDateString())
            ->assertOk()->assertJsonPath('siswa.0.status', 'sakit')->assertJsonPath('sudah_tercatat', true);
    }

    public function test_rekap_dan_kehadiran_siswa(): void
    {
        Absensi::create(['siswa_id' => $this->siswa->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today()->startOfMonth(), 'status' => 'hadir']);
        Absensi::create(['siswa_id' => $this->siswa->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today()->startOfMonth()->addDay(), 'status' => 'alpa']);
        $bulan = today()->format('Y-m');

        $this->actingAs($this->admin())->getJson("/admin/absensi/rekap?kelas_id={$this->kelas->id}&bulan=$bulan")
            ->assertOk()->assertJsonPath('baris.0.hadir', 1)->assertJsonPath('baris.0.alpa', 1)->assertJsonPath('baris.0.persen', 50);
        $this->actingAs($this->siswa->user)->getJson("/siswa/kehadiran/data?bulan=$bulan")
            ->assertOk()->assertJsonPath('ringkasan.total', 2)->assertJsonCount(2, 'daftar');
        // admin tanpa data siswa tidak punya halaman kehadiran pribadi
        $this->actingAs($this->admin())->getJson("/siswa/kehadiran/data?bulan=$bulan")->assertForbidden();
    }

    public function test_nilai_disimpan_dihapus_dan_dibatasi(): void
    {
        $guru = $this->guru->user;
        $payload = fn ($nilai, $mapel = null) => ['kelas_id' => $this->kelas->id, 'mapel_id' => $mapel ?? $this->mapel->id, 'jenis' => 'uh', 'items' => [['siswa_id' => $this->siswa->id, 'nilai' => $nilai]]];

        $this->actingAs($guru)->postJson('/guru/nilai', $payload(85))->assertOk()->assertJsonPath('tersimpan', 1);
        $this->actingAs($guru)->postJson('/guru/nilai', $payload(90))->assertOk();
        $this->assertDatabaseCount('nilai', 1);
        $this->assertEquals(90, Nilai::first()->nilai);

        $this->actingAs($guru)->postJson('/guru/nilai', $payload(101))->assertStatus(422);
        $this->actingAs($guru)->postJson('/guru/nilai', $payload(-1))->assertStatus(422);

        // guru tidak boleh menilai mapel lain
        $ipa = Mapel::create(['mapel_nama' => 'Fisika']);
        $this->actingAs($guru)->postJson('/guru/nilai', $payload(80, $ipa->id))->assertForbidden();
        // admin boleh
        $this->actingAs($this->admin())->postJson('/admin/nilai', $payload(80, $ipa->id))->assertOk();
        $this->assertDatabaseCount('nilai', 2);

        // nilai kosong menghapus
        $this->actingAs($guru)->postJson('/guru/nilai', $payload(null))->assertOk();
        $this->assertDatabaseCount('nilai', 1);
    }

    public function test_nilai_akhir_tertimbang_predikat_dan_rapor(): void
    {
        $this->assertSame(82.0, Nilai::nilaiAkhir(['tugas' => 100, 'uh' => 100, 'uts' => 70, 'uas' => 70]));
        $this->assertSame(90.0, Nilai::nilaiAkhir(['uh' => 90]), 'bobot dibagi ulang bila jenis lain belum ada');
        $this->assertNull(Nilai::nilaiAkhir([]));
        foreach ([[95, 'A'], [90, 'A'], [85, 'B'], [70, 'C'], [60, 'D'], [59.9, 'E']] as [$n, $p]) {
            $this->assertSame($p, Nilai::predikat($n));
        }
        $this->assertSame('-', Nilai::predikat(null));

        $periode = ['semester_id' => Akademik::semesterAktif()->id];
        foreach (['tugas' => 60, 'uh' => 60, 'uts' => 60, 'uas' => 60] as $jenis => $n) {
            Nilai::create(['siswa_id' => $this->siswa->id, 'kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'jenis' => $jenis, 'nilai' => $n, ...$periode]);
        }

        $this->actingAs($this->siswa->user)->get('/siswa/nilai')->assertOk()->assertSee('Matematika')->assertSee('Belum tuntas');
        $this->actingAs($this->admin())->get('/admin/rapor/'.$this->siswa->id)->assertOk()->assertSee('Ani');
        $this->actingAs($this->siswa->user)->get('/siswa/nilai?tahun_ajaran=salah')->assertNotFound();
    }

    public function test_pengguna_crud_reset_password_dan_proteksi(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/admin/pengguna', ['name' => 'Admin Dua', 'username' => 'admin.dua', 'email' => 'dua@sekolah.test', 'password' => 'rahasia1'])->assertCreated();
        $dua = User::where('email', 'dua@sekolah.test')->first();
        $this->assertSame('admin', $dua->role);
        $this->actingAs($admin)->postJson('/admin/pengguna', ['name' => 'X', 'username' => 'orang.lain', 'email' => 'dua@sekolah.test', 'password' => 'rahasia1'])->assertStatus(422);

        $this->actingAs($admin)->putJson("/admin/pengguna/{$dua->id}", ['name' => 'Admin 2', 'username' => 'admin.dua', 'email' => 'dua@sekolah.test'])->assertOk();
        $this->actingAs($admin)->putJson("/admin/pengguna/{$dua->id}/password", ['password' => 'baru1234'])->assertOk();
        $this->assertTrue(\Hash::check('baru1234', $dua->fresh()->password));

        // guru: kosong = password awal; admin: wajib isi
        $this->actingAs($admin)->putJson("/admin/pengguna/{$this->guru->user_id}/password", [])->assertOk();
        $this->assertTrue(\Hash::check('guru123', $this->guru->user->fresh()->password));
        $this->actingAs($admin)->putJson("/admin/pengguna/{$dua->id}/password", [])->assertStatus(422);

        // proteksi hapus
        $this->actingAs($admin)->deleteJson("/admin/pengguna/{$admin->id}")->assertStatus(409);
        $this->actingAs($admin)->deleteJson("/admin/pengguna/{$this->siswa->user_id}")->assertStatus(409);
        $this->actingAs($admin)->deleteJson("/admin/pengguna/{$dua->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $dua->id]);

        // role selain admin tidak boleh masuk
        $this->actingAs($this->guru->user)->getJson('/admin/pengguna/data')->assertStatus(403);
    }

    public function test_dashboard_admin_menampilkan_ringkasan(): void
    {
        $periode = ['semester_id' => Akademik::semesterAktif()->id];
        Absensi::create(['siswa_id' => $this->siswa->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today(), 'status' => 'hadir']);
        Nilai::create(['siswa_id' => $this->siswa->id, 'kelas_id' => $this->kelas->id, 'mapel_id' => $this->mapel->id, 'jenis' => 'uh', 'nilai' => 88, ...$periode]);
        Jadwal::create($this->jadwal(['hari' => min(6, (int) now()->dayOfWeekIso), 'jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00', ...$periode]));

        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk()
            ->assertSee('Kehadiran Hari Ini')->assertSee('100,0%')->assertSee('Pendaftar PPDB Terbaru')->assertDontSee('Rata-rata Nilai per Kelas');
    }
}
