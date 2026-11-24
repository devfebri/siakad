<?php

namespace Tests\Feature;

use App\Invoice;
use App\Kelas;
use App\Langganan;
use App\Mail\LanggananMail;
use App\Paket;
use App\Sekolah;
use App\Support\AksesSekolah;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Aturan langganan: masa tenggang, mode baca-saja, modul per paket, Langganan Saya, dan pengingat H-30/H-7/H-1. */
class LanggananTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));

        // sekolah uji kini dibatasi langganan; admin dibuat sebelum langganan diatur
        $this->sekolah->update(['tanpa_batas' => false]);
        $this->admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@uji.test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function langganan(string $paket, string $berakhir, ?Sekolah $sekolah = null): Langganan
    {
        AksesSekolah::lupakan();

        return Langganan::create([
            'sekolah_id' => ($sekolah ?? $this->sekolah)->id, 'paket_id' => Paket::where('kode', $paket)->value('id'), 'durasi_bulan' => 1,
            'mulai' => Carbon::parse($berakhir)->subMonth()->toDateString(), 'berakhir' => $berakhir, 'status' => 'aktif',
        ]);
    }

    private function masuk(): void
    {
        $this->actingAs($this->admin);
        AksesSekolah::lupakan();
    }

    // ------------------------------------------------------------ status dan banner

    public function test_langganan_aktif_berjalan_normal_tanpa_banner(): void
    {
        $this->langganan('pro', '2026-12-31');
        $this->masuk();

        $this->get('/admin/dashboard')->assertOk()->assertDontSee('Masa tenggang')->assertDontSee('mode baca-saja');
        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A'])->assertCreated();
        $this->assertSame(['aktif', false], [AksesSekolah::ambil()['status'], AksesSekolah::ambil()['baca_saja']]);
    }

    public function test_banner_pengingat_muncul_seminggu_sebelum_berakhir(): void
    {
        $this->langganan('pro', '2026-11-10'); // 6 hari lagi
        $this->masuk();

        $this->get('/admin/dashboard')->assertOk()->assertSee('Langganan berakhir 10 November 2026')->assertSee('6 hari lagi')->assertSee('Langganan Saya');
    }

    public function test_masa_tenggang_masih_bisa_mengubah_data_dengan_banner_peringatan(): void
    {
        $this->langganan('pro', '2026-11-03'); // berakhir kemarin
        $this->masuk();

        $this->assertSame('tenggang', AksesSekolah::ambil()['status']);
        $this->get('/admin/dashboard')->assertOk()->assertSee('Masa tenggang sampai 10 November 2026');
        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A'])->assertCreated();
    }

    public function test_setelah_tenggang_situs_baca_saja_dan_perubahan_ditolak_dengan_pesan_jelas(): void
    {
        $this->langganan('pro', '2026-10-20');
        $kelas = Kelas::create(['nama_kelas' => 'X-A']);
        $this->masuk();

        $this->assertSame('baca_saja', AksesSekolah::ambil()['status']);
        $this->get('/admin/dashboard')->assertOk()->assertSee('mode baca-saja');
        $this->get('/admin/kelas')->assertOk();
        $this->getJson('/admin/kelas/data?draw=1&start=0&length=10')->assertOk();

        $pesan = $this->postJson('/admin/kelas', ['nama_kelas' => 'X-B'])->assertForbidden()->assertJsonPath('baca_saja', true)->json('message');
        $this->assertStringContainsString('20 Oktober 2026', $pesan);
        $this->assertStringContainsString('baca-saja', $pesan);
        $this->putJson("/admin/kelas/{$kelas->id}", ['nama_kelas' => 'X-Z'])->assertForbidden();
        $this->deleteJson("/admin/kelas/{$kelas->id}")->assertForbidden();
        $this->assertSame('X-A', $kelas->fresh()->nama_kelas);
        $this->assertSame(1, Kelas::count());

        // permintaan halaman biasa: kembali dengan pesan, bukan error mentah
        $this->from('/admin/kelas')->post('/admin/kelas', ['nama_kelas' => 'X-B'])->assertRedirect('/admin/kelas')->assertSessionHas('toast_error');
    }

    public function test_tanpa_langganan_sama_dengan_baca_saja_dan_modul_berbayar_terkunci(): void
    {
        $this->masuk();

        $this->assertSame('belum_ada', AksesSekolah::ambil()['status']);
        $pesan = $this->postJson('/admin/kelas', ['nama_kelas' => 'X'])->assertForbidden()->json('message');
        $this->assertStringContainsString('belum memiliki langganan', $pesan);
        $this->get('/admin/dashboard')->assertOk()->assertSee('belum memiliki langganan');
        $this->get('/admin/absensi')->assertRedirect(route('admin/dashboard'))->assertSessionHas('toast_error');
    }

    public function test_sekolah_tanpa_batas_tidak_pernah_dibatasi(): void
    {
        $this->sekolah->update(['tanpa_batas' => true]);
        $this->langganan('basic', '2020-01-01');
        $this->masuk();

        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A'])->assertCreated();
        $this->get('/admin/ppdb')->assertOk();
        $this->assertTrue(AksesSekolah::ambil()['bebas']);
        $this->assertTrue(AksesSekolah::modul('portal_orang_tua'));
        $this->get('/admin/dashboard')->assertDontSee('baca-saja');
    }

    public function test_pengecualian_saat_baca_saja_login_pengunjung_dan_pengajuan(): void
    {
        $this->langganan('pro', '2026-10-01');

        // login/logout tetap bisa
        $this->post('/login', ['username' => $this->admin->username, 'password' => 'password'])->assertSessionDoesntHaveErrors();
        $this->post('/logout')->assertRedirect($this->sekolah->url('/'));

        // formulir pengunjung: PPDB dan chat
        \App\Setting::put(['ppdb_aktif' => '1', 'ppdb_tahun_ajaran' => '2026/2027', 'ppdb_buka' => '2026-01-01 00:00:00', 'ppdb_tutup' => '2027-01-01 00:00:00']);
        $this->postJson('/chat/start', ['nama' => 'Tamu', 'email' => 't@c.test', 'topik' => \App\ChatSession::TOPIK[0], 'persetujuan' => 1])->assertCreated();
        $this->get('/ppdb')->assertOk();

        // admin: pengajuan perpanjangan tetap bisa, perubahan lain tidak
        $this->masuk();
        $pro = Paket::where('kode', 'pro')->first();
        $this->postJson('/admin/langganan/ajukan', ['paket_id' => $pro->id, 'durasi_bulan' => 12])->assertCreated();
        $this->postJson('/admin/pengumuman', ['pengumuman_judul' => 'X'])->assertForbidden();
    }

    public function test_halaman_publik_tetap_tampil_saat_baca_saja(): void
    {
        $this->langganan('pro', '2026-10-01');

        foreach (['/', '/profil', '/akademik', '/berita', '/galeri', '/kontak', '/unduhan', '/ppdb'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    // ------------------------------------------------------------ modul per paket

    public function test_modul_di_luar_paket_basic_ditolak_di_panel_publik_dan_api(): void
    {
        $this->langganan('basic', '2026-12-31'); // absensi, nilai, konten web
        $this->masuk();

        // diizinkan
        $this->get('/admin/absensi')->assertOk();
        $this->get('/admin/nilai')->assertOk();
        $this->get('/admin/berita')->assertOk();
        // ditolak
        $this->get('/admin/ppdb')->assertRedirect(route('admin/dashboard'))->assertSessionHas('toast_error', fn ($m) => str_contains($m, 'PPDB Online') && str_contains($m, 'paket'));
        $this->get('/admin/unduhan')->assertRedirect(route('admin/dashboard'));
        $this->get('/admin/chat')->assertRedirect(route('admin/dashboard'));
        $this->getJson('/admin/unduhan/data')->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'Unduhan'));
        $this->postJson('/admin/chat/status', ['status' => 'online'])->assertForbidden();

        // publik: seolah tidak ada
        $this->get('/ppdb')->assertNotFound();
        $this->get('/unduhan')->assertNotFound();
        $this->getJson('/chat/status')->assertForbidden();
        $this->postJson('/chat/start', [])->assertForbidden();
    }

    public function test_paket_pro_membuka_ppdb_unduhan_dan_konsultasi(): void
    {
        $this->langganan('pro', '2026-12-31');
        $this->masuk();

        foreach (['/admin/ppdb', '/admin/unduhan', '/admin/chat', '/admin/chat/statistik'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson('/chat/status')->assertOk();
        $this->assertFalse(AksesSekolah::modul('portal_orang_tua'));
    }

    public function test_menu_admin_dan_situs_publik_menyembunyikan_modul_di_luar_paket(): void
    {
        $this->langganan('basic', '2026-12-31');
        $this->masuk();

        $this->get('/admin/dashboard')->assertOk()->assertSee('Absensi')->assertSee('Berita')->assertSee('Langganan Saya')
            ->assertDontSee('Konsultasi Online')->assertDontSee('Unduhan')->assertDontSee(route('admin/ppdb'));

        auth()->logout();
        $home = $this->get('/')->assertOk();
        $home->assertDontSee('id="chat-widget"', false)->assertDontSee(route('ppdb'))->assertDontSee(route('unduhan'))->assertSee(route('berita'));

        $this->langganan('premium', '2027-12-31'); // langganan terkini berpindah ke Premium
        $this->get('/')->assertOk()->assertSee('id="chat-widget"', false)->assertSee(route('ppdb'));
    }

    public function test_konten_web_dikunci_jika_tidak_ada_di_paket(): void
    {
        $paket = Paket::where('kode', 'basic')->first();
        $paket->modul()->sync(\App\Modul::whereIn('kode', ['absensi'])->pluck('id')); // paket tanpa konten web
        $this->langganan('basic', '2026-12-31');

        $this->get('/berita')->assertNotFound();
        $this->get('/galeri')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Kabar Terbaru dari Sekolah');
    }

    public function test_menyaring_menu_membuang_judul_bagian_yang_kosong(): void
    {
        $this->langganan('basic', '2026-12-31');
        $menu = [
            ['label' => 'Dashboard', 'route' => 'x', 'match' => ['x']],
            ['header' => 'PPDB'],
            ['label' => 'PPDB', 'route' => 'p', 'match' => ['p'], 'modul' => 'ppdb'],
            ['header' => 'Akademik'],
            ['label' => 'Absensi', 'route' => 'a', 'match' => ['a'], 'modul' => 'absensi'],
        ];

        $hasil = AksesSekolah::saringMenu($menu);

        $this->assertSame(['Dashboard', 'Akademik', 'Absensi'], array_map(fn ($i) => $i['label'] ?? $i['header'], $hasil));
    }

    // ------------------------------------------------------------ Langganan Saya

    public function test_halaman_langganan_saya_menampilkan_status_modul_dan_riwayat(): void
    {
        $this->langganan('pro', '2026-12-31');
        Invoice::create(['nomor' => 'INV-2026-0001', 'sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::first()->id, 'durasi_bulan' => 1, 'jumlah' => 100000, 'status' => 'menunggu']);
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMA', 'status' => 'aktif']);
        Invoice::create(['nomor' => 'INV-2026-0002', 'sekolah_id' => $lain->id, 'paket_id' => Paket::first()->id, 'durasi_bulan' => 1, 'jumlah' => 999000, 'status' => 'menunggu']);
        $this->masuk();

        $this->get('/admin/langganan')->assertOk()->assertSee('Paket Pro')->assertSee('31 Desember 2026')->assertSee('Sisa 57 hari')
            ->assertSee('Portal Orang Tua')->assertSee('INV-2026-0001')->assertDontSee('INV-2026-0002')->assertDontSee('999.000');
    }

    public function test_langganan_saya_untuk_sekolah_tanpa_batas_dan_hanya_admin(): void
    {
        $this->sekolah->update(['tanpa_batas' => true]);
        $this->masuk();
        $this->get('/admin/langganan')->assertOk()->assertSee('Tanpa batas');

        $guru = User::factory()->create(['role' => 'guru']);
        $this->actingAs($guru)->get('/admin/langganan')->assertRedirect(route('guru/dashboard'));
        auth()->logout();
        $this->get('/admin/langganan')->assertRedirect(route('login'));
    }

    public function test_pengajuan_perpanjangan_membuat_invoice_menunggu_tanpa_duplikat(): void
    {
        $this->langganan('basic', '2026-12-31');
        $this->masuk();
        $pro = Paket::where('kode', 'pro')->first();

        $this->postJson('/admin/langganan/ajukan', ['paket_id' => $pro->id, 'durasi_bulan' => 6])->assertCreated();
        $inv = Invoice::firstOrFail();
        $this->assertSame([$this->sekolah->id, $pro->id, 6, 1080000, 'menunggu'], [$inv->sekolah_id, $inv->paket_id, $inv->durasi_bulan, $inv->jumlah, $inv->status]);
        $this->assertStringContainsString($this->admin->name, $inv->catatan);

        $this->postJson('/admin/langganan/ajukan', ['paket_id' => $pro->id, 'durasi_bulan' => 6])->assertStatus(409);
        $this->postJson('/admin/langganan/ajukan', ['paket_id' => $pro->id, 'durasi_bulan' => 3])->assertStatus(422);
        $this->postJson('/admin/langganan/ajukan', ['paket_id' => 9999, 'durasi_bulan' => 6])->assertStatus(422);
        $pro->update(['aktif' => false]);
        $this->postJson('/admin/langganan/ajukan', ['paket_id' => $pro->id, 'durasi_bulan' => 1])->assertStatus(422);
        $this->assertSame(1, Invoice::count());
    }

    // ------------------------------------------------------------ pengingat H-30 / H-7 / H-1

    private function jalankanPengingat(): void
    {
        $this->artisan('langganan:pengingat')->assertSuccessful();
    }

    public function test_pengingat_dikirim_per_tahap_sekali_saja(): void
    {
        Mail::fake();
        $l = $this->langganan('pro', '2026-12-04'); // 30 hari lagi

        $this->jalankanPengingat();
        Mail::assertQueued(LanggananMail::class, 1);
        Mail::assertQueued(LanggananMail::class, fn (LanggananMail $m) => $m->hasTo('admin@uji.test') && $m->sisaHari === 30);
        $this->assertNotNull($l->fresh()->pengingat_h30_at);

        $this->jalankanPengingat(); // hari yang sama: tidak dobel
        Mail::assertQueued(LanggananMail::class, 1);

        // H-7
        Carbon::setTestNow(Carbon::parse('2026-11-27 10:00:00', 'Asia/Jakarta'));
        $this->jalankanPengingat();
        Mail::assertQueued(LanggananMail::class, 2);
        Mail::assertQueued(LanggananMail::class, fn (LanggananMail $m) => $m->sisaHari === 7);

        // H-1
        Carbon::setTestNow(Carbon::parse('2026-12-03 10:00:00', 'Asia/Jakarta'));
        $this->jalankanPengingat();
        Mail::assertQueued(LanggananMail::class, 3);
        Mail::assertQueued(LanggananMail::class, fn (LanggananMail $m) => $m->sisaHari === 1);

        Carbon::setTestNow(Carbon::parse('2026-12-04 10:00:00', 'Asia/Jakarta'));
        $this->jalankanPengingat(); // hari terakhir: semua tahap sudah terkirim
        Mail::assertQueued(LanggananMail::class, 3);
    }

    public function test_tahap_terlewat_dikirim_sekali_dengan_tahap_terdekat(): void
    {
        Mail::fake();
        $l = $this->langganan('pro', '2026-11-09'); // 5 hari lagi, belum pernah diingatkan

        $this->jalankanPengingat();

        Mail::assertQueued(LanggananMail::class, 1);
        $l->refresh();
        $this->assertNotNull($l->pengingat_h7_at);
        $this->assertNotNull($l->pengingat_h30_at, 'tahap yang lebih jauh ikut ditandai');
        $this->assertNull($l->pengingat_h1_at);
    }

    public function test_pengingat_dikirim_ke_semua_admin_dan_dilewati_untuk_kasus_lain(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'admin', 'email' => 'admin2@uji.test']);
        User::factory()->create(['role' => 'guru', 'email' => 'guru@uji.test']);
        $this->langganan('pro', '2026-11-10');

        $this->jalankanPengingat();
        Mail::assertQueued(LanggananMail::class, 2);
        Mail::assertNotQueued(LanggananMail::class, fn (LanggananMail $m) => $m->hasTo('guru@uji.test'));

        // sekolah lain: tanpa batas, nonaktif, jauh, sudah habis, dan sudah diperpanjang
        Mail::fake();
        $buat = function (string $sub, array $atribut, string $berakhir) {
            $s = Sekolah::create(['nama' => $sub, 'subdomain' => $sub, 'jenjang' => 'SMA'] + $atribut);
            SekolahAktif::jalankan($s, fn () => User::factory()->create(['role' => 'admin', 'email' => "a@{$sub}.test"]));
            $this->langganan('pro', $berakhir, $s);

            return $s;
        };
        $buat('bebas', ['tanpa_batas' => true, 'status' => 'aktif'], '2026-11-10');
        $buat('mati', ['status' => 'nonaktif'], '2026-11-10');
        $buat('jauh', ['status' => 'aktif'], '2027-06-01');
        $buat('habis', ['status' => 'aktif'], '2026-10-01');
        $diperpanjang = $buat('lanjut', ['status' => 'aktif'], '2026-11-10');
        $this->langganan('pro', '2027-12-31', $diperpanjang); // langganan terkini sudah jauh

        $this->artisan('langganan:pengingat')->assertSuccessful();
        Mail::assertNothingQueued(); // sekolah uji sudah diingatkan; sekolah lain dilewati
    }

    public function test_perintah_pengingat_simulasi_tidak_mengirim_dan_terjadwal(): void
    {
        Mail::fake();
        $l = $this->langganan('pro', '2026-11-10');

        $this->artisan('langganan:pengingat --kering')->expectsOutputToContain('[simulasi]')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertNull($l->fresh()->pengingat_h7_at);
        $this->artisan('schedule:list')->expectsOutputToContain('langganan:pengingat')->assertSuccessful();
    }

    public function test_email_pengingat_memakai_identitas_sekolah_dan_tautan_langganan(): void
    {
        $l = $this->langganan('pro', '2026-11-10');
        \App\Setting::put(['nama_sekolah' => 'SMA Contoh Mandiri']);
        SekolahAktif::instance()->set(null); // seperti queue worker

        $mail = new LanggananMail($l->fresh(), 6);
        $html = $mail->render();

        $this->assertStringContainsString('SMA Contoh Mandiri', $html);
        $this->assertStringContainsString('10 November 2026', $html);
        $this->assertStringContainsString('6 hari lagi', $html);
        $this->assertStringContainsString('admin/langganan', $html);
        $this->assertStringContainsString('berakhir 6 hari lagi', $mail->envelope()->subject);
        $this->assertStringContainsString('berakhir besok', (new LanggananMail($l, 1))->envelope()->subject);
    }

    // ------------------------------------------------------------ platform: tanpa batas

    public function test_super_admin_mengatur_sekolah_tanpa_batas(): void
    {
        $super = SekolahAktif::jalankan(null, function () {
            $u = User::factory()->create(['sekolah_id' => null, 'username' => 'super']);
            $u->setPeran(['super_admin']);

            return $u;
        });
        $this->actingAs($super);

        $this->putJson("/platform/sekolah/{$this->sekolah->id}", ['nama' => 'Sekolah Uji', 'jenjang' => 'SMA', 'subdomain' => 'uji', 'tanpa_batas' => 1])->assertOk();
        $this->assertTrue($this->sekolah->fresh()->tanpa_batas);
        $daftar = $this->getJson('/platform/sekolah/data?draw=1&start=0&length=10&search[value]=&columns[0][data]=&order[0][column]=0&order[0][dir]=asc')->assertOk();
        $this->assertSame('Tanpa batas|primary', $daftar->json('data.0.status_sewa'));
        $this->get("/platform/sekolah/{$this->sekolah->id}")->assertOk()->assertSee('Tanpa batas');

        $this->putJson("/platform/sekolah/{$this->sekolah->id}", ['nama' => 'Sekolah Uji', 'jenjang' => 'SMA', 'subdomain' => 'uji'])->assertOk(); // tidak dicentang = dibatasi
        $this->assertFalse($this->sekolah->fresh()->tanpa_batas);
    }
}
