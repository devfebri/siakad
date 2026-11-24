<?php

namespace Tests\Feature;

use App\Langganan;
use App\Paket;
use App\Peran;
use App\Sekolah;
use App\Setting;
use App\Support\AksesSekolah;
use App\Support\DomainDns;
use App\Support\DomainKustom;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tahap 3 poin 7: domain kustom per sekolah dengan verifikasi DNS (TXT), pengenalan tenant, dan endpoint izin SSL. */
class DomainKustomTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<string, array<int, string>>> catatan DNS palsu: [jenis][host] => nilai */
    private array $dns = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['sekolah.domain_utama' => 'siakad.test', 'sekolah.server_ip' => '203.0.113.10', 'sekolah.cname_target' => null]);
        DomainDns::$palsu = fn (string $jenis, string $host) => $this->dns[$jenis][$host] ?? [];
    }

    protected function tearDown(): void
    {
        DomainDns::$palsu = null;
        parent::tearDown();
    }

    private function sekolahB(?string $domain = 'sekolahb.sch.id', bool $terverifikasi = false): Sekolah
    {
        $b = Sekolah::create(['nama' => 'SMP Bunga', 'subdomain' => 'bunga', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        SekolahAktif::jalankan($b, fn () => Setting::put(['nama_sekolah' => 'SMP Bunga Mekar']));
        if ($domain) {
            DomainKustom::klaim($b, $domain);
            if ($terverifikasi) {
                $b->forceFill(['domain_terverifikasi_pada' => now()])->save();
            }
        }

        return $b->fresh();
    }

    private function adminDi(Sekolah $s): User
    {
        return SekolahAktif::jalankan($s, fn () => User::factory()->create(['role' => 'admin']));
    }

    private function pasangTxt(Sekolah $s): void
    {
        $i = DomainKustom::instruksi($s->fresh());
        $this->dns['TXT'][$i['txt_host']] = [$i['txt_nilai']];
    }

    // ------------------------------------------------------------ pengenalan sekolah

    public function test_domain_belum_terverifikasi_tidak_mengenali_sekolah_dan_setelah_verifikasi_ya(): void
    {
        config(['sekolah.default_subdomain' => null, 'sekolah.izinkan_query' => false]);
        $b = $this->sekolahB();

        $this->get('http://sekolahb.sch.id/')->assertNotFound()->assertSee('Sekolah tidak ditemukan');

        $this->pasangTxt($b);
        $this->assertTrue(DomainKustom::periksa($b)['terverifikasi']);

        $this->get('http://sekolahb.sch.id/')->assertOk()->assertSee('SMP Bunga Mekar');
        $this->get('http://www.sekolahb.sch.id/')->assertOk()->assertSee('SMP Bunga Mekar');   // "www." ikut dikenali
        $this->get('http://orang-lain.sch.id/')->assertNotFound();
    }

    public function test_login_di_domain_kustom_masuk_ke_sekolah_yang_benar(): void
    {
        config(['sekolah.default_subdomain' => null, 'sekolah.izinkan_query' => false]);
        $b = $this->sekolahB('sekolahb.sch.id', true);
        $admin = SekolahAktif::jalankan($b, fn () => User::factory()->create(['role' => 'admin', 'username' => 'adminbunga', 'password' => 'rahasia123']));

        $this->post('http://sekolahb.sch.id/login', ['username' => 'adminbunga', 'password' => 'rahasia123'])->assertRedirect(route('admin/dashboard'));
        $this->assertAuthenticatedAs($admin);

        // akun sekolah B tidak bisa masuk lewat domain sekolah lain
        auth()->logout();
        $this->post('http://uji.siakad.test/login', ['username' => 'adminbunga', 'password' => 'rahasia123'])->assertSessionHasErrors('username');
    }

    public function test_alamat_sekolah_memakai_domain_hanya_bila_terverifikasi(): void
    {
        $b = $this->sekolahB();
        $this->assertNull($b->domainAktif());
        $this->assertStringContainsString('bunga.siakad.test', $b->url('/login'));

        $b->forceFill(['domain_terverifikasi_pada' => now()])->save();
        $this->assertSame('sekolahb.sch.id', $b->fresh()->domainAktif());
        $this->assertSame('http://sekolahb.sch.id/login', $b->fresh()->url('/login'));
    }

    // ------------------------------------------------------------ verifikasi DNS

    public function test_verifikasi_dns_txt_dan_arah_lalu_lintas(): void
    {
        $b = $this->sekolahB();
        $i = DomainKustom::instruksi($b);
        $this->assertSame('_siakad-verifikasi.sekolahb.sch.id', $i['txt_host']);
        $this->assertSame('siakad-verifikasi='.$b->domain_token, $i['txt_nilai']);
        $this->assertSame('bunga.siakad.test', $i['cname_target']);       // turunan domain utama
        $this->assertSame('203.0.113.10', $i['a_target']);

        // belum ada rekaman
        $hasil = DomainKustom::periksa($b);
        $this->assertFalse($hasil['terverifikasi']);
        $this->assertFalse($hasil['txt']);

        // TXT salah isi (token milik sekolah lain) tidak cukup
        $this->dns['TXT'][$i['txt_host']] = ['siakad-verifikasi=token-orang-lain'];
        $this->assertFalse(DomainKustom::periksa($b->fresh())['terverifikasi']);

        // TXT benar, tetapi belum mengarah ke server: terverifikasi dengan peringatan
        $this->pasangTxt($b);
        $hasil = DomainKustom::periksa($b->fresh());
        $this->assertTrue($hasil['terverifikasi']);
        $this->assertFalse($hasil['mengarah']);
        $this->assertStringContainsString('belum mengarah', $hasil['pesan']);

        // CNAME ke target, atau A ke IP server = mengarah
        $this->dns['CNAME']['sekolahb.sch.id'] = ['Bunga.Siakad.Test.'];
        $this->assertTrue(DomainKustom::periksa($b->fresh())['mengarah']);
        $this->dns['CNAME'] = [];
        $this->dns['A']['sekolahb.sch.id'] = ['203.0.113.10'];
        $this->assertTrue(DomainKustom::periksa($b->fresh())['mengarah']);
        $this->assertSame('Domain terverifikasi dan siap dipakai.', DomainKustom::periksa($b->fresh())['pesan']);
    }

    public function test_dua_sekolah_mengklaim_domain_sama_yang_terbukti_lebih_dulu_yang_berhak(): void
    {
        $a = $this->sekolah;
        $b = $this->sekolahB('sama.sch.id');
        // klaim kedua melepaskan klaim pertama yang belum terbukti
        DomainKustom::klaim($a, 'sama.sch.id');
        $this->assertNull($b->fresh()->domain_kustom);
        $this->assertSame('sama.sch.id', $a->fresh()->domain_kustom);

        $this->pasangTxt($a);
        $this->assertTrue(DomainKustom::periksa($a->fresh())['terverifikasi']);

        // setelah terbukti, tidak bisa direbut
        $this->expectException(\InvalidArgumentException::class);
        DomainKustom::klaim($b, 'sama.sch.id');
    }

    public function test_domain_diganti_mengulang_verifikasi_dan_domain_sama_tidak(): void
    {
        $b = $this->sekolahB('sekolahb.sch.id', true);
        $tokenLama = $b->domain_token;

        DomainKustom::klaim($b, 'WWW.SekolahB.sch.id'); // sama setelah dinormalkan
        $this->assertNotNull($b->fresh()->domain_terverifikasi_pada);
        $this->assertSame($tokenLama, $b->fresh()->domain_token);

        DomainKustom::klaim($b, 'baru.sch.id');
        $this->assertNull($b->fresh()->domain_terverifikasi_pada);
        $this->assertNotSame($tokenLama, $b->fresh()->domain_token);

        DomainKustom::klaim($b, null);
        $this->assertNull($b->fresh()->domain_kustom);
        $this->assertNull(DomainKustom::instruksi($b->fresh()));
    }

    public function test_domain_tidak_sah_ditolak(): void
    {
        foreach (['http://sekolah.sch.id', 'sekolah', 'sekolah.sch.id/halaman', '192.168.1.5', 'siakad.test', 'foo.siakad.test', 'spasi sekolah.sch.id', '-salah.sch.id', str_repeat('a', 160).'.sch.id'] as $tidakSah) {
            $this->assertNotNull(DomainKustom::galat($tidakSah, $this->sekolah), $tidakSah);
        }
        foreach (['sekolahku.sch.id', 'www.sekolahku.sch.id', 'smp1.jambi.sch.id', 'xn--sekolah-abc.id'] as $sah) {
            $this->assertNull(DomainKustom::galat($sah, $this->sekolah), $sah);
        }
        $this->assertNull(DomainKustom::galat('', $this->sekolah)); // kosong = hapus
    }

    // ------------------------------------------------------------ menu admin sekolah

    public function test_admin_mendaftarkan_domain_melihat_instruksi_dan_mengecek(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin/domain')->assertOk()->assertSee('Domain Website')->assertDontSee('Menunggu verifikasi');

        $this->post('/admin/domain', ['domain' => 'WWW.SekolahKu.sch.id'])->assertRedirect('/admin/domain');
        $sekolah = $this->sekolah->fresh();
        $this->assertSame('sekolahku.sch.id', $sekolah->domain_kustom);   // dinormalkan: huruf kecil, tanpa www
        $this->assertNull($sekolah->domain_terverifikasi_pada);

        $this->get('/admin/domain')->assertOk()->assertSee('Menunggu verifikasi')
            ->assertSee('_siakad-verifikasi.sekolahku.sch.id')->assertSee('siakad-verifikasi='.$sekolah->domain_token)
            ->assertSee('uji.siakad.test')->assertSee('203.0.113.10');

        $this->post('/admin/domain/periksa')->assertRedirect('/admin/domain');
        $this->get('/admin/domain')->assertSee('belum ditemukan');

        $this->pasangTxt($sekolah);
        $this->post('/admin/domain/periksa');
        $this->get('/admin/domain')->assertOk()->assertSee('Terverifikasi')->assertSee('ditemukan');
        $this->assertNotNull($sekolah->fresh()->domain_terverifikasi_pada);

        $this->delete('/admin/domain')->assertRedirect('/admin/domain');
        $this->assertNull($sekolah->fresh()->domain_kustom);
    }

    public function test_admin_tidak_bisa_memakai_domain_tidak_sah_atau_milik_sekolah_lain(): void
    {
        $this->sekolahB('milikb.sch.id', true);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post('/admin/domain', ['domain' => ''])->assertSessionHasErrors('domain');
        $this->post('/admin/domain', ['domain' => 'http://x.sch.id'])->assertSessionHasErrors('domain');
        $this->post('/admin/domain', ['domain' => 'milikb.sch.id'])->assertSessionHasErrors('domain');   // sudah terverifikasi sekolah lain
        $this->post('/admin/domain', ['domain' => 'sub.siakad.test'])->assertSessionHasErrors('domain'); // milik platform
        $this->assertNull($this->sekolah->fresh()->domain_kustom);
    }

    public function test_menu_domain_hanya_admin_dan_tersedia_di_semua_paket(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/admin/domain')->assertRedirect();
        $this->post('/admin/domain', ['domain' => 'guru.sch.id'])->assertRedirect();
        $this->assertNull($this->sekolah->fresh()->domain_kustom);

        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'basic')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/domain')->assertOk();
    }

    // ------------------------------------------------------------ Super Admin

    private function superAdmin(): User
    {
        return SekolahAktif::jalankan(null, function () {
            $u = User::factory()->create(['username' => 'super', 'sekolah_id' => null]);
            $u->setPeran([Peran::SUPER_ADMIN]);

            return $u;
        });
    }

    public function test_super_admin_mengisi_domain_mengecek_dan_menandai_manual(): void
    {
        $b = $this->sekolahB(null);
        $this->actingAs($this->superAdmin());
        $isi = fn (array $ubah = []) => $ubah + ['nama' => 'SMP Bunga', 'jenjang' => 'SMP', 'subdomain' => 'bunga', 'status' => 'aktif', 'domain_kustom' => 'WWW.Bunga-Sekolah.sch.id'];

        $this->putJson("/platform/sekolah/{$b->id}", $isi(['domain_kustom' => 'http://salah']))->assertStatus(422)->assertJsonValidationErrors('domain_kustom');
        $this->putJson("/platform/sekolah/{$b->id}", $isi())->assertOk();
        $this->assertSame('bunga-sekolah.sch.id', $b->fresh()->domain_kustom);
        $this->assertNull($b->fresh()->domain_terverifikasi_pada);       // diisi Super Admin pun tetap perlu bukti

        $this->get("/platform/sekolah/{$b->id}")->assertOk()->assertSee('Domain kustom')->assertSee('Menunggu verifikasi')->assertSee('_siakad-verifikasi.bunga-sekolah.sch.id');
        $this->post("/platform/sekolah/{$b->id}/domain/periksa")->assertRedirect("/platform/sekolah/{$b->id}");
        $this->assertNull($b->fresh()->domain_terverifikasi_pada);

        $this->post("/platform/sekolah/{$b->id}/domain/manual")->assertRedirect();
        $this->assertNotNull($b->fresh()->domain_terverifikasi_pada);
        $this->assertSame('http://bunga-sekolah.sch.id/', $b->fresh()->url('/'));

        // menyimpan form lagi tanpa mengubah domain tidak membuang verifikasi
        $this->putJson("/platform/sekolah/{$b->id}", $isi())->assertOk();
        $this->assertNotNull($b->fresh()->domain_terverifikasi_pada);

        $this->delete("/platform/sekolah/{$b->id}/domain")->assertRedirect();
        $this->assertNull($b->fresh()->domain_kustom);
    }

    public function test_pembuatan_sekolah_oleh_super_admin_dengan_domain_menunggu_verifikasi(): void
    {
        $this->actingAs($this->superAdmin());

        $this->postJson('/platform/sekolah', [
            'nama' => 'SMA Baru', 'jenjang' => 'SMA', 'subdomain' => 'baru', 'domain_kustom' => 'sman-baru.sch.id', 'status' => 'aktif',
            'admin_name' => 'Admin', 'admin_username' => 'adminbaru', 'admin_email' => 'a@baru.test', 'admin_password' => 'rahasia1',
        ])->assertCreated();

        $baru = Sekolah::where('subdomain', 'baru')->first();
        $this->assertSame('sman-baru.sch.id', $baru->domain_kustom);
        $this->assertNull($baru->domain_terverifikasi_pada);
        $this->assertNotEmpty($baru->domain_token);
    }

    public function test_admin_sekolah_tidak_bisa_memakai_aksi_domain_super_admin(): void
    {
        $b = $this->sekolahB();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->post("/platform/sekolah/{$b->id}/domain/manual")->assertRedirect(); // bukan area admin sekolah
        $this->assertNull($b->fresh()->domain_terverifikasi_pada);
    }

    // ------------------------------------------------------------ endpoint izin SSL dan pemeriksaan otomatis

    public function test_endpoint_izin_ssl_hanya_untuk_domain_terdaftar_dan_terverifikasi(): void
    {
        $this->sekolahB('benar.sch.id', true);
        $belum = Sekolah::create(['nama' => 'Belum', 'subdomain' => 'belum', 'jenjang' => 'SMP', 'status' => 'aktif']);
        DomainKustom::klaim($belum, 'belum.sch.id');
        $mati = Sekolah::create(['nama' => 'Mati', 'subdomain' => 'mati', 'jenjang' => 'SMP', 'status' => 'nonaktif']);
        DomainKustom::klaim($mati, 'mati.sch.id');
        $mati->forceFill(['domain_terverifikasi_pada' => now()])->save();

        $this->get('/_domain/izin?domain=benar.sch.id')->assertOk()->assertContent('');
        $this->get('/_domain/izin?domain=www.benar.sch.id')->assertOk();
        $this->get('/_domain/izin?domain=siakad.test')->assertOk();            // domain utama platform
        $this->get('/_domain/izin?domain=bunga.siakad.test')->assertOk();      // subdomain sekolah
        $this->get('/_domain/izin?domain=belum.sch.id')->assertNotFound();      // belum terbukti
        $this->get('/_domain/izin?domain=mati.sch.id')->assertNotFound();       // sekolah nonaktif
        $this->get('/_domain/izin?domain=penyerang.com')->assertNotFound();
        $this->get('/_domain/izin')->assertNotFound();
    }

    public function test_perintah_periksa_otomatis_memverifikasi_domain_yang_menunggu(): void
    {
        $b = $this->sekolahB('otomatis.sch.id');
        $lama = Sekolah::create(['nama' => 'Lama', 'subdomain' => 'lama', 'jenjang' => 'SMP', 'status' => 'aktif']);
        DomainKustom::klaim($lama, 'lama.sch.id');
        Sekolah::whereKey($lama->id)->update(['updated_at' => now()->subDays(10)]);     // didaftarkan lebih dari 7 hari lalu: tidak dicek lagi
        $this->pasangTxt($b);
        $this->pasangTxt($lama);

        $this->artisan('domain:periksa')->assertSuccessful();

        $this->assertNotNull($b->fresh()->domain_terverifikasi_pada);
        $this->assertNull($lama->fresh()->domain_terverifikasi_pada);
    }
}
