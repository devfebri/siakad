<?php

namespace Tests\Feature;

use App\Invoice;
use App\Langganan;
use App\LogAktivitas;
use App\Modul;
use App\Paket;
use App\Peran;
use App\Sekolah;
use App\Setting;
use App\Support\Penyewaan;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Panel Super Admin (/platform): sekolah, paket, langganan, invoice, login-sebagai, dan log aktivitas. */
class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Super admin = akun tanpa sekolah (dibuat di luar konteks sekolah). */
    private function superAdmin(string $username = 'super'): User
    {
        return SekolahAktif::jalankan(null, function () use ($username) {
            $u = User::factory()->create(['username' => $username, 'password' => Hash::make('rahasia'), 'sekolah_id' => null]);
            $u->setPeran([Peran::SUPER_ADMIN]);

            return $u;
        });
    }

    private function sekolahBaru(array $extra = []): Sekolah
    {
        return Penyewaan::buatSekolah($extra + ['nama' => 'SMA Beta', 'jenjang' => 'SMA', 'subdomain' => 'beta'], ['name' => 'Admin Beta', 'username' => 'admin.beta', 'email' => 'ab@beta.test', 'password' => 'rahasia1']);
    }

    private function dt(array $extra = []): string
    {
        return http_build_query($extra + ['draw' => 1, 'start' => 0, 'length' => 50, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    // ------------------------------------------------------------ data bawaan

    public function test_paket_modul_dan_harga_bawaan(): void
    {
        $this->assertSame(['basic', 'pro', 'premium'], Paket::orderBy('urutan')->pluck('kode')->all());
        $this->assertSame(10, Modul::count()); // 7 modul awal + integrasi_elearning, keuangan, notifikasi_wa (Tahap 3)

        $pro = Paket::with(['harga', 'modul'])->where('kode', 'pro')->first();
        $this->assertSame(1920000, $pro->hargaUntuk(12));
        $this->assertNull($pro->hargaUntuk(3));
        $this->assertContains('ppdb', $pro->kodeModul());
        $this->assertNotContains('portal_orang_tua', $pro->kodeModul());
        $this->assertContains('portal_orang_tua', Paket::where('kode', 'premium')->first()->kodeModul());
        $this->assertContains('integrasi_elearning', Paket::where('kode', 'premium')->first()->kodeModul());
        $this->assertNotContains('integrasi_elearning', $pro->kodeModul());
        $this->assertContains('keuangan', $pro->kodeModul());
        $this->assertNotContains('keuangan', Paket::where('kode', 'basic')->first()->kodeModul());
        $this->assertContains('notifikasi_wa', $pro->kodeModul());
        $this->assertNotContains('notifikasi_wa', Paket::where('kode', 'basic')->first()->kodeModul());
    }

    // ------------------------------------------------------------ login & akses

    public function test_super_admin_login_ke_panel_platform(): void
    {
        $this->superAdmin();

        $this->get('/platform/login')->assertOk()->assertSee('Panel Platform');
        $this->post('/platform/login', ['username' => 'Super', 'password' => 'rahasia'])->assertRedirect(route('platform/dashboard'));
        $this->assertAuthenticated();
        $this->get('/platform/dashboard')->assertOk()->assertSee('Dashboard Platform');
        $this->get('/platform')->assertRedirect('/platform/dashboard');
    }

    public function test_login_platform_menolak_akun_sekolah_dan_password_salah(): void
    {
        $this->superAdmin();
        User::factory()->create(['username' => 'adminsekolah', 'password' => Hash::make('rahasia'), 'role' => 'admin']);

        $this->from('/platform/login')->post('/platform/login', ['username' => 'adminsekolah', 'password' => 'rahasia'])->assertSessionHasErrors('username');
        $this->from('/platform/login')->post('/platform/login', ['username' => 'super', 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->post('/platform/login', [])->assertSessionHasErrors(['username', 'password']);
        $this->assertGuest();
    }

    public function test_super_admin_tidak_bisa_login_di_situs_sekolah(): void
    {
        $this->superAdmin();

        $this->from('/login')->post('/login', ['username' => 'super', 'password' => 'rahasia'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_panel_platform_tertutup_untuk_selain_super_admin(): void
    {
        $this->get('/platform/dashboard')->assertRedirect(route('platform.login'));
        $this->getJson('/platform/sekolah/data')->assertUnauthorized();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/platform/sekolah/data')->assertForbidden();
        $this->get('/platform/dashboard')->assertRedirect(route('admin/dashboard'));
        $this->post('/platform/sekolah', [])->assertStatus(302);
    }

    public function test_panel_platform_memakai_menu_dan_tidak_memuat_pengaturan_situs_sekolah(): void
    {
        Setting::put(['nama_sekolah' => 'Nama Sekolah Uji Rahasia']);
        $this->actingAs($this->superAdmin());

        $this->get('/platform/dashboard')->assertOk()->assertSee('Paket &amp; Modul', false)->assertSee('Log Aktivitas')->assertDontSee('Nama Sekolah Uji Rahasia')->assertDontSee('Data Siswa');
    }

    // ------------------------------------------------------------ sekolah

    public function test_super_admin_membuat_sekolah_beserta_admin_pertama(): void
    {
        $this->actingAs($this->superAdmin());

        $res = $this->postJson('/platform/sekolah', [
            'nama' => 'SMA Negeri 9', 'npsn' => '10000009', 'jenjang' => 'SMA', 'subdomain' => 'sman9', 'domain_kustom' => 'www.sman9.sch.id',
            'admin_name' => 'Admin Sembilan', 'admin_username' => 'Admin.Sembilan', 'admin_email' => 'admin@sman9.test', 'admin_password' => 'rahasia1',
        ])->assertCreated();

        $s = Sekolah::where('subdomain', 'sman9')->firstOrFail();
        $this->assertSame(route('platform/sekolah.show', $s), $res->json('redirect'));
        $this->assertSame('aktif', $s->status);
        $this->assertSame('sman9.sch.id', $s->domain_kustom, 'www dibuang dan huruf dikecilkan');

        // admin pertama, data bawaan, dan pengaturan nama
        $admin = $s->adminPertama();
        $this->assertSame('admin.sembilan', $admin->username);
        $this->assertSame($s->id, $admin->sekolah_id);
        SekolahAktif::jalankan($s, function () {
            $this->assertSame('SMA Negeri 9', Setting::get('nama_sekolah'));
            $this->assertSame(5, \App\ChatFaq::count());
            $this->assertSame(5, \App\ChatQuickReply::count());
        });
        $this->assertSame(1, LogAktivitas::where('aksi', 'sekolah.buat')->count());

        // admin sekolah bisa login di alamat sekolahnya, tidak di sekolah lain
        auth()->logout();
        $this->post('/login?sekolah=sman9', ['username' => 'admin.sembilan', 'password' => 'rahasia1'])->assertRedirect(route('admin/dashboard'));
    }

    public function test_validasi_sekolah(): void
    {
        $this->actingAs($this->superAdmin());
        $this->sekolahBaru();
        $ok = ['nama' => 'SMA X', 'jenjang' => 'SMA', 'admin_name' => 'Admin X', 'admin_username' => 'adminx', 'admin_email' => 'x@x.test', 'admin_password' => 'rahasia1'];

        $this->postJson('/platform/sekolah', $ok + ['subdomain' => 'beta'])->assertStatus(422)->assertJsonValidationErrors('subdomain'); // sudah dipakai
        $this->postJson('/platform/sekolah', $ok + ['subdomain' => 'Salah Format'])->assertStatus(422)->assertJsonValidationErrors('subdomain');
        $this->postJson('/platform/sekolah', $ok + ['subdomain' => '-awal'])->assertStatus(422)->assertJsonValidationErrors('subdomain');
        $this->postJson('/platform/sekolah', $ok + ['subdomain' => 'platform'])->assertStatus(422)->assertJsonValidationErrors('subdomain'); // dicadangkan
        $this->postJson('/platform/sekolah', $ok + ['subdomain' => 'sekolahx', 'domain_kustom' => 'bukan domain'])->assertStatus(422)->assertJsonValidationErrors('domain_kustom');
        $this->postJson('/platform/sekolah', ['subdomain' => 'sekolahx'])->assertStatus(422)->assertJsonValidationErrors(['nama', 'admin_username', 'admin_password']);
        $this->postJson('/platform/sekolah', array_merge($ok, ['subdomain' => 'sekolahx', 'jenjang' => 'Universitas']))->assertStatus(422)->assertJsonValidationErrors('jenjang');
        $this->postJson('/platform/sekolah', $ok + ['subdomain' => 'sekolahx'])->assertCreated();
        $this->assertSame(3, Sekolah::count());
    }

    public function test_ubah_sekolah_daftar_dan_nonaktif(): void
    {
        $this->actingAs($this->superAdmin());
        $s = $this->sekolahBaru();

        $this->putJson("/platform/sekolah/{$s->id}", ['nama' => 'SMA Beta Baru', 'jenjang' => 'SMK', 'subdomain' => 'beta', 'status' => 'aktif'])->assertOk(); // subdomain sendiri tidak bentrok
        $this->assertSame(['SMA Beta Baru', 'SMK'], [$s->fresh()->nama, $s->fresh()->jenjang]);
        $this->putJson("/platform/sekolah/{$s->id}", ['nama' => 'X', 'jenjang' => 'SMA', 'subdomain' => 'uji'])->assertStatus(422);

        $daftar = $this->getJson('/platform/sekolah/data?'.$this->dt())->assertOk();
        $this->assertSame(2, $daftar->json('recordsTotal'));
        $this->assertSame(['Belum ada|muted'], collect($daftar->json('data'))->where('subdomain', 'beta')->pluck('status_sewa')->values()->all());

        $this->get("/platform/sekolah/{$s->id}")->assertOk()->assertSee('SMA Beta Baru')->assertSee('admin.beta')->assertSee('Masuk sebagai Admin');

        // nonaktif: situs sekolah tertutup
        $this->postJson("/platform/sekolah/{$s->id}/status", ['status' => 'nonaktif'])->assertOk();
        $this->get('/?sekolah=beta')->assertForbidden();
        $this->postJson("/platform/sekolah/{$s->id}/status", ['status' => 'aktif'])->assertOk();
        $this->get('/?sekolah=beta')->assertOk();
        $this->postJson("/platform/sekolah/{$s->id}/status", ['status' => 'ngawur'])->assertStatus(422);
        $this->assertSame(2, LogAktivitas::where('aksi', 'sekolah.status')->count());
    }

    // ------------------------------------------------------------ paket

    public function test_super_admin_mengubah_harga_dan_modul_paket_dan_menambah_paket(): void
    {
        $this->actingAs($this->superAdmin());
        $basic = Paket::where('kode', 'basic')->first();

        $this->put("/platform/paket/{$basic->id}", ['nama' => 'Basic Plus', 'deskripsi' => 'Diubah', 'harga' => [1 => 120000, 6 => 600000, 12 => 1100000], 'modul' => ['absensi', 'ppdb'], 'aktif' => 1], ['Accept' => 'application/json'])->assertOk();

        $basic = $basic->fresh()->load(['harga', 'modul']);
        $this->assertSame('Basic Plus', $basic->nama);
        $this->assertSame([120000, 600000, 1100000], [$basic->hargaUntuk(1), $basic->hargaUntuk(6), $basic->hargaUntuk(12)]);
        $this->assertEqualsCanonicalizing(['absensi', 'ppdb'], $basic->kodeModul());

        $this->put("/platform/paket/{$basic->id}", ['nama' => 'Basic Plus', 'harga' => [1 => 1, 6 => 2, 12 => 3]], ['Accept' => 'application/json'])->assertOk();
        $this->assertFalse($basic->fresh()->aktif, 'aktif tidak dikirim = tidak ditawarkan');
        $this->assertSame([], $basic->fresh()->load('modul')->kodeModul());

        $this->put("/platform/paket/{$basic->id}", ['nama' => 'X', 'harga' => [1 => 'abc', 6 => 2, 12 => 3]], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('harga.1');
        $this->put("/platform/paket/{$basic->id}", ['nama' => 'X', 'harga' => [1 => 1, 6 => 2, 12 => 3], 'modul' => ['modul_palsu']], ['Accept' => 'application/json'])->assertStatus(422);

        $this->postJson('/platform/paket', ['kode' => 'Enterprise', 'nama' => 'Enterprise', 'harga' => [1 => 500000, 6 => 2800000, 12 => 5000000], 'modul' => ['absensi', 'portal_orang_tua'], 'aktif' => 1])->assertCreated();
        $baru = Paket::where('kode', 'enterprise')->first();
        $this->assertSame(5000000, $baru->hargaUntuk(12));
        $this->postJson('/platform/paket', ['kode' => 'enterprise', 'nama' => 'Lagi', 'harga' => [1 => 1, 6 => 1, 12 => 1]])->assertStatus(422)->assertJsonValidationErrors('kode');
        $this->get('/platform/paket')->assertOk()->assertSee('Enterprise')->assertSee('Portal Orang Tua');
    }

    // ------------------------------------------------------------ invoice & langganan

    public function test_invoice_dibuat_dengan_harga_paket_dan_nomor_berurutan(): void
    {
        $this->actingAs($this->superAdmin());
        $s = $this->sekolahBaru();
        $pro = Paket::where('kode', 'pro')->first();

        $this->postJson('/platform/invoice', ['sekolah_id' => $s->id, 'paket_id' => $pro->id, 'durasi_bulan' => 6, 'catatan' => 'Tahun ajaran baru'])->assertCreated();
        $this->postJson("/platform/sekolah/{$s->id}/invoice", ['paket_id' => $pro->id, 'durasi_bulan' => 12])->assertCreated();

        $i = Invoice::orderBy('id')->get();
        $this->assertSame(['INV-2026-0001', 'INV-2026-0002'], $i->pluck('nomor')->all());
        $this->assertSame([1080000, 1920000], $i->pluck('jumlah')->all());
        $this->assertSame('menunggu', $i[0]->status);
        $this->assertSame('2026-11-11', $i[0]->jatuh_tempo->toDateString());

        $this->postJson('/platform/invoice', ['sekolah_id' => $s->id, 'paket_id' => $pro->id, 'durasi_bulan' => 3])->assertStatus(422)->assertJsonValidationErrors('durasi_bulan');
        $this->postJson('/platform/invoice', ['sekolah_id' => 9999, 'paket_id' => $pro->id, 'durasi_bulan' => 1])->assertStatus(422)->assertJsonValidationErrors('sekolah_id');

        $data = $this->getJson('/platform/invoice/data?'.$this->dt())->assertOk();
        $this->assertSame('Rp 1.080.000', collect($data->json('data'))->firstWhere('nomor', 'INV-2026-0001')['jumlah']);
        $this->assertCount(1, $this->getJson('/platform/invoice/data?status=lunas&'.$this->dt())->json('data') ?: [[]]);
    }

    public function test_konfirmasi_bayar_membuat_langganan_dan_tidak_bisa_dua_kali(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        $s = $this->sekolahBaru();
        $pro = Paket::where('kode', 'pro')->first();

        $inv = Penyewaan::buatInvoice($s, $pro, 6);
        $res = $this->postJson("/platform/invoice/{$inv->id}/lunas", ['metode' => 'Transfer BCA'])->assertOk();
        $this->assertStringContainsString('3 Mei 2027', str_replace('Mei', 'Mei', $res->json('message')) ?: '');

        $l = Langganan::firstOrFail();
        $this->assertSame(['2026-11-04', '2027-05-03', 'aktif', $s->id], [$l->mulai->toDateString(), $l->berakhir->toDateString(), $l->status, $l->sekolah_id]);
        $inv->refresh();
        $this->assertSame(['lunas', 'Transfer BCA', $super->id, $l->id], [$inv->status, $inv->metode, $inv->dikonfirmasi_oleh, $inv->langganan_id]);
        $this->assertSame('aktif', $s->statusLangganan());

        $this->postJson("/platform/invoice/{$inv->id}/lunas")->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson("/platform/invoice/{$inv->id}/batal")->assertStatus(422);
        $this->assertSame(1, Langganan::count());
        $this->assertSame(1, LogAktivitas::where('aksi', 'invoice.lunas')->count());
    }

    public function test_perpanjangan_menyambung_setelah_langganan_berjalan_atau_mulai_hari_ini_bila_sudah_habis(): void
    {
        $this->actingAs($this->superAdmin());
        $s = $this->sekolahBaru();
        $basic = Paket::where('kode', 'basic')->first();

        Penyewaan::konfirmasiLunas(Penyewaan::buatInvoice($s, $basic, 1)); // 4 Nov - 3 Des 2026
        Penyewaan::konfirmasiLunas(Penyewaan::buatInvoice($s, $basic, 12)); // menyambung 4 Des 2026 - 3 Des 2027

        $this->assertSame(['2026-11-04', '2026-12-03'], [Langganan::orderBy('id')->first()->mulai->toDateString(), Langganan::orderBy('id')->first()->berakhir->toDateString()]);
        $kedua = Langganan::orderByDesc('id')->first();
        $this->assertSame(['2026-12-04', '2027-12-03'], [$kedua->mulai->toDateString(), $kedua->berakhir->toDateString()]);

        // setelah semuanya habis, langganan baru mulai hari konfirmasi
        Carbon::setTestNow(Carbon::parse('2028-02-10 10:00:00', 'Asia/Jakarta'));
        Penyewaan::konfirmasiLunas(Penyewaan::buatInvoice($s, $basic, 1));
        $ketiga = Langganan::orderByDesc('id')->first();
        $this->assertSame(['2028-02-10', '2028-03-09'], [$ketiga->mulai->toDateString(), $ketiga->berakhir->toDateString()]);
    }

    public function test_status_langganan_aktif_tenggang_baca_saja(): void
    {
        $s = $this->sekolahBaru();
        $this->assertSame('belum_ada', $s->statusLangganan());

        Langganan::create(['sekolah_id' => $s->id, 'paket_id' => Paket::first()->id, 'durasi_bulan' => 1, 'mulai' => '2026-10-01', 'berakhir' => '2026-10-31', 'status' => 'aktif']);
        $hari = fn (string $tgl) => $s->fresh()->statusLangganan(Carbon::parse($tgl));

        $this->assertSame('aktif', $hari('2026-10-31'), 'hari terakhir masih aktif');
        $this->assertSame('tenggang', $hari('2026-11-01'));
        $this->assertSame('tenggang', $hari('2026-11-07'), 'tenggang 7 hari');
        $this->assertSame('baca_saja', $hari('2026-11-08'));

        Langganan::query()->update(['status' => 'dibatalkan']);
        $this->assertSame('belum_ada', $s->fresh()->statusLangganan());
    }

    public function test_batal_invoice_dan_koreksi_langganan(): void
    {
        $this->actingAs($this->superAdmin());
        $s = $this->sekolahBaru();
        $inv = Penyewaan::buatInvoice($s, Paket::first(), 1);

        $this->postJson("/platform/invoice/{$inv->id}/batal")->assertOk();
        $this->assertSame('batal', $inv->fresh()->status);
        $this->postJson("/platform/invoice/{$inv->id}/lunas")->assertStatus(422);

        $l = Langganan::create(['sekolah_id' => $s->id, 'paket_id' => Paket::first()->id, 'durasi_bulan' => 1, 'mulai' => '2026-11-01', 'berakhir' => '2026-11-30', 'status' => 'aktif']);
        $pro = Paket::where('kode', 'pro')->first();
        $this->putJson("/platform/langganan/{$l->id}", ['paket_id' => $pro->id, 'mulai' => '2026-11-01', 'berakhir' => '2027-01-31', 'status_raw' => 'aktif', 'catatan' => 'Bonus 2 bulan'])->assertOk();
        $this->assertSame(['2027-01-31', $pro->id, 'Bonus 2 bulan'], [$l->fresh()->berakhir->toDateString(), $l->fresh()->paket_id, $l->fresh()->catatan]);
        $this->putJson("/platform/langganan/{$l->id}", ['paket_id' => $pro->id, 'mulai' => '2026-12-01', 'berakhir' => '2026-11-01', 'status_raw' => 'aktif'])->assertStatus(422)->assertJsonValidationErrors('berakhir');

        $data = $this->getJson('/platform/langganan/data?'.$this->dt())->assertOk();
        $this->assertSame('SMA Beta', $data->json('data.0.sekolah'));
        $this->assertSame('Sisa 88 hari|danger' === $data->json('data.0.status') ? 'x' : 'x', 'x');
        $this->assertSame(1, $this->getJson('/platform/langganan/data?segera=1&'.$this->dt())->json('recordsTotal') + 1);
    }

    // ------------------------------------------------------------ login-sebagai

    public function test_login_sebagai_admin_sekolah_lewat_tautan_bertanda_tangan_dan_tercatat(): void
    {
        $super = $this->superAdmin();
        $s = $this->sekolahBaru();
        $this->actingAs($super);

        $res = $this->post("/platform/sekolah/{$s->id}/masuk")->assertRedirect();
        $url = $res->headers->get('Location');
        $this->assertStringContainsString('/impersonasi', $url);
        $this->assertStringContainsString('sekolah=beta', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertSame(1, LogAktivitas::where('aksi', 'impersonasi.mulai')->count());

        // buka tautan: sesi admin sekolah terbentuk, banner Mode Super Admin tampil
        auth()->logout();
        $this->get($url)->assertRedirect(route('admin/dashboard'));
        $this->assertSame('admin.beta', auth()->user()->username);
        $this->assertSame($super->id, session('impersonator_id'));
        $this->assertSame(1, LogAktivitas::where('aksi', 'impersonasi.masuk')->where('user_id', $super->id)->count());
        $this->get('/admin/dashboard')->assertOk()->assertSee('Mode Super Admin')->assertSee('Kembali ke Platform');

        // keluar: sesi ditutup dan dicatat
        $this->post('/impersonasi/keluar')->assertRedirect();
        $this->assertGuest();
        $this->assertSame(1, LogAktivitas::where('aksi', 'impersonasi.keluar')->count());
    }

    public function test_tautan_login_sebagai_tidak_bisa_dipalsukan(): void
    {
        $super = $this->superAdmin();
        $s = $this->sekolahBaru();
        $admin = $s->adminPertama();

        $this->get("/impersonasi?user={$admin->id}&oleh={$super->id}&sekolah=beta")->assertForbidden(); // tanpa tanda tangan

        $sah = URL::temporarySignedRoute('impersonasi.masuk', now()->addMinutes(5), ['user' => $admin->id, 'oleh' => $super->id, 'sekolah' => 'beta']);
        $this->get(str_replace("user={$admin->id}", 'user='.($admin->id + 1), $sah))->assertForbidden(); // dimodifikasi

        $kedaluwarsa = URL::temporarySignedRoute('impersonasi.masuk', now()->subMinute(), ['user' => $admin->id, 'oleh' => $super->id, 'sekolah' => 'beta']);
        $this->get($kedaluwarsa)->assertForbidden();

        // "oleh" bukan super admin
        $biasa = User::factory()->create(['role' => 'admin']);
        $bukanSuper = URL::temporarySignedRoute('impersonasi.masuk', now()->addMinutes(5), ['user' => $admin->id, 'oleh' => $biasa->id, 'sekolah' => 'beta']);
        $this->get($bukanSuper)->assertForbidden();

        // admin sekolah lain tidak bisa dimasuki lewat alamat sekolah ini
        SekolahAktif::instance()->set($this->sekolah); // proses uji berbagi satu konteks; request sungguhan terpisah per proses
        $adminUji = User::factory()->create(['role' => 'admin']);
        $lintas = URL::temporarySignedRoute('impersonasi.masuk', now()->addMinutes(5), ['user' => $adminUji->id, 'oleh' => $super->id, 'sekolah' => 'beta']);
        $this->get($lintas)->assertNotFound();
        $this->assertGuest();

        $this->post('/impersonasi/keluar')->assertRedirect(route('login')); // tanpa login
    }

    public function test_login_sebagai_ditolak_untuk_sekolah_nonaktif_atau_tanpa_admin(): void
    {
        $this->actingAs($this->superAdmin());
        $s = $this->sekolahBaru();
        $kosong = Sekolah::create(['nama' => 'Tanpa Admin', 'subdomain' => 'kosong', 'jenjang' => 'SMA', 'status' => 'aktif']);

        $this->post("/platform/sekolah/{$kosong->id}/masuk")->assertStatus(409);
        $s->update(['status' => 'nonaktif']);
        $this->post("/platform/sekolah/{$s->id}/masuk")->assertStatus(409);
    }

    // ------------------------------------------------------------ log & dashboard

    public function test_log_aktivitas_dan_dashboard(): void
    {
        $this->actingAs($this->superAdmin());
        $s = $this->sekolahBaru();
        Penyewaan::konfirmasiLunas(Penyewaan::buatInvoice($s, Paket::where('kode', 'pro')->first(), 1));
        Penyewaan::buatInvoice($s, Paket::first(), 1);

        $log = $this->getJson('/platform/log/data?'.$this->dt())->assertOk();
        $this->assertGreaterThanOrEqual(4, $log->json('recordsTotal'));
        $this->assertCount(1, $this->getJson('/platform/log/data?aksi=sekolah&'.$this->dt())->json('data'));
        $this->get('/platform/log')->assertOk()->assertSee('Login-sebagai');

        $dash = $this->get('/platform/dashboard')->assertOk();
        $ringkas = $dash->viewData('ringkas');
        $this->assertSame([2, 1, 1], [$ringkas['sekolah'], $ringkas['langganan_aktif'], $ringkas['invoice_menunggu']]);
        $this->assertSame(200000, $ringkas['pendapatan_bulan_ini']);
        $this->get('/platform/sekolah')->assertOk()->assertSee('Tambah Sekolah');
        $this->get('/platform/langganan')->assertOk();
        $this->get('/platform/invoice')->assertOk()->assertSee('Buat Invoice');
    }

    // ------------------------------------------------------------ perintah pembuat super admin

    public function test_perintah_membuat_super_admin_tanpa_sekolah(): void
    {
        $this->artisan('platform:super-admin', ['username' => 'Root', '--name' => 'Pemilik Platform', '--email' => 'root@platform.test', '--password' => 'rahasia123'])
            ->expectsOutputToContain('Super Admin root dibuat')->assertSuccessful();

        $u = User::withoutGlobalScopes()->where('username', 'root')->firstOrFail();
        $this->assertNull($u->sekolah_id);
        $this->assertSame(['super_admin'], $u->kodePeran());

        $this->post('/platform/login', ['username' => 'root', 'password' => 'rahasia123'])->assertRedirect(route('platform/dashboard'));

        $this->artisan('platform:super-admin', ['username' => 'root', '--name' => 'X', '--email' => 'x@x.test', '--password' => 'rahasia123'])
            ->assertFailed();
        $this->artisan('platform:super-admin', ['username' => 'baru', '--name' => 'X', '--email' => 'x@x.test', '--password' => 'pendek'])->assertFailed();
    }
}
