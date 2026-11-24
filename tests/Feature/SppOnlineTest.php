<?php

namespace Tests\Feature;

use App\Events\PembayaranSppDiterima;
use App\Kelas;
use App\Langganan;
use App\LogAktivitas;
use App\Mail\PembayaranSppMail;
use App\OrangTua;
use App\Paket;
use App\PembayaranSpp;
use App\Pembayaran\Driver\FakeGateway;
use App\PengaturanPembayaranSekolah;
use App\Sekolah;
use App\Siswa;
use App\Support\AksesSekolah;
use App\Support\Keuangan;
use App\Support\SekolahAktif;
use App\Tagihan;
use App\TransaksiSpp;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Tahap 3 poin 5: SPP online oleh orang tua dengan kunci gerbang milik sekolah, beberapa tagihan sekaligus, biaya admin, dan notifikasi bendahara. */
class SppOnlineTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'SB-Mid-server-SEKOLAH-UJI-1234';

    private Kelas $kelas;
    private Siswa $andi;
    private Siswa $budi;
    private User $ortu;
    private Tagihan $t1;
    private Tagihan $t2;
    private Tagihan $t3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->kelas = Kelas::create(['nama_kelas' => 'VII A', 'tingkat' => 7]);
        $this->andi = $this->siswa('Andi');
        $this->budi = $this->siswa('Budi');
        $this->ortu = User::factory()->create(['role' => 'orang_tua', 'email' => 'ortu@contoh.test']);
        $ortu = OrangTua::create(['user_id' => $this->ortu->id, 'nama' => 'Bapak Andi']);
        $ortu->hubungkan($this->andi, 'ayah');
        $ortu->hubungkan($this->budi, 'ayah');

        $this->t1 = $this->tagihan($this->andi, 100000, '2026-11');
        $this->t2 = $this->tagihan($this->andi, 50000, '2026-12');
        $this->t3 = $this->tagihan($this->budi, 70000, '2026-11');
    }

    private function siswa(string $nama, ?Sekolah $sekolah = null): Siswa
    {
        return SekolahAktif::jalankan($sekolah ?? $this->sekolah, function () use ($nama, $sekolah) {
            $kelas = $sekolah ? Kelas::create(['nama_kelas' => 'X', 'tingkat' => 7]) : $this->kelas;

            return Siswa::create(['user_id' => User::factory()->create(['role' => 'siswa'])->id, 'nisn' => fake()->unique()->numerify('##########'), 'nama_siswa' => $nama, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $kelas->id]);
        });
    }

    private function tagihan(Siswa $s, int $jumlah, string $periode): Tagihan
    {
        return SekolahAktif::jalankan(Sekolah::find($s->sekolah_id), fn () => Tagihan::create(['siswa_id' => $s->id, 'nama' => 'SPP '.$periode, 'periode' => $periode, 'jumlah' => $jumlah, 'jatuh_tempo' => today()->addDays(5)]));
    }

    private function pg(array $ubah = [], ?Sekolah $sekolah = null): PengaturanPembayaranSekolah
    {
        return SekolahAktif::jalankan($sekolah ?? $this->sekolah, function () use ($ubah) {
            $p = PengaturanPembayaranSekolah::milikSekolahAktif();
            $p->forceFill($ubah + ['driver' => 'midtrans', 'midtrans_server_key' => self::KUNCI])->save();

            return $p;
        });
    }

    private function fakeGerbang(): void
    {
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'tok', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/tok'], 201)]);
    }

    private function bayar(array $ids): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->ortu)->post('/ortu/tagihan/bayar', ['tagihan_id' => $ids]);
    }

    private function callbackMidtrans(PengaturanPembayaranSekolah $pg, string $orderId, int $jumlah, array $ubah = [], ?string $kunci = null): array
    {
        $d = $ubah + ['order_id' => $orderId, 'status_code' => '200', 'gross_amount' => number_format($jumlah, 2, '.', ''), 'transaction_status' => 'settlement', 'transaction_id' => 'trx-1', 'payment_type' => 'qris'];
        $d['signature_key'] ??= hash('sha512', $d['order_id'].$d['status_code'].$d['gross_amount'].($kunci ?? self::KUNCI));

        return $d;
    }

    // ------------------------------------------------------------ pengaturan sekolah (admin)

    public function test_admin_mengatur_gerbang_sekolah_dengan_kunci_terenkripsi_dan_tersembunyi(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $dasar = ['driver' => 'midtrans', 'sandbox' => 1, 'biaya_admin_ditanggung' => 'ortu', 'biaya_admin_tetap' => 4000, 'biaya_admin_persen' => 1];

        $this->get('/admin/keuangan/online')->assertOk()->assertSee('Belum aktif');
        $this->put('/admin/keuangan/online', $dasar)->assertSessionHasErrors('driver'); // kunci belum diisi
        $this->put('/admin/keuangan/online', $dasar + ['midtrans_server_key' => self::KUNCI])->assertRedirect('/admin/keuangan/online');

        $pg = PengaturanPembayaranSekolah::first();
        $this->assertSame(self::KUNCI, $pg->midtrans_server_key);
        $this->assertStringNotContainsString('SEKOLAH-UJI', (string) \DB::table('pengaturan_pembayaran_sekolah')->value('midtrans_server_key'));
        $this->assertSame(4000, $pg->biaya_admin_tetap);

        $halaman = $this->get('/admin/keuangan/online')->assertOk()->assertSee('Aktif.')->assertSee($pg->urlCallback('midtrans'));
        $this->assertStringNotContainsString('SEKOLAH-UJI', $halaman->getContent());
        $halaman->assertSee('1234'); // empat karakter terakhir saja

        // kolom kunci kosong = kunci lama dipertahankan; kunci tidak ikut keluar di JSON model
        $this->put('/admin/keuangan/online', $dasar + ['midtrans_server_key' => ''])->assertRedirect();
        $this->assertSame(self::KUNCI, $pg->fresh()->midtrans_server_key);
        $this->assertArrayNotHasKey('midtrans_server_key', $pg->fresh()->toArray());
        $this->assertArrayNotHasKey('webhook_token', $pg->fresh()->toArray());

        $this->put('/admin/keuangan/online', ['driver' => 'xendit', 'xendit_secret_key' => 'xnd_development_X'] + $dasar)->assertSessionHasErrors('xendit_callback_token');
        $this->put('/admin/keuangan/online', ['biaya_admin_persen' => 50] + $dasar)->assertSessionHasErrors('biaya_admin_persen');
        $this->put('/admin/keuangan/online', ['driver' => 'nonaktif'] + $dasar)->assertRedirect();
        $this->assertNull($pg->fresh()->gerbangAktif());
    }

    public function test_pengaturan_pembayaran_online_hanya_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'bendahara']));

        $this->get('/admin/keuangan/online')->assertRedirect();
        $this->put('/bendahara/keuangan/online', [])->assertNotFound(); // rute ini tidak ada di panel bendahara
        $this->get('/bendahara/dashboard')->assertOk()->assertDontSee('Pembayaran Online');
    }

    // ------------------------------------------------------------ halaman orang tua

    public function test_tombol_bayar_hanya_tampil_bila_gerbang_sekolah_aktif(): void
    {
        $this->actingAs($this->ortu)->get('/ortu/tagihan')->assertOk()->assertDontSee('Bayar Sekarang')->assertSee('langsung di sekolah');

        $this->pg(['biaya_admin_ditanggung' => 'ortu', 'biaya_admin_tetap' => 4000, 'biaya_admin_persen' => 1]);
        $this->get('/ortu/tagihan')->assertOk()->assertSee('Bayar Sekarang')->assertSee('name="tagihan_id[]"', false)->assertSee('\u0022ditanggung\u0022:\u0022ortu\u0022', false);

        $this->pg(['driver' => 'nonaktif']);
        $this->get('/ortu/tagihan')->assertOk()->assertDontSee('Bayar Sekarang');
    }

    // ------------------------------------------------------------ memulai pembayaran

    public function test_bayar_beberapa_tagihan_sekaligus_memakai_kunci_sekolah_dan_biaya_admin_ortu(): void
    {
        $pg = $this->pg(['biaya_admin_ditanggung' => 'ortu', 'biaya_admin_tetap' => 4000, 'biaya_admin_persen' => 1]);
        $this->fakeGerbang();

        $res = $this->bayar([$this->t1->id, $this->t2->id, $this->t3->id]);
        $res->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/tok');

        $t = TransaksiSpp::first();
        $this->assertSame(220000, $t->subtotal);
        $this->assertSame(6200, $t->biaya_admin);        // 4.000 + 1% x 220.000
        $this->assertSame(226200, $t->total);
        $this->assertSame('ortu', $t->biaya_ditanggung);
        $this->assertSame(3, $t->item()->count());
        $this->assertSame($this->ortu->id, $t->user_id);

        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode(self::KUNCI.':')) && $r['transaction_details']['gross_amount'] === 226200 && $r['transaction_details']['order_id'] === $t->order_id);
    }

    public function test_biaya_admin_ditanggung_sekolah_tidak_menambah_total(): void
    {
        $this->pg(['biaya_admin_ditanggung' => 'sekolah', 'biaya_admin_tetap' => 4000, 'biaya_admin_persen' => 1]);
        $this->fakeGerbang();

        $this->bayar([$this->t1->id]);

        $t = TransaksiSpp::first();
        $this->assertSame(100000, $t->subtotal);
        $this->assertSame(5000, $t->biaya_admin);   // dicatat, tetapi
        $this->assertSame(100000, $t->total);        // tidak ditagihkan ke orang tua
        Http::assertSent(fn (HttpRequest $r) => $r['transaction_details']['gross_amount'] === 100000);
    }

    public function test_tagihan_yang_sama_yang_masih_menunggu_dilanjutkan_bukan_dibuat_ulang(): void
    {
        $this->pg();
        $this->fakeGerbang();

        $this->bayar([$this->t1->id, $this->t2->id])->assertRedirect();
        $this->bayar([$this->t2->id, $this->t1->id])->assertRedirect(); // urutan beda, himpunan sama
        $this->assertSame(1, TransaksiSpp::count());
        Http::assertSentCount(1);

        $this->bayar([$this->t1->id])->assertRedirect();                 // himpunan lain = transaksi baru
        $this->assertSame(2, TransaksiSpp::count());

        $this->get('/ortu/tagihan')->assertOk()->assertSee('Lanjutkan Pembayaran');
    }

    public function test_tagihan_milik_orang_lain_lunas_atau_kosong_ditolak(): void
    {
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        $citra = $this->siswa('Citra', $lain);
        $tCitra = $this->tagihan($citra, 90000, '2026-11');
        $tNonAnak = $this->tagihan($this->siswa('Bukan Anak'), 90000, '2026-11');
        $this->pg();
        $this->fakeGerbang();
        Keuangan::bayar($this->t2, 50000);

        $this->bayar([$tCitra->id])->assertSessionHasErrors('tagihan_id');          // sekolah lain
        $this->bayar([$tNonAnak->id])->assertSessionHasErrors('tagihan_id');        // bukan anak akun ini
        $this->bayar([$this->t2->id])->assertSessionHasErrors('tagihan_id');        // sudah lunas
        $this->bayar([$this->t1->id, $tNonAnak->id])->assertSessionHasErrors('tagihan_id'); // campuran: ditolak seluruhnya
        $this->bayar([])->assertSessionHasErrors('tagihan_id');
        $this->assertSame(0, TransaksiSpp::count());
        Http::assertNothingSent();
    }

    public function test_gerbang_gagal_tidak_menyimpan_transaksi_dan_memberi_pesan_ramah(): void
    {
        $this->pg();
        Http::fake(['*' => Http::response(['error_messages' => ['Access denied']], 401)]);

        $this->bayar([$this->t1->id])->assertRedirect(route('ortu/tagihan'))->assertSessionHas('toast_error');
        $this->assertSame(0, TransaksiSpp::count());
    }

    public function test_tanpa_gerbang_aktif_bayar_ditolak(): void
    {
        $this->bayar([$this->t1->id])->assertRedirect(route('ortu/tagihan'))->assertSessionHas('toast_error');
    }

    // ------------------------------------------------------------ callback

    private function transaksiBerjalan(array $pgUbah = [], ?array $ids = null): array
    {
        $pg = $this->pg($pgUbah);
        $this->fakeGerbang();
        $this->bayar($ids ?? [$this->t1->id, $this->t2->id, $this->t3->id]);

        return [$pg, TransaksiSpp::latest('id')->first()];
    }

    public function test_callback_sah_melunasi_semua_tagihan_dengan_kuitansi_dan_memberi_tahu_bendahara(): void
    {
        Mail::fake();
        Event::fake([PembayaranSppDiterima::class]);
        User::factory()->create(['role' => 'bendahara', 'email' => 'bendahara@sekolah.test']);
        [$pg, $t] = $this->transaksiBerjalan(['biaya_admin_ditanggung' => 'ortu', 'biaya_admin_tetap' => 4000]);

        $this->postJson('/api/webhook/spp/'.$pg->webhook_token.'/midtrans', $this->callbackMidtrans($pg, $t->order_id, $t->total))->assertOk();

        foreach ([$this->t1, $this->t2, $this->t3] as $tg) {
            $this->assertSame('lunas', $tg->fresh()->status);
        }
        $p = PembayaranSpp::orderBy('id')->get();
        $this->assertCount(3, $p);
        $this->assertSame([100000, 50000, 70000], $p->pluck('jumlah')->all()); // hanya jumlah tagihan, bukan biaya admin
        $this->assertSame(['online'], $p->pluck('metode')->unique()->all());
        $this->assertSame([$t->order_id], $p->pluck('transaksi_id')->unique()->all());
        $this->assertCount(3, array_unique($p->pluck('nomor')->all()));

        $t->refresh();
        $this->assertSame('berhasil', $t->status);
        $this->assertSame('qris', $t->metode);
        Event::assertDispatched(PembayaranSppDiterima::class, fn ($e) => $e->transaksi->id === $t->id && count($e->pembayaranIds) === 3);
    }

    public function test_bendahara_menerima_email_sekali_saja(): void
    {
        Mail::fake();
        User::factory()->create(['role' => 'bendahara', 'email' => 'bendahara@sekolah.test']);
        User::factory()->create(['role' => 'bendahara', 'email' => 'tanpa@ortu.invalid']); // email palsu tidak dikirimi
        [$pg, $t] = $this->transaksiBerjalan();
        $isi = $this->callbackMidtrans($pg, $t->order_id, $t->total);

        $this->postJson('/api/webhook/spp/'.$pg->webhook_token.'/midtrans', $isi)->assertOk();
        $this->postJson('/api/webhook/spp/'.$pg->webhook_token.'/midtrans', $isi)->assertOk()->assertJsonPath('message', 'Sudah diterima sebelumnya.');

        Mail::assertQueued(PembayaranSppMail::class, 1);
        Mail::assertQueued(PembayaranSppMail::class, fn ($m) => $m->hasTo('bendahara@sekolah.test'));
    }

    public function test_callback_ganda_atau_beda_kunci_tidak_membayar_dua_kali(): void
    {
        [$pg, $t] = $this->transaksiBerjalan();
        $url = '/api/webhook/spp/'.$pg->webhook_token.'/midtrans';

        $this->postJson($url, $this->callbackMidtrans($pg, $t->order_id, $t->total))->assertOk();
        $this->postJson($url, $this->callbackMidtrans($pg, $t->order_id, $t->total, ['transaction_id' => 'trx-lain']))->assertOk();

        $this->assertSame(3, PembayaranSpp::count());
        $this->assertSame(100000, $this->t1->fresh()->terbayar);
        $this->assertSame('lunas', $this->t1->fresh()->status);
    }

    public function test_jumlah_callback_harus_sama_dengan_total_transaksi(): void
    {
        [$pg, $t] = $this->transaksiBerjalan(['biaya_admin_ditanggung' => 'ortu', 'biaya_admin_tetap' => 4000]);

        // pembayar hanya membayar tagihan tanpa biaya admin: tidak diterima otomatis
        $this->postJson('/api/webhook/spp/'.$pg->webhook_token.'/midtrans', $this->callbackMidtrans($pg, $t->order_id, $t->subtotal))->assertOk();

        $this->assertSame(0, PembayaranSpp::count());
        $this->assertSame('menunggu', $t->fresh()->status);
        $this->assertTrue(LogAktivitas::where('aksi', 'spp_online.jumlah_beda')->exists());
    }

    public function test_tanda_tangan_salah_ditolak_dan_token_tak_dikenal_404(): void
    {
        [$pg, $t] = $this->transaksiBerjalan();
        $url = '/api/webhook/spp/'.$pg->webhook_token.'/midtrans';

        $this->postJson($url, $this->callbackMidtrans($pg, $t->order_id, $t->total, [], 'kunci-penyerang'))->assertStatus(401);
        $this->postJson($url, $this->callbackMidtrans($pg, $t->order_id, $t->total, ['signature_key' => 'asal']))->assertStatus(401);
        $this->postJson('/api/webhook/spp/'.str_repeat('a', 40).'/midtrans', $this->callbackMidtrans($pg, $t->order_id, $t->total))->assertNotFound();
        $this->postJson($url, $this->callbackMidtrans($pg, $t->order_id, $t->total))->assertOk(); // yang sah tetap diterima

        $this->assertSame(2, \App\CallbackGateway::where('valid', false)->where('hasil', 'ditolak')->where('sekolah_id', $this->sekolah->id)->count());
        $this->assertSame(3, PembayaranSpp::count());
    }

    public function test_kunci_dan_callback_sekolah_lain_tidak_bisa_dipakai_silang(): void
    {
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        $pgLain = $this->pg(['midtrans_server_key' => 'SB-Mid-server-SEKOLAH-LAIN-9999'], $lain);
        [$pg, $t] = $this->transaksiBerjalan();

        // alamat callback sekolah lain + tanda tangan dengan kunci sekolah ini: ditolak
        $this->postJson('/api/webhook/spp/'.$pgLain->webhook_token.'/midtrans', $this->callbackMidtrans($pg, $t->order_id, $t->total))->assertStatus(401);
        // alamat callback sekolah lain + kunci sekolah lain, tetapi order milik sekolah ini: diabaikan
        $this->postJson('/api/webhook/spp/'.$pgLain->webhook_token.'/midtrans', $this->callbackMidtrans($pgLain, $t->order_id, $t->total, [], 'SB-Mid-server-SEKOLAH-LAIN-9999'))->assertOk();

        $this->assertSame(0, PembayaranSpp::withoutGlobalScopes()->count());
        $this->assertSame('menunggu', $t->fresh()->status);
    }

    public function test_tagihan_yang_sudah_lunas_manual_dilewati_tanpa_kelebihan_catatan_pembayaran(): void
    {
        [$pg, $t] = $this->transaksiBerjalan([], [$this->t1->id, $this->t2->id]);
        Keuangan::bayar($this->t1, 100000, 'tunai'); // bendahara menerima tunai lebih dulu

        $this->postJson('/api/webhook/spp/'.$pg->webhook_token.'/midtrans', $this->callbackMidtrans($pg, $t->order_id, $t->total))->assertOk();

        $this->assertSame(1, PembayaranSpp::where('metode', 'online')->count());       // hanya t2
        $this->assertSame(100000, $this->t1->fresh()->terbayar);                        // t1 tidak dibayar dua kali
        $this->assertSame('lunas', $this->t2->fresh()->status);
        $this->assertStringContainsString('kelebihan', $t->fresh()->catatan);
        $this->assertTrue(LogAktivitas::where('aksi', 'spp_online.kelebihan')->exists());
    }

    public function test_status_gagal_dan_kedaluwarsa_tidak_membayar(): void
    {
        [$pg, $t] = $this->transaksiBerjalan();
        $url = '/api/webhook/spp/'.$pg->webhook_token.'/midtrans';

        $this->postJson($url, $this->callbackMidtrans($pg, $t->order_id, $t->total, ['transaction_status' => 'pending', 'transaction_id' => 'a']))->assertOk();
        $this->assertSame('menunggu', $t->fresh()->status);
        $this->postJson($url, $this->callbackMidtrans($pg, $t->order_id, $t->total, ['transaction_status' => 'expire', 'transaction_id' => 'b']))->assertOk();

        $this->assertSame('kedaluwarsa', $t->fresh()->status);
        $this->assertSame(0, PembayaranSpp::count());
        $this->assertSame('belum', $this->t1->fresh()->status);
    }

    public function test_xendit_sekolah_memakai_token_callback_sekolah(): void
    {
        $pg = $this->pg(['driver' => 'xendit', 'xendit_secret_key' => 'xnd_development_SEKOLAH', 'xendit_callback_token' => 'token-sekolah-uji']);
        Http::fake(['api.xendit.co/*' => Http::response(['id' => 'inv-1', 'invoice_url' => 'https://checkout.xendit.co/web/inv-1'], 200)]);

        $this->bayar([$this->t1->id])->assertRedirect('https://checkout.xendit.co/web/inv-1');
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_SEKOLAH:')));
        $t = TransaksiSpp::first();
        $isi = ['id' => 'inv-1', 'external_id' => $t->order_id, 'status' => 'PAID', 'paid_amount' => 100000, 'amount' => 100000, 'payment_channel' => 'BNI'];
        $url = '/api/webhook/spp/'.$pg->webhook_token.'/xendit';

        $this->postJson($url, $isi, ['x-callback-token' => 'token-platform-bukan-sekolah'])->assertStatus(401);
        $this->postJson($url, $isi, ['x-callback-token' => 'token-sekolah-uji'])->assertOk();
        $this->assertSame('lunas', $this->t1->fresh()->status);
    }

    // ------------------------------------------------------------ alur lengkap (simulasi), paket, dan notifikasi

    public function test_alur_lengkap_orang_tua_membayar_lewat_simulasi(): void
    {
        $this->pg(['driver' => 'fake', 'midtrans_server_key' => null]);

        $res = $this->bayar([$this->t1->id, $this->t3->id]);
        $t = TransaksiSpp::first();
        $this->assertSame(route('pembayaran.simulasi-spp', $t->order_id), $res->headers->get('Location'));

        $this->get(route('pembayaran.simulasi-spp', $t->order_id))->assertOk()->assertSee('Simulasi')->assertSee('170.000');
        $kembali = $this->post(route('pembayaran.simulasi-spp.kirim', $t->order_id), ['status' => 'berhasil']);
        $this->assertStringContainsString('/ortu/tagihan?bayar=kembali', $kembali->headers->get('Location'));

        $this->assertSame('lunas', $this->t1->fresh()->status);
        $this->assertSame('lunas', $this->t3->fresh()->status);
        $this->actingAs($this->ortu)->get('/ortu/tagihan?bayar=kembali')->assertOk()->assertSee('Terima kasih')->assertSee('Unduh');

        // bendahara/admin melihat pemberitahuan di panel
        $this->actingAs(User::factory()->create(['role' => 'bendahara']))->get('/bendahara/dashboard')->assertOk()->assertSee('2 pembayaran SPP online baru');
    }

    public function test_simulasi_hanya_untuk_gerbang_simulasi_dan_tidak_di_produksi(): void
    {
        $this->pg(['driver' => 'fake', 'midtrans_server_key' => null]);
        $this->bayar([$this->t1->id]);
        $t = TransaksiSpp::first();

        $this->app['env'] = 'production';
        $this->get(route('pembayaran.simulasi-spp', $t->order_id))->assertNotFound();
        $this->assertNull(PengaturanPembayaranSekolah::first()->gerbang('fake')); // tidak ada gerbang simulasi di produksi
        $this->assertSame('belum', $this->t1->fresh()->status);
    }

    public function test_callback_simulasi_tanpa_tanda_tangan_ditolak(): void
    {
        $pg = $this->pg(['driver' => 'fake', 'midtrans_server_key' => null]);
        $this->bayar([$this->t1->id]);
        $t = TransaksiSpp::first();
        $isi = ['order_id' => $t->order_id, 'status' => 'berhasil', 'jumlah' => $t->total];

        $this->postJson('/api/webhook/spp/'.$pg->webhook_token.'/fake', $isi)->assertStatus(401);
        $this->postJson('/api/webhook/spp/'.$pg->webhook_token.'/fake', $isi, ['X-Fake-Signature' => FakeGateway::tandatangani($t->order_id, 'berhasil', $t->total)])->assertOk();
        $this->assertSame('lunas', $this->t1->fresh()->status);
    }

    public function test_spp_online_mengikuti_paket_dan_tetap_bisa_saat_sewa_baca_saja(): void
    {
        $this->pg();
        $this->fakeGerbang();
        $this->sekolah->update(['tanpa_batas' => false]);
        $langganan = Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'basic')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDays(40), 'berakhir' => today()->addDays(60), 'status' => 'aktif']);
        AksesSekolah::lupakan();

        $this->bayar([$this->t1->id])->assertStatus(403);                        // Basic: tanpa modul Keuangan
        $this->assertSame(0, TransaksiSpp::count());

        $langganan->update(['paket_id' => Paket::where('kode', 'pro')->value('id'), 'berakhir' => today()->subDays(30)]); // Pro, sewa lewat masa tenggang = baca-saja
        AksesSekolah::lupakan();
        $this->assertTrue(AksesSekolah::bacaSaja());
        $this->bayar([$this->t1->id])->assertRedirect('https://app.sandbox.midtrans.com/snap/v2/vtweb/tok');   // orang tua tetap boleh membayar
        $this->assertSame(1, TransaksiSpp::count());
    }
}
