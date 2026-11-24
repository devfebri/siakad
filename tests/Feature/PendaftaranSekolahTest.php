<?php

namespace Tests\Feature;

use App\Invoice;
use App\Langganan;
use App\Mail\VerifikasiPendaftaranMail;
use App\Paket;
use App\PendaftaranSekolah;
use App\PengaturanPlatform;
use App\Peran;
use App\Sekolah;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Tahap 3 poin 3: halaman produk, pendaftaran sekolah mandiri dengan verifikasi email, trial, dan Pengaturan Platform. */
class PendaftaranSekolahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        PengaturanPlatform::put(['trial_aktif' => '1', 'trial_hari' => '14']);
    }

    private function data(array $ubah = []): array
    {
        return $ubah + [
            'nama' => 'SMP Negeri Contoh', 'npsn' => '10203040', 'jenjang' => 'SMP', 'subdomain' => 'smpcontoh',
            'paket_id' => Paket::where('kode', 'pro')->value('id'), 'durasi_bulan' => 6,
            'admin_name' => 'Ibu Rina', 'admin_email' => 'rina@contoh.sch.id', 'admin_username' => 'rina',
            'admin_password' => 'kataSandi1', 'admin_password_confirmation' => 'kataSandi1', 'setuju' => 1, 'pakai_trial' => 1,
        ];
    }

    /** Daftar lalu kembalikan tautan verifikasi dari email yang dikirim. */
    private function daftar(array $ubah = []): string
    {
        $this->post('/daftar', $this->data($ubah))->assertRedirect(route('daftar.cek-email'));
        $tautan = null;
        Mail::assertQueued(VerifikasiPendaftaranMail::class, function (VerifikasiPendaftaranMail $m) use (&$tautan) {
            $tautan = $m->tautan;

            return true;
        });

        return $tautan;
    }

    private function sekolahBaru(string $subdomain = 'smpcontoh'): ?Sekolah
    {
        return Sekolah::where('subdomain', $subdomain)->first();
    }

    // ------------------------------------------------------------ halaman produk

    public function test_halaman_produk_menampilkan_paket_harga_faq_dan_trial(): void
    {
        $this->get('/produk')->assertOk()
            ->assertSee('Basic')->assertSee('Pro')->assertSee('Premium')
            ->assertSee('Rp 100.000')->assertSee('Rp 960.000')->assertSee('Rp 3.360.000') // harga 1 bulan Basic, 12 bulan Basic dan Premium dari tabel paket_harga
            ->assertSee('Integrasi E-Learning')->assertSee('Pertanyaan yang sering diajukan')->assertSee('Coba gratis 14 hari');

        PengaturanPlatform::put(['trial_aktif' => '0']);
        $this->get('/produk')->assertOk()->assertDontSee('Coba gratis 14 hari');
    }

    public function test_harga_halaman_produk_mengikuti_tabel_paket_harga(): void
    {
        Paket::where('kode', 'basic')->first()->harga()->where('durasi_bulan', 1)->update(['harga' => 123000]);

        $this->get('/produk')->assertOk()->assertSee('Rp 123.000');
    }

    public function test_domain_utama_melayani_produk_tanpa_sekolah(): void
    {
        config(['sekolah.domain_utama' => 'siakad.test']);

        $this->get('http://siakad.test/daftar')->assertOk()->assertSee('Daftarkan sekolah Anda');
        $this->get('http://www.siakad.test/produk')->assertOk();
    }

    public function test_cek_ketersediaan_alamat(): void
    {
        $this->getJson('/daftar/cek-subdomain?s=smpbaru')->assertOk()->assertJsonPath('tersedia', true);
        $this->getJson('/daftar/cek-subdomain?s=uji')->assertJsonPath('tersedia', false);      // dipakai sekolah lain
        $this->getJson('/daftar/cek-subdomain?s=admin')->assertJsonPath('tersedia', false);    // dicadangkan
        $this->getJson('/daftar/cek-subdomain?s=Salah_Format!')->assertJsonPath('tersedia', false);

        $this->daftar();
        $this->getJson('/daftar/cek-subdomain?s=smpcontoh')->assertJsonPath('tersedia', false); // ditahan pendaftaran yang menunggu
    }

    // ------------------------------------------------------------ alur daftar

    public function test_daftar_mengirim_email_dan_sekolah_baru_dibuat_setelah_verifikasi(): void
    {
        $tautan = $this->daftar();
        $this->assertNull($this->sekolahBaru(), 'sekolah belum boleh dibuat sebelum email terverifikasi');
        $this->assertSame(1, PendaftaranSekolah::count());
        $this->assertNotSame($this->data()['admin_password'], PendaftaranSekolah::first()->data['admin']['password']); // password di-hash

        $this->get($tautan)->assertOk()->assertSee('Sekolah Anda sudah aktif')->assertSee('smpcontoh');

        $sekolah = $this->sekolahBaru();
        $this->assertSame('SMP Negeri Contoh', $sekolah->nama);
        $this->assertSame('aktif', $sekolah->status);

        $admin = User::withoutGlobalScopes()->where('sekolah_id', $sekolah->id)->first();
        $this->assertSame('rina', $admin->username);
        $this->assertTrue(Hash::check('kataSandi1', $admin->password));
        $this->assertTrue(SekolahAktif::jalankan($sekolah, fn () => $admin->fresh()->punyaPeran(Peran::ADMIN)));

        // trial 14 hari di paket pilihan, tanpa invoice
        $l = Langganan::where('sekolah_id', $sekolah->id)->first();
        $this->assertTrue($l->trial);
        $this->assertSame(Paket::where('kode', 'pro')->value('id'), $l->paket_id);
        $this->assertSame(today()->addDays(13)->toDateString(), $l->berakhir->toDateString());
        $this->assertSame(0, Invoice::where('sekolah_id', $sekolah->id)->count());
        $this->assertSame('aktif', $sekolah->statusLangganan());

        // admin baru bisa masuk ke situsnya
        $this->post('/login?sekolah=smpcontoh', ['username' => 'rina', 'password' => 'kataSandi1'])->assertRedirect();
    }

    public function test_tanpa_trial_membuat_invoice_dari_harga_paket(): void
    {
        $tautan = $this->daftar(['pakai_trial' => 0]);
        $this->get($tautan)->assertOk()->assertSee('Rp 1.080.000');

        $sekolah = $this->sekolahBaru();
        $this->assertSame(0, Langganan::where('sekolah_id', $sekolah->id)->count());
        $invoice = Invoice::where('sekolah_id', $sekolah->id)->first();
        $this->assertSame(1080000, (int) $invoice->jumlah); // Pro 6 bulan
        $this->assertSame('menunggu', $invoice->status);
    }

    public function test_trial_dimatikan_platform_mengabaikan_centang(): void
    {
        PengaturanPlatform::put(['trial_aktif' => '0']);
        $this->get($this->daftar())->assertOk();

        $this->assertSame(0, Langganan::where('trial', true)->count());
        $this->assertSame(1, Invoice::where('sekolah_id', $this->sekolahBaru()->id)->count());
    }

    public function test_lama_trial_mengikuti_pengaturan_platform(): void
    {
        PengaturanPlatform::put(['trial_hari' => '30']);
        $this->get($this->daftar())->assertOk();

        $this->assertSame(today()->addDays(29)->toDateString(), Langganan::where('trial', true)->first()->berakhir->toDateString());
    }

    public function test_trial_hanya_sekali_per_npsn(): void
    {
        $this->get($this->daftar())->assertOk();
        $this->assertSame(1, Langganan::where('trial', true)->count());

        // sekolah lain dengan NPSN sama mencoba trial lagi: dapat invoice, bukan trial
        $tautan = $this->daftar(['subdomain' => 'smpkedua', 'admin_email' => 'lain@contoh.sch.id', 'admin_username' => 'lain']);
        $this->get($tautan)->assertOk();

        $this->assertSame(1, Langganan::where('trial', true)->count());
        $this->assertSame(1, Invoice::where('sekolah_id', $this->sekolahBaru('smpkedua')->id)->count());
    }

    public function test_tautan_hanya_sekali_pakai_kedaluwarsa_atau_palsu(): void
    {
        $tautan = $this->daftar();
        $this->get($tautan)->assertOk()->assertSee('Sekolah Anda sudah aktif');
        $this->get($tautan)->assertOk()->assertSee('Tautan sudah tidak berlaku');
        $this->assertSame(1, Sekolah::where('subdomain', 'smpcontoh')->count());

        $this->get('/daftar/verifikasi/'.str_repeat('a', 64))->assertOk()->assertSee('Tautan sudah tidak berlaku');
        $this->get('/daftar/verifikasi/terlalu-pendek')->assertNotFound();

        $baru = $this->daftar(['subdomain' => 'smpkadaluarsa', 'admin_email' => 'x@contoh.sch.id', 'admin_username' => 'xyz']);
        PendaftaranSekolah::where('subdomain', 'smpkadaluarsa')->update(['kedaluwarsa_pada' => now()->subMinute()]);
        $this->get($baru)->assertOk()->assertSee('Tautan sudah tidak berlaku');
        $this->assertNull($this->sekolahBaru('smpkadaluarsa'));
    }

    public function test_alamat_diambil_pihak_lain_sebelum_verifikasi_ditolak(): void
    {
        $tautan = $this->daftar();
        Sekolah::create(['nama' => 'Penyerobot', 'subdomain' => 'smpcontoh', 'jenjang' => 'SMP', 'status' => 'aktif']);

        $this->get($tautan)->assertOk()->assertSee('Tautan sudah tidak berlaku');
        $this->assertSame('Penyerobot', Sekolah::where('subdomain', 'smpcontoh')->value('nama'));
    }

    public function test_validasi_alamat_email_dan_kata_sandi(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // banyak percobaan berturut-turut
        $this->post('/daftar', $this->data(['subdomain' => 'uji']))->assertSessionHasErrors('subdomain');           // sudah dipakai
        $this->post('/daftar', $this->data(['subdomain' => 'admin']))->assertSessionHasErrors('subdomain');         // dicadangkan
        $this->post('/daftar', $this->data(['subdomain' => '-salah-']))->assertSessionHasErrors('subdomain');
        $this->post('/daftar', $this->data(['admin_password_confirmation' => 'beda']))->assertSessionHasErrors('admin_password');
        $this->post('/daftar', $this->data(['admin_password' => 'pendek', 'admin_password_confirmation' => 'pendek']))->assertSessionHasErrors('admin_password');
        $this->post('/daftar', $this->data(['setuju' => null]))->assertSessionHasErrors('setuju');
        $this->post('/daftar', $this->data(['paket_id' => 9999]))->assertSessionHasErrors('paket_id');
        $this->post('/daftar', $this->data(['durasi_bulan' => 3]))->assertSessionHasErrors('durasi_bulan');
        Mail::assertNothingQueued();
    }

    public function test_email_admin_yang_sudah_terdaftar_ditolak(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'rina@contoh.sch.id']);

        $this->post('/daftar', $this->data())->assertSessionHasErrors('admin_email');
    }

    public function test_alamat_yang_ditahan_pendaftaran_lain_ditolak(): void
    {
        $this->daftar();

        $this->post('/daftar', $this->data(['admin_email' => 'orang.lain@contoh.sch.id', 'admin_username' => 'lain']))->assertSessionHasErrors('subdomain');
    }

    public function test_daftar_ulang_dengan_email_sama_mengganti_pendaftaran_lama(): void
    {
        $lama = $this->daftar();
        $baru = $this->daftar(['nama' => 'Nama Diperbaiki']);

        $this->assertSame(1, PendaftaranSekolah::count());
        $this->get($lama)->assertSee('Tautan sudah tidak berlaku');
        $this->get($baru)->assertSee('Sekolah Anda sudah aktif');
        $this->assertSame('Nama Diperbaiki', $this->sekolahBaru()->nama);
    }

    public function test_kotak_jebakan_robot_dan_pembatasan_laju(): void
    {
        $this->post('/daftar', $this->data(['website' => 'http://spam.test']))->assertRedirect(route('daftar.cek-email'));
        Mail::assertNothingQueued();
        $this->assertSame(0, PendaftaranSekolah::count());

        for ($i = 0; $i < 5; $i++) {
            $this->post('/daftar', $this->data(['setuju' => null]));
        }
        $this->post('/daftar', $this->data())->assertStatus(429);
    }

    // ------------------------------------------------------------ pengaturan platform

    public function test_super_admin_mengatur_trial_dan_kontak_lalu_tampil_di_produk(): void
    {
        $super = SekolahAktif::jalankan(null, function () {
            $u = User::factory()->create(['username' => 'super', 'sekolah_id' => null]);
            $u->setPeran([Peran::SUPER_ADMIN]);

            return $u;
        });

        $this->actingAs($super)->get('/platform/pengaturan')->assertOk()->assertSee('Percobaan gratis');
        $this->put('/platform/pengaturan', ['nama_produk' => 'SekolahKu', 'kontak_email' => 'halo@sekolahku.id', 'kontak_wa' => '628111222333', 'trial_aktif' => 1, 'trial_hari' => 21, 'pembayaran_driver' => 'nonaktif'])
            ->assertRedirect('/platform/pengaturan');
        $this->put('/platform/pengaturan', ['nama_produk' => 'SekolahKu', 'trial_hari' => 99, 'pembayaran_driver' => 'nonaktif'])->assertSessionHasErrors('trial_hari');

        auth()->logout();
        $this->get('/produk')->assertOk()->assertSee('SekolahKu')->assertSee('Coba gratis 21 hari')->assertSee('halo@sekolahku.id')->assertSee('wa.me/628111222333');
    }

    public function test_pengaturan_platform_hanya_untuk_super_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/platform/pengaturan')->assertRedirect();
    }

    public function test_rahasia_platform_disimpan_terenkripsi(): void
    {
        PengaturanPlatform::putRahasia('midtrans_server_key', 'SB-Mid-server-ABCDEF123456');

        $this->assertSame('SB-Mid-server-ABCDEF123456', PengaturanPlatform::rahasia('midtrans_server_key'));
        $this->assertStringNotContainsString('ABCDEF', (string) \DB::table('platform_setting')->where('key', 'midtrans_server_key')->value('value'));
        $this->assertSame('••••••••••3456', PengaturanPlatform::rahasiaTersembunyi('midtrans_server_key'));
        $this->assertNull(PengaturanPlatform::get('midtrans_server_key')); // get() tidak membocorkan rahasia
    }
}
