<?php

namespace Tests\Feature;

use App\Backup\BerkasSekolah;
use App\Backup\Pencadangan;
use App\BackupSekolah;
use App\Gallery;
use App\Guru;
use App\JenisBiaya;
use App\Kelas;
use App\Langganan;
use App\LogAktivitas;
use App\Paket;
use App\PengaturanPlatform;
use App\PengaturanWa;
use App\Peran;
use App\Sekolah;
use App\Setting;
use App\Siswa;
use App\Support\Pemantauan;
use App\Support\SekolahAktif;
use App\Tagihan;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** Tahap 3 poin 8: backup zip per sekolah (isi, keamanan, jadwal, arsip), pemantauan penggunaan, dan log aktivitas dengan filter. */
class BackupPemantauanTest extends TestCase
{
    use RefreshDatabase;

    private string $publik;
    private Sekolah $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');

        // folder publik sementara: berkas unggahan uji tidak menyentuh folder sungguhan
        $this->publik = sys_get_temp_dir().'/smpn-backup-public-'.uniqid();
        File::makeDirectory($this->publik.'/storage', 0777, true);
        $this->app->usePublicPath($this->publik);

        $this->b = Sekolah::create(['nama' => 'SMP Bunga', 'subdomain' => 'bunga', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publik);
        parent::tearDown();
    }

    private function berkasPublik(string $relatif, string $isi = 'isi-berkas'): void
    {
        File::ensureDirectoryExists(dirname($this->publik.'/'.$relatif));
        File::put($this->publik.'/'.$relatif, $isi);
    }

    /** Data uji untuk satu sekolah: akun (dengan password), siswa, guru, tagihan, foto galeri, logo, dan avatar. */
    private function isiSekolah(Sekolah $s, string $awalan): array
    {
        return SekolahAktif::jalankan($s, function () use ($s, $awalan) {
            $kelas = Kelas::create(['nama_kelas' => "$awalan-A", 'tingkat' => 7]);
            $uSiswa = User::factory()->create(['role' => 'siswa', 'email' => "{$awalan}.siswa@uji.test", 'password' => 'rahasia-siswa']);
            $siswa = Siswa::create(['user_id' => $uSiswa->id, 'nisn' => fake()->unique()->numerify('##########'), 'nama_siswa' => "Siswa $awalan", 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $kelas->id, 'avatar' => "avatar-$awalan.png"]);
            $uGuru = User::factory()->create(['role' => 'guru', 'email' => "{$awalan}.guru@uji.test"]);
            $mapel = \App\Mapel::create(['mapel_nama' => "Mapel $awalan"]);
            Guru::create(['user_id' => $uGuru->id, 'nip_guru' => fake()->unique()->numerify('############'), 'nama_guru' => "Guru $awalan", 'mapel_id' => $mapel->id, 'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '0812', 'tanggal_lahir' => '1985-01-01']);
            $tagihan = Tagihan::create(['siswa_id' => $siswa->id, 'nama' => "SPP $awalan", 'periode' => '2026-11', 'jumlah' => 100000, 'jatuh_tempo' => today()]);
            Gallery::create(['author' => 'admin', 'foto' => "galeri-$awalan.jpg"]);
            Setting::put(['logo' => "storage/situs/logo-$awalan.png", 'nama_sekolah' => "Sekolah $awalan"]);

            $this->berkasPublik("storage/gallery/galeri-$awalan.jpg", "foto-$awalan");
            $this->berkasPublik("storage/situs/logo-$awalan.png", "logo-$awalan");
            $this->berkasPublik("storage/siswa/{$awalan}.siswa@uji.test/avatar-$awalan.png", "avatar-$awalan");

            return ['siswa' => $siswa, 'tagihan' => $tagihan, 'uSiswa' => $uSiswa];
        });
    }

    /** @return array{0: ZipArchive, 1: array} zip terbuka dan manifest */
    private function bukaZip(BackupSekolah $backup): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($backup->berkas)) === true, 'zip tidak bisa dibuka');

        return [$zip, json_decode($zip->getFromName('manifest.json'), true)];
    }

    private function tabelDi(ZipArchive $zip, string $tabel): array
    {
        $isi = $zip->getFromName("data/$tabel.json");
        $this->assertNotFalse($isi, "data/$tabel.json tidak ada di zip");

        return json_decode($isi, true);
    }

    private function buatBackup(Sekolah $s, string $jenis = 'manual'): BackupSekolah
    {
        $b = BackupSekolah::create(['sekolah_id' => $s->id, 'jenis' => $jenis, 'status' => 'berjalan']);

        return Pencadangan::kerjakan($b);
    }

    private function superAdmin(): User
    {
        return SekolahAktif::jalankan(null, function () {
            $u = User::factory()->create(['username' => 'super', 'sekolah_id' => null]);
            $u->setPeran([Peran::SUPER_ADMIN]);

            return $u;
        });
    }

    // ------------------------------------------------------------ isi backup

    public function test_backup_berisi_data_dan_berkas_sekolah_itu_saja(): void
    {
        $a = $this->isiSekolah($this->sekolah, 'alfa');
        $this->isiSekolah($this->b, 'beta');
        $this->berkasPublik('storage/gallery/tidak-dirujuk.jpg', 'yatim');   // ada di disk tetapi tidak dirujuk sekolah mana pun

        $backup = $this->buatBackup($this->sekolah);

        $this->assertSame('selesai', $backup->status);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($backup->berkas)), $backup->checksum);
        $this->assertGreaterThan(0, $backup->ukuran);
        [$zip, $manifest] = $this->bukaZip($backup);

        $this->assertSame(Pencadangan::FORMAT, $manifest['format']);
        $this->assertSame('Sekolah Uji', $manifest['sekolah']['nama']);
        $this->assertSame($backup->jumlah_tabel, count($manifest['tabel']));

        // data: hanya baris sekolah ini
        $siswa = $this->tabelDi($zip, 'siswa');
        $this->assertSame(['Siswa alfa'], array_column($siswa, 'nama_siswa'));
        $this->assertSame(['Guru alfa'], array_column($this->tabelDi($zip, 'guru'), 'nama_guru'));
        $this->assertSame(['SPP alfa'], array_column($this->tabelDi($zip, 'tagihan'), 'nama'));
        $this->assertSame(1, $manifest['tabel']['siswa']);
        $this->assertStringNotContainsString('beta', $zip->getFromName('data/users.json'));
        $this->assertSame(['alfa.guru@uji.test', 'alfa.siswa@uji.test'], collect($this->tabelDi($zip, 'users'))->pluck('email')->sort()->values()->all());

        // berkas: yang dirujuk sekolah ini saja
        $nama = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->all();
        $this->assertContains('berkas/storage/gallery/galeri-alfa.jpg', $nama);
        $this->assertContains('berkas/storage/situs/logo-alfa.png', $nama);
        $this->assertContains('berkas/storage/siswa/alfa.siswa@uji.test/avatar-alfa.png', $nama);
        $this->assertNotContains('berkas/storage/gallery/galeri-beta.jpg', $nama);
        $this->assertNotContains('berkas/storage/gallery/tidak-dirujuk.jpg', $nama);
        $this->assertSame('foto-alfa', $zip->getFromName('berkas/storage/gallery/galeri-alfa.jpg'));
        $this->assertSame(3, $backup->jumlah_berkas);
        $this->assertContains('BACA-SAYA.txt', $nama);
    }

    public function test_data_rahasia_tidak_ikut_diekspor(): void
    {
        $this->isiSekolah($this->sekolah, 'alfa');
        SekolahAktif::jalankan($this->sekolah, function () {
            PengaturanWa::milikSekolahAktif()->forceFill(['driver' => 'fonnte', 'token' => 'TOKEN-WA-RAHASIA', 'kunci2' => 'KUNCI2-RAHASIA'])->save();
            \App\PengaturanPembayaranSekolah::milikSekolahAktif()->forceFill(['driver' => 'midtrans', 'midtrans_server_key' => 'KUNCI-MIDTRANS-RAHASIA'])->save();
        });
        User::where('sekolah_id', $this->sekolah->id)->first()->forceFill(['remember_token' => 'TOKEN-INGAT-AKU', 'cek_password' => 'password-polos'])->save();
        DB::table('pendaftaran_sekolah')->insert(['token_hash' => 'x', 'email' => 'a@b.c', 'subdomain' => 'x', 'data' => '{"password":"HASH-ADMIN-BARU"}', 'kedaluwarsa_pada' => now(), 'sekolah_id' => $this->sekolah->id, 'created_at' => now(), 'updated_at' => now()]);

        $backup = $this->buatBackup($this->sekolah);
        [$zip] = $this->bukaZip($backup);

        $semua = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'data/')) {
                $semua .= $zip->getFromIndex($i);
            }
        }
        foreach (['TOKEN-WA-RAHASIA', 'KUNCI2-RAHASIA', 'KUNCI-MIDTRANS-RAHASIA', 'TOKEN-INGAT-AKU', 'password-polos', 'HASH-ADMIN-BARU', 'rahasia-siswa'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, $semua, $rahasia);
        }
        foreach ($this->tabelDi($zip, 'users') as $u) {
            foreach (['password', 'cek_password', 'remember_token'] as $kolom) {
                $this->assertArrayNotHasKey($kolom, $u);
            }
        }
        $this->assertArrayNotHasKey('domain_token', $this->tabelDi($zip, 'sekolah')[0]);
        $this->assertFalse($zip->getFromName('data/pendaftaran_sekolah.json'));   // tabel operasional platform
        $this->assertFalse($zip->getFromName('data/callback_gateway.json'));
        $this->assertNotFalse($zip->getFromName('data/pengaturan_wa_sekolah.json'));   // pengaturannya ikut, kuncinya tidak
        $this->assertArrayNotHasKey('token', $this->tabelDi($zip, 'pengaturan_wa_sekolah')[0]);
    }

    public function test_tabel_anak_ikut_lewat_induk_dan_tidak_membocorkan_sekolah_lain(): void
    {
        $a = $this->isiSekolah($this->sekolah, 'alfa');
        $this->isiSekolah($this->b, 'beta');
        $peranSiswa = Peran::where('kode', 'siswa')->value('id');
        $this->assertGreaterThan(0, DB::table('peran_user')->count());

        $trx = DB::table('transaksi_spp')->insertGetId(['sekolah_id' => $this->sekolah->id, 'driver' => 'fake', 'order_id' => 'SPP-A', 'subtotal' => 1, 'total' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('transaksi_spp_item')->insert(['transaksi_spp_id' => $trx, 'tagihan_id' => $a['tagihan']->id, 'jumlah' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $trxB = DB::table('transaksi_spp')->insertGetId(['sekolah_id' => $this->b->id, 'driver' => 'fake', 'order_id' => 'SPP-B', 'subtotal' => 1, 'total' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('transaksi_spp_item')->insert(['transaksi_spp_id' => $trxB, 'tagihan_id' => 999, 'jumlah' => 1, 'created_at' => now(), 'updated_at' => now()]);

        [$zip] = $this->bukaZip($this->buatBackup($this->sekolah));

        $this->assertCount(1, $this->tabelDi($zip, 'transaksi_spp_item'));
        $idUser = collect($this->tabelDi($zip, 'users'))->pluck('id')->all();
        $peran = $this->tabelDi($zip, 'peran_user');
        $this->assertNotEmpty($peran);
        foreach ($peran as $p) {
            $this->assertContains($p['user_id'], $idUser);   // hanya peran akun sekolah ini
        }
        $this->assertContains($peranSiswa, array_column($peran, 'peran_id'));
    }

    public function test_jalur_berkas_di_luar_folder_unggahan_diabaikan(): void
    {
        File::put(dirname($this->publik).'/rahasia-di-luar.txt', 'JANGAN-IKUT');
        SekolahAktif::jalankan($this->sekolah, function () {
            Gallery::create(['author' => 'x', 'foto' => '../../rahasia-di-luar.txt']);
            Setting::put(['logo' => '../rahasia-di-luar.txt']);
            Gallery::create(['author' => 'x', 'foto' => 'sah.jpg']);
        });
        $this->berkasPublik('storage/gallery/sah.jpg', 'sah');

        $berkas = BerkasSekolah::daftar($this->sekolah);

        $this->assertSame(['berkas/storage/gallery/sah.jpg'], array_values($berkas));
        File::delete(dirname($this->publik).'/rahasia-di-luar.txt');
    }

    public function test_lampiran_chat_ikut_dari_disk_privat(): void
    {
        Storage::disk('local')->put('chat/7/lampiran.txt', 'isi-lampiran');
        DB::table('chat_attachments')->insert(['message_id' => 1, 'path' => 'chat/7/lampiran.txt', 'nama_asli' => 'a.txt', 'mime' => 'text/plain', 'ukuran' => 12, 'sekolah_id' => $this->sekolah->id, 'created_at' => now(), 'updated_at' => now()]);

        $zip = $this->bukaZip($this->buatBackup($this->sekolah))[0];

        $this->assertSame('isi-lampiran', $zip->getFromName('berkas/chat/chat/7/lampiran.txt'));
    }

    public function test_backup_gagal_dicatat_tanpa_melempar_error(): void
    {
        $b = BackupSekolah::create(['sekolah_id' => 99999, 'jenis' => 'manual', 'status' => 'berjalan']);

        $hasil = Pencadangan::kerjakan($b);

        $this->assertSame('gagal', $hasil->status);
        $this->assertStringContainsString('Sekolah tidak ditemukan', $hasil->galat);
        $this->assertNull($hasil->berkas);
    }

    public function test_mulai_mengerjakan_lewat_antrian(): void
    {
        $this->isiSekolah($this->sekolah, 'alfa');

        $backup = Pencadangan::mulai($this->sekolah, 'manual', 5);   // antrian sync di tes

        $this->assertSame('selesai', $backup->fresh()->status);
        $this->assertSame(5, $backup->dibuat_oleh);
        $this->assertTrue($backup->fresh()->adaBerkas());
        $this->assertTrue(LogAktivitas::where('aksi', 'backup.selesai')->where('sekolah_id', $this->sekolah->id)->exists());
    }

    // ------------------------------------------------------------ jadwal, retensi, arsip

    public function test_retensi_hanya_menghapus_backup_terjadwal_lama(): void
    {
        $dibuat = [];
        foreach (range(1, 6) as $i) {
            $dibuat[] = $this->buatBackup($this->sekolah, 'terjadwal');
        }
        $manual = $this->buatBackup($this->sekolah, 'manual');
        $arsip = $this->buatBackup($this->sekolah, 'arsip');
        $lain = $this->buatBackup($this->b, 'terjadwal');

        $hapus = Pencadangan::bersihkanTerjadwal(4);

        $this->assertSame(2, $hapus);
        $this->assertSame(4, BackupSekolah::where('sekolah_id', $this->sekolah->id)->where('jenis', 'terjadwal')->count());
        $this->assertFalse(Storage::disk('local')->exists($dibuat[0]->berkas));  // paling lama hilang beserta berkasnya
        $this->assertFalse(Storage::disk('local')->exists($dibuat[1]->berkas));
        $this->assertTrue(Storage::disk('local')->exists($dibuat[5]->berkas));
        $this->assertTrue($manual->fresh()->adaBerkas());                          // manual dan arsip tidak disentuh
        $this->assertTrue($arsip->fresh()->adaBerkas());
        $this->assertTrue($lain->fresh()->adaBerkas());                            // batas dihitung per sekolah
    }

    public function test_perintah_backup_terjadwal_mengikuti_pengaturan_dan_melewati_yang_baru(): void
    {
        $mati = Sekolah::create(['nama' => 'Mati', 'subdomain' => 'mati', 'jenjang' => 'SMP', 'status' => 'nonaktif']);

        PengaturanPlatform::put(['backup_terjadwal_aktif' => '0']);
        $this->artisan('backup:sekolah')->assertSuccessful();
        $this->assertSame(0, BackupSekolah::count());

        PengaturanPlatform::put(['backup_terjadwal_aktif' => '1']);
        $this->artisan('backup:sekolah')->assertSuccessful();
        $this->assertSame(2, BackupSekolah::where('jenis', 'terjadwal')->count());           // Sekolah Uji + SMP Bunga
        $this->assertSame(0, BackupSekolah::where('sekolah_id', $mati->id)->count());          // sekolah nonaktif dilewati

        $this->artisan('backup:sekolah')->assertSuccessful();                                    // jeda 6 hari: tidak dobel
        $this->assertSame(2, BackupSekolah::count());

        $this->artisan('backup:sekolah', ['--sekolah' => 'bunga', '--jenis' => 'manual'])->assertSuccessful();
        $this->assertSame(1, BackupSekolah::where('sekolah_id', $this->b->id)->where('jenis', 'manual')->count());
    }

    public function test_sekolah_nonaktif_90_hari_diarsipkan_sekali_dan_tidak_dihapus(): void
    {
        $this->isiSekolah($this->b, 'beta');
        $this->b->update(['status' => 'nonaktif']);
        $this->assertNotNull($this->b->fresh()->dinonaktifkan_pada);

        Sekolah::whereKey($this->b->id)->update(['dinonaktifkan_pada' => now()->subDays(89)]);
        $this->artisan('sekolah:arsipkan')->assertSuccessful();
        $this->assertSame(0, BackupSekolah::count());                                           // baru 89 hari

        Sekolah::whereKey($this->b->id)->update(['dinonaktifkan_pada' => now()->subDays(91)]);
        $this->artisan('sekolah:arsipkan')->assertSuccessful();
        $arsip = BackupSekolah::where('jenis', 'arsip')->first();
        $this->assertSame('selesai', $arsip->status);
        $this->assertNotNull($this->b->fresh()->diarsipkan_pada);
        $this->assertSame('Siswa beta', DB::table('siswa')->where('sekolah_id', $this->b->id)->value('nama_siswa'));   // data tidak dihapus

        $this->artisan('sekolah:arsipkan')->assertSuccessful();                                  // tidak diarsipkan dua kali
        $this->assertSame(1, BackupSekolah::where('jenis', 'arsip')->count());

        // diaktifkan kembali: penanda dibersihkan
        $this->b->fresh()->update(['status' => 'aktif']);
        $this->assertNull($this->b->fresh()->dinonaktifkan_pada);
        $this->assertNull($this->b->fresh()->diarsipkan_pada);
    }

    // ------------------------------------------------------------ admin sekolah

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_membuat_dan_mengunduh_backup_sekolahnya_sendiri(): void
    {
        $this->isiSekolah($this->sekolah, 'alfa');
        $this->actingAs($this->admin());

        $this->get('/admin/backup')->assertOk()->assertSee('Backup Data')->assertSee('Belum ada backup')->assertSee('data pribadi');
        $this->post('/admin/backup')->assertRedirect('/admin/backup')->assertSessionHas('toast_success');

        $backup = BackupSekolah::first();
        $this->assertSame('manual', $backup->jenis);
        $this->assertSame('selesai', $backup->status);
        $this->get('/admin/backup')->assertOk()->assertSee('Unduh')->assertSee('Sisa kesempatan hari ini: 2');

        $res = $this->get("/admin/backup/{$backup->id}/unduh")->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.zip', $res->headers->get('Content-Disposition'));
        $this->assertSame('PK', substr($res->streamedContent(), 0, 2));   // berkas zip
    }

    public function test_admin_tidak_bisa_mengunduh_backup_sekolah_lain(): void
    {
        $lain = $this->buatBackup($this->b);
        $this->actingAs($this->admin());

        $this->get("/admin/backup/{$lain->id}/unduh")->assertNotFound();
        $this->get('/admin/backup')->assertOk()->assertDontSee("/backup/{$lain->id}/unduh");
    }

    public function test_pembuatan_manual_dibatasi_tiga_kali_sehari_dan_tidak_ganda(): void
    {
        $this->actingAs($this->admin());

        foreach (range(1, 3) as $i) {
            $this->post('/admin/backup')->assertSessionHas('toast_success');
        }
        $this->assertSame(3, BackupSekolah::count());
        $this->post('/admin/backup')->assertSessionHas('toast_error');
        $this->assertSame(3, BackupSekolah::count());

        BackupSekolah::query()->delete();
        BackupSekolah::create(['sekolah_id' => $this->sekolah->id, 'jenis' => 'manual', 'status' => 'berjalan']);
        $this->post('/admin/backup')->assertSessionHas('toast_error');   // yang sebelumnya masih berjalan
        $this->assertSame(1, BackupSekolah::count());
    }

    public function test_menu_backup_hanya_admin(): void
    {
        $backup = $this->buatBackup($this->sekolah);
        $this->actingAs(User::factory()->create(['role' => 'guru']));

        $this->get('/admin/backup')->assertRedirect();
        $this->post('/admin/backup')->assertRedirect();
        $this->get("/admin/backup/{$backup->id}/unduh")->assertRedirect();
        $this->assertSame(1, BackupSekolah::count());
    }

    // ------------------------------------------------------------ Super Admin

    public function test_super_admin_membuat_mengunduh_dan_menghapus_backup_semua_sekolah(): void
    {
        $this->isiSekolah($this->b, 'beta');
        $this->actingAs($this->superAdmin());

        $this->get('/platform/backup')->assertOk()->assertSee('Backup Sekolah')->assertSee('SMP Bunga');
        $this->post('/platform/backup', ['sekolah_id' => $this->b->id])->assertRedirect('/platform/backup');
        $this->post('/platform/backup', ['sekolah_id' => 9999])->assertSessionHasErrors('sekolah_id');

        $backup = BackupSekolah::first();
        $this->assertSame($this->b->id, $backup->sekolah_id);
        $this->assertSame(auth()->id(), $backup->dibuat_oleh);
        $this->get('/platform/backup')->assertSee('/unduh')->assertSee(auth()->user()->name);

        $this->get("/platform/backup/{$backup->id}/unduh")->assertOk();
        $this->assertTrue(LogAktivitas::where('aksi', 'backup.unduh')->exists());

        $this->delete("/platform/backup/{$backup->id}")->assertRedirect('/platform/backup');
        $this->assertSame(0, BackupSekolah::count());
        $this->assertFalse(Storage::disk('local')->exists($backup->berkas));
    }

    public function test_admin_sekolah_tidak_bisa_masuk_area_backup_platform(): void
    {
        $this->actingAs($this->admin());

        $this->get('/platform/backup')->assertRedirect();
        $this->post('/platform/backup', ['sekolah_id' => $this->b->id])->assertRedirect();
        $this->assertSame(0, BackupSekolah::count());
    }

    public function test_pengaturan_backup_terjadwal_di_pengaturan_platform(): void
    {
        $this->actingAs($this->superAdmin());
        $dasar = ['nama_produk' => 'SIAKAD', 'trial_hari' => 14, 'pembayaran_driver' => 'nonaktif'];

        $this->get('/platform/pengaturan')->assertOk()->assertSee('Backup sekolah');
        $this->put('/platform/pengaturan', $dasar + ['backup_simpan_jumlah' => 99])->assertSessionHasErrors('backup_simpan_jumlah');
        $this->put('/platform/pengaturan', $dasar + ['backup_simpan_jumlah' => 6, 'backup_terjadwal_aktif' => 1])->assertRedirect('/platform/pengaturan');

        $this->assertTrue(PengaturanPlatform::aktif('backup_terjadwal_aktif'));
        $this->assertSame('6', PengaturanPlatform::get('backup_simpan_jumlah'));
        $this->put('/platform/pengaturan', $dasar + ['backup_simpan_jumlah' => 6]);
        $this->assertFalse(PengaturanPlatform::aktif('backup_terjadwal_aktif'));
    }

    // ------------------------------------------------------------ pemantauan

    public function test_pemantauan_menghitung_penggunaan_per_sekolah(): void
    {
        $this->isiSekolah($this->sekolah, 'alfa');
        $this->isiSekolah($this->b, 'beta');
        SekolahAktif::jalankan($this->b, function () {
            $k = Kelas::first();
            foreach (['Dua', 'Tiga'] as $n) {
                Siswa::create(['user_id' => User::factory()->create(['role' => 'siswa'])->id, 'nisn' => fake()->unique()->numerify('##########'), 'nama_siswa' => $n, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $k->id]);
            }
            Siswa::create(['user_id' => User::factory()->create(['role' => 'siswa'])->id, 'nisn' => fake()->unique()->numerify('##########'), 'nama_siswa' => 'Lulus', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2005-01-01', 'kelas_id' => $k->id, 'status' => 'lulus']);
        });
        DB::table('api_request_log')->insert([
            ['sekolah_id' => $this->b->id, 'tanggal' => today()->toDateString(), 'jumlah' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['sekolah_id' => $this->b->id, 'tanggal' => today()->subDays(10)->toDateString(), 'jumlah' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['sekolah_id' => $this->b->id, 'tanggal' => today()->subDays(45)->toDateString(), 'jumlah' => 9999, 'created_at' => now(), 'updated_at' => now()], // di luar 30 hari
        ]);
        User::withoutGlobalScopes()->where('sekolah_id', $this->b->id)->first()->forceFill(['login_terakhir_at' => now()->subHours(3)])->save();

        $semua = Pemantauan::semua()->keyBy(fn ($r) => $r['sekolah']->subdomain);
        $beta = $semua['bunga'];

        $this->assertSame(3, $beta['siswa']);              // yang lulus tidak dihitung
        $this->assertSame(1, $beta['guru']);
        $this->assertSame(5, $beta['pengguna']);           // siswa + guru dari isiSekolah, lalu 2 siswa aktif dan 1 siswa lulus
        $this->assertSame(140, $beta['api_30_hari']);
        $this->assertSame(40, $beta['api_hari_ini']);
        $this->assertNotNull($beta['login_terakhir']);
        $this->assertSame('tanpa_batas', $beta['status_sewa']);
        $this->assertSame(strlen('foto-beta') + strlen('logo-beta') + strlen('avatar-beta'), $beta['ukuran']);
        $this->assertSame(1, $semua['uji']['siswa']);
        $this->assertSame(0, $semua['uji']['api_30_hari']);
        $this->assertNull($semua['uji']['login_terakhir']);
    }

    public function test_pemantauan_menampilkan_status_langganan_dan_backup_terakhir(): void
    {
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'pro')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addDays(20), 'status' => 'aktif']);
        $this->buatBackup($this->sekolah);

        $r = Pemantauan::semua()->first(fn ($x) => $x['sekolah']->id === $this->sekolah->id);

        $this->assertSame('aktif', $r['status_sewa']);
        $this->assertSame('Pro', $r['paket']);
        $this->assertNotNull($r['backup_terakhir']);
    }

    public function test_halaman_pemantauan_dan_filter(): void
    {
        $this->b->update(['status' => 'nonaktif']);
        $this->actingAs($this->superAdmin());

        $this->get('/platform/pemantauan')->assertOk()->assertSee('Pemantauan Sekolah')->assertSee('Sekolah Uji')->assertSee('SMP Bunga')->assertSee('Penyimpanan berkas');
        $this->get('/platform/pemantauan?status=nonaktif')->assertOk()->assertSee('SMP Bunga')->assertDontSee('Sekolah Uji');
        $this->get('/platform/pemantauan?cari=bung')->assertOk()->assertSee('SMP Bunga')->assertDontSee('Sekolah Uji');
        $this->post('/platform/pemantauan/segarkan')->assertRedirect('/platform/pemantauan');
    }

    public function test_pemantauan_hanya_super_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/platform/pemantauan')->assertRedirect();
        $this->post('/platform/pemantauan/segarkan')->assertRedirect();
    }

    public function test_ukuran_teks_mudah_dibaca(): void
    {
        $this->assertSame('-', Pemantauan::ukuranTeks(null));
        $this->assertSame('0 B', Pemantauan::ukuranTeks(0));
        $this->assertSame('512 B', Pemantauan::ukuranTeks(512));
        $this->assertSame('1,5 KB', Pemantauan::ukuranTeks(1536));
        $this->assertSame('2,0 MB', Pemantauan::ukuranTeks(2 * 1024 * 1024));
    }

    public function test_login_terakhir_dicatat_saat_masuk(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'username' => 'adminuji', 'password' => 'rahasia123']);
        $this->assertNull($user->login_terakhir_at);

        $this->post('/login', ['username' => 'adminuji', 'password' => 'rahasia123'])->assertRedirect();

        $this->assertNotNull($user->fresh()->login_terakhir_at);
        $this->assertTrue(LogAktivitas::where('aksi', 'auth.login')->where('user_id', $user->id)->where('sekolah_id', $this->sekolah->id)->exists());
        $this->assertNotNull(Pemantauan::semua()->first(fn ($r) => $r['sekolah']->id === $this->sekolah->id)['login_terakhir']);
    }

    // ------------------------------------------------------------ log aktivitas

    private function isiLog(): void
    {
        $uA = SekolahAktif::jalankan($this->sekolah, fn () => User::factory()->create(['role' => 'admin', 'name' => 'Budi Admin', 'username' => 'budi']));
        $uB = SekolahAktif::jalankan($this->b, fn () => User::factory()->create(['role' => 'admin', 'name' => 'Citra Admin', 'username' => 'citra']));
        $buat = fn (array $d) => tap(LogAktivitas::create($d), fn ($l) => $l->forceFill(['created_at' => $d['created_at']])->save());

        $buat(['user_id' => $uA->id, 'sekolah_id' => $this->sekolah->id, 'aksi' => 'keuangan.bayar', 'deskripsi' => 'Bayar SPP A', 'created_at' => Carbon::parse('2026-11-01 10:00')]);
        $buat(['user_id' => $uA->id, 'sekolah_id' => $this->sekolah->id, 'aksi' => 'auth.login', 'deskripsi' => 'Masuk A', 'created_at' => Carbon::parse('2026-11-05 08:00')]);
        $buat(['user_id' => $uB->id, 'sekolah_id' => $this->b->id, 'aksi' => 'keuangan.batal_bayar', 'deskripsi' => 'Batal bayar B', 'created_at' => Carbon::parse('2026-11-10 09:00')]);
        $buat(['user_id' => null, 'sekolah_id' => null, 'aksi' => 'platform.pengaturan', 'deskripsi' => 'Pengaturan platform', 'created_at' => Carbon::parse('2026-11-12 12:00')]);
    }

    private function logData(array $filter = []): array
    {
        return $this->getJson('/platform/log/data?'.http_build_query($filter + ['draw' => 1, 'start' => 0, 'length' => 50]))->assertOk()->json();
    }

    public function test_log_dapat_disaring_menurut_sekolah_pengguna_aksi_dan_tanggal(): void
    {
        $this->isiLog();
        $this->actingAs($this->superAdmin());
        $deskripsi = fn (array $r) => collect($r['data'])->pluck('deskripsi')->sort()->values()->all();

        $semua = $this->logData();
        $this->assertGreaterThanOrEqual(4, $semua['recordsTotal']);

        $this->assertSame(['Bayar SPP A', 'Masuk A'], $deskripsi($this->logData(['sekolah_id' => $this->sekolah->id, 'dari' => '2026-11-01', 'sampai' => '2026-11-30'])));
        $this->assertSame(['Batal bayar B'], $deskripsi($this->logData(['sekolah_id' => $this->b->id])));
        $this->assertSame(['Pengaturan platform'], $deskripsi($this->logData(['sekolah_id' => 'platform', 'dari' => '2026-11-01'])));
        $this->assertSame(['Batal bayar B'], $deskripsi($this->logData(['pengguna' => 'citra'])));
        $this->assertSame(['Bayar SPP A', 'Masuk A'], $deskripsi($this->logData(['pengguna' => 'Budi'])));
        $this->assertSame(['Batal bayar B', 'Bayar SPP A'], $deskripsi($this->logData(['aksi' => 'keuangan', 'dari' => '2026-11-01', 'sampai' => '2026-11-30'])));
        $this->assertSame(['Bayar SPP A'], $deskripsi($this->logData(['aksi' => 'keuangan.bayar'])));
        $this->assertSame(['Masuk A'], $deskripsi($this->logData(['dari' => '2026-11-04', 'sampai' => '2026-11-06'])));
        $this->assertSame([], $deskripsi($this->logData(['pengguna' => 'tidak-ada-orang-ini'])));
        $this->assertSame($semua['recordsTotal'], $this->logData(['dari' => 'bukan tanggal'])['recordsTotal']);   // filter rusak diabaikan
    }

    public function test_log_menampilkan_pengguna_dan_sekolah_dan_halaman_memuat_pilihan_filter(): void
    {
        $this->isiLog();
        $this->actingAs($this->superAdmin());

        $baris = collect($this->logData(['aksi' => 'keuangan.batal_bayar'])['data'])->first();
        $this->assertSame('Citra Admin (citra)', $baris['pengguna']);
        $this->assertSame('SMP Bunga', $baris['sekolah']);
        $this->assertSame('Platform', collect($this->logData(['sekolah_id' => 'platform'])['data'])->first()['sekolah']);

        $this->get('/platform/log')->assertOk()->assertSee('SMP Bunga')->assertSee('Platform saja')->assertSee('Unduh CSV')->assertSee('Keuangan sekolah')->assertSee('Masuk/keluar');
    }

    public function test_log_dapat_diunduh_sebagai_csv_sesuai_filter(): void
    {
        $this->isiLog();
        $this->actingAs($this->superAdmin());

        $res = $this->get('/platform/log/ekspor?'.http_build_query(['sekolah_id' => $this->sekolah->id]))->assertOk();
        $isi = $res->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
        $this->assertStringContainsString('Waktu,Pengguna,Sekolah,Aksi,Deskripsi,IP', $isi);
        $this->assertStringContainsString('Bayar SPP A', $isi);
        $this->assertStringContainsString('Budi Admin (budi)', $isi);
        $this->assertStringNotContainsString('Batal bayar B', $isi);
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('log-aktivitas-', $res->headers->get('Content-Disposition'));
    }

    public function test_log_hanya_super_admin(): void
    {
        $this->actingAs($this->admin());

        $this->getJson('/platform/log/data')->assertStatus(403);
        $this->get('/platform/log/ekspor')->assertRedirect();
    }

    public function test_tindakan_backup_dan_keuangan_tercatat_di_log(): void
    {
        $this->actingAs($this->admin());
        JenisBiaya::create(['nama' => 'SPP', 'nominal' => 1000, 'jenis' => 'bulanan']);

        $this->post('/admin/backup');

        $aksi = LogAktivitas::pluck('aksi')->all();
        $this->assertContains('backup.selesai', $aksi);
    }
}
