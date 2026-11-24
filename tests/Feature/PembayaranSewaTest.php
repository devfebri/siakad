<?php

namespace Tests\Feature;

use App\CallbackGateway;
use App\Invoice;
use App\Langganan;
use App\LogAktivitas;
use App\Paket;
use App\Pembayaran\Driver\FakeGateway;
use App\Pembayaran\Driver\MidtransGateway;
use App\Pembayaran\Gerbang;
use App\Pembayaran\PermintaanBayar;
use App\Pembayaran\Sewa;
use App\PembayaranGateway;
use App\PengaturanPlatform;
use App\Peran;
use App\Support\AksesSekolah;
use App\Support\Penyewaan;
use App\Support\SekolahAktif;
use App\Support\Terbilang;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Tahap 3 poin 4: pembayaran sewa lewat gerbang (Midtrans, Xendit, simulasi), callback idempotent, dan invoice/kuitansi PDF. */
class PembayaranSewaTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI_MIDTRANS = 'SB-Mid-server-RAHASIA123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->sekolah->update(['tanpa_batas' => false]);
    }

    private function pakaiMidtrans(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'midtrans', 'pembayaran_sandbox' => '1']);
        PengaturanPlatform::putRahasia('midtrans_server_key', self::KUNCI_MIDTRANS);
    }

    private function pakaiXendit(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'xendit']);
        PengaturanPlatform::putRahasia('xendit_secret_key', 'xnd_development_RAHASIA');
        PengaturanPlatform::putRahasia('xendit_callback_token', 'token-callback-xendit');
    }

    private function invoice(string $paket = 'pro', int $durasi = 1): Invoice
    {
        return Penyewaan::buatInvoice($this->sekolah, Paket::where('kode', $paket)->first(), $durasi);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Callback Midtrans dengan tanda tangan sah (atau diubah-ubah lewat $ubah). */
    private function callbackMidtrans(string $orderId, array $ubah = [], ?string $kunci = null): array
    {
        $d = $ubah + ['order_id' => $orderId, 'status_code' => '200', 'gross_amount' => '200000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'trx-1', 'payment_type' => 'qris'];
        $d['signature_key'] ??= hash('sha512', $d['order_id'].$d['status_code'].$d['gross_amount'].($kunci ?? self::KUNCI_MIDTRANS));

        return $d;
    }

    // ------------------------------------------------------------ pembantu kecil

    public function test_terbilang_rupiah(): void
    {
        $this->assertSame('nol rupiah', Terbilang::rupiah(0));
        $this->assertSame('seratus ribu rupiah', Terbilang::rupiah(100000));
        $this->assertSame('satu juta delapan puluh ribu rupiah', Terbilang::rupiah(1080000));
        $this->assertSame('tiga juta tiga ratus enam puluh ribu rupiah', Terbilang::rupiah(3360000));
        $this->assertSame('seribu lima ratus rupiah', Terbilang::rupiah(1500));
        $this->assertSame('sebelas ribu rupiah', Terbilang::rupiah(11000));
    }

    public function test_pemilihan_gerbang_dari_pengaturan_dan_kunci_terenkripsi(): void
    {
        $this->assertNull(Gerbang::aktif()); // bawaan: nonaktif

        $this->pakaiMidtrans();
        $this->assertInstanceOf(MidtransGateway::class, Gerbang::aktif());
        $this->assertStringNotContainsString('RAHASIA', (string) \DB::table('platform_setting')->where('key', 'midtrans_server_key')->value('value'));

        PengaturanPlatform::put(['pembayaran_driver' => 'xendit']);
        $this->assertNull(Gerbang::aktif()); // dipilih tetapi kunci belum diisi
        $this->pakaiXendit();
        $this->assertSame('xendit', Gerbang::aktif()->kode());

        PengaturanPlatform::put(['pembayaran_driver' => 'fake']);
        $this->assertInstanceOf(FakeGateway::class, Gerbang::aktif());
    }

    // ------------------------------------------------------------ Midtrans

    public function test_midtrans_membuat_tagihan_dengan_kunci_server_dan_mode_sandbox(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['app.sandbox.midtrans.com/*' => Http::response(['token' => 'tok-1', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/tok-1'], 201)]);
        $invoice = $this->invoice();

        $bayar = Sewa::mulai($invoice, 'Ibu Admin', 'admin@uji.test');

        $this->assertSame('https://app.sandbox.midtrans.com/snap/v2/vtweb/tok-1', $bayar->url_bayar);
        $this->assertSame('menunggu', $bayar->status);
        $this->assertStringStartsWith($invoice->nomor.'-', $bayar->order_id);
        Http::assertSent(function (HttpRequest $r) use ($invoice, $bayar) {
            return $r->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
                && $r->hasHeader('Authorization', 'Basic '.base64_encode(self::KUNCI_MIDTRANS.':'))
                && $r['transaction_details']['order_id'] === $bayar->order_id
                && $r['transaction_details']['gross_amount'] === (int) $invoice->jumlah;
        });

        // tagihan yang masih berlaku dipakai ulang, tidak membuat transaksi baru di gerbang
        $lagi = Sewa::mulai($invoice, 'Ibu Admin', 'admin@uji.test');
        $this->assertSame($bayar->id, $lagi->id);
        Http::assertSentCount(1);
    }

    public function test_midtrans_produksi_memakai_alamat_produksi(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'midtrans', 'pembayaran_sandbox' => '0']);
        PengaturanPlatform::putRahasia('midtrans_server_key', 'Mid-server-PROD');
        Http::fake(['app.midtrans.com/*' => Http::response(['token' => 't', 'redirect_url' => 'https://app.midtrans.com/snap/v2/vtweb/t'], 201)]);

        Sewa::mulai($this->invoice(), 'A', null);

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://app.midtrans.com/snap/v1/transactions');
    }

    public function test_gerbang_menolak_atau_tidak_terjangkau_memberi_pesan_ramah(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['error_messages' => ['Access denied due to unauthorized transaction']], 401)]);

        $this->expectException(\App\Pembayaran\GerbangException::class);
        Sewa::mulai($this->invoice(), 'A', null);
    }

    public function test_callback_midtrans_sah_melunasi_invoice_dan_membuat_langganan(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/t'], 201)]);
        $invoice = $this->invoice(); // Pro 1 bulan = 200.000
        $bayar = Sewa::mulai($invoice, 'A', null);

        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id))->assertOk();

        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame('berhasil', $bayar->fresh()->status);
        $this->assertSame('qris', $bayar->fresh()->metode);
        $l = Langganan::where('sekolah_id', $this->sekolah->id)->first();
        $this->assertSame($invoice->id, $l->invoice_id);
        $this->assertSame(today()->toDateString(), $l->mulai->toDateString());
        $this->assertStringContainsString('Midtrans', $invoice->fresh()->metode);
        AksesSekolah::lupakan();
        $this->assertSame('aktif', $this->sekolah->fresh()->statusLangganan());
    }

    public function test_callback_ganda_tidak_menambah_masa_aktif_dua_kali(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/t'], 201)]);
        $bayar = Sewa::mulai($this->invoice(), 'A', null);
        $isi = $this->callbackMidtrans($bayar->order_id);

        $this->postJson('/api/webhook/pembayaran/midtrans', $isi)->assertOk();
        $this->postJson('/api/webhook/pembayaran/midtrans', $isi)->assertOk()->assertJsonPath('message', 'Sudah diterima sebelumnya.');
        // kejadian yang sama dikirim ulang dengan id transaksi berbeda (kunci beda) tetap tidak menambah langganan
        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id, ['transaction_id' => 'trx-2']))->assertOk();
        // dan lewat jalur proses langsung
        $this->assertSame('diproses', Sewa::proses(new \App\Pembayaran\NotifikasiBayar('midtrans', $bayar->order_id, 'berhasil', 200000, 'k-lain')));

        $this->assertSame(1, Langganan::where('sekolah_id', $this->sekolah->id)->count());
        $this->assertSame(1, Invoice::where('status', 'lunas')->count());
    }

    public function test_perpanjangan_menyambung_setelah_langganan_berjalan(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/t'], 201)]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'pro')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDays(5), 'berakhir' => today()->addDays(10), 'status' => 'aktif']);
        $bayar = Sewa::mulai($this->invoice(), 'A', null);

        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id))->assertOk();

        $baru = Langganan::where('sekolah_id', $this->sekolah->id)->orderByDesc('id')->first();
        $this->assertSame(today()->addDays(11)->toDateString(), $baru->mulai->toDateString()); // aturan perpanjangan yang sudah ada
    }

    public function test_callback_dengan_tanda_tangan_salah_ditolak_dan_dicatat(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/t'], 201)]);
        $invoice = $this->invoice();
        $bayar = Sewa::mulai($invoice, 'A', null);

        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id, [], 'kunci-penyerang'))->assertStatus(401);
        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id, ['signature_key' => 'asal']))->assertStatus(401);
        $this->postJson('/api/webhook/pembayaran/midtrans', ['order_id' => $bayar->order_id])->assertStatus(401);

        $this->assertSame('menunggu', $invoice->fresh()->status);
        $this->assertSame(0, Langganan::where('sekolah_id', $this->sekolah->id)->count());
        $this->assertSame(3, CallbackGateway::where('valid', false)->where('hasil', 'ditolak')->count());
    }

    public function test_jumlah_callback_berbeda_tidak_melunasi(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/t'], 201)]);
        $invoice = $this->invoice();
        $bayar = Sewa::mulai($invoice, 'A', null);

        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id, ['gross_amount' => '1000.00']))->assertOk();

        $this->assertSame('menunggu', $invoice->fresh()->status);
        $this->assertNotNull($bayar->fresh()->catatan);
        $this->assertTrue(LogAktivitas::where('aksi', 'pembayaran.jumlah_beda')->exists());
    }

    public function test_order_tak_dikenal_status_gagal_kedaluwarsa_dan_menunggu(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/t'], 201)]);
        $invoice = $this->invoice();
        $bayar = Sewa::mulai($invoice, 'A', null);

        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans('ORDER-ASING'))->assertOk(); // tak dikenal: diabaikan
        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id, ['transaction_status' => 'pending', 'transaction_id' => 'a']))->assertOk();
        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id, ['transaction_status' => 'capture', 'fraud_status' => 'challenge', 'transaction_id' => 'b']))->assertOk();
        $this->assertSame('menunggu', $bayar->fresh()->status);
        $this->assertSame('menunggu', $invoice->fresh()->status);

        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id, ['transaction_status' => 'expire', 'transaction_id' => 'c']))->assertOk();
        $this->assertSame('kedaluwarsa', $bayar->fresh()->status);
        $this->assertSame('menunggu', $invoice->fresh()->status); // invoice tetap bisa dibayar lagi
    }

    public function test_pembayaran_masuk_setelah_lunas_manual_tidak_memperpanjang_lagi(): void
    {
        $this->pakaiMidtrans();
        Http::fake(['*' => Http::response(['token' => 't', 'redirect_url' => 'https://pay.test/t'], 201)]);
        $invoice = $this->invoice();
        $bayar = Sewa::mulai($invoice, 'A', null);
        Penyewaan::konfirmasiLunas($invoice, 'Transfer BCA'); // super admin lebih dulu mengonfirmasi manual

        $this->postJson('/api/webhook/pembayaran/midtrans', $this->callbackMidtrans($bayar->order_id))->assertOk();

        $this->assertSame(1, Langganan::where('sekolah_id', $this->sekolah->id)->count());
        $this->assertTrue(LogAktivitas::where('aksi', 'pembayaran.kelebihan')->exists());
        $this->assertStringContainsString('kelebihan', $bayar->fresh()->catatan);
    }

    // ------------------------------------------------------------ Xendit

    public function test_xendit_membuat_tagihan_dan_callback_diverifikasi_dengan_token(): void
    {
        $this->pakaiXendit();
        Http::fake(['api.xendit.co/*' => Http::response(['id' => 'inv-xnd-1', 'invoice_url' => 'https://checkout.xendit.co/web/inv-xnd-1', 'expiry_date' => now()->addDay()->toIso8601String()], 200)]);
        $invoice = $this->invoice();
        $bayar = Sewa::mulai($invoice, 'A', 'admin@uji.test');

        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.xendit.co/v2/invoices' && $r->hasHeader('Authorization', 'Basic '.base64_encode('xnd_development_RAHASIA:')) && $r['external_id'] === $bayar->order_id && $r['amount'] === 200000);
        $this->assertSame('https://checkout.xendit.co/web/inv-xnd-1', $bayar->url_bayar);

        $isi = ['id' => 'inv-xnd-1', 'external_id' => $bayar->order_id, 'status' => 'PAID', 'amount' => 200000, 'paid_amount' => 200000, 'payment_channel' => 'BCA'];
        $this->postJson('/api/webhook/pembayaran/xendit', $isi, ['x-callback-token' => 'token-salah'])->assertStatus(401);
        $this->postJson('/api/webhook/pembayaran/xendit', $isi)->assertStatus(401); // tanpa token
        $this->assertSame('menunggu', $invoice->fresh()->status);

        $this->postJson('/api/webhook/pembayaran/xendit', $isi, ['x-callback-token' => 'token-callback-xendit'])->assertOk();
        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->postJson('/api/webhook/pembayaran/xendit', $isi, ['x-callback-token' => 'token-callback-xendit'])->assertOk();
        $this->assertSame(1, Langganan::where('sekolah_id', $this->sekolah->id)->count());
    }

    public function test_callback_untuk_gerbang_yang_belum_dikonfigurasi_ditolak(): void
    {
        $this->postJson('/api/webhook/pembayaran/xendit', ['external_id' => 'x', 'status' => 'PAID'], ['x-callback-token' => 'apa-saja'])->assertStatus(404);
        $this->postJson('/api/webhook/pembayaran/tidak-ada', [])->assertNotFound();
    }

    // ------------------------------------------------------------ alur admin sekolah (simulasi)

    public function test_admin_sekolah_membayar_lewat_simulasi_sampai_lunas(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'fake']);
        $invoice = $this->invoice('premium', 6);
        $this->actingAs($this->admin());

        $this->get('/admin/langganan')->assertOk()->assertSee('Bayar Online')->assertSee('Invoice');

        $res = $this->post('/admin/langganan/invoice/'.$invoice->id.'/bayar');
        $res->assertRedirect();
        $bayar = PembayaranGateway::first();
        $this->assertSame(route('pembayaran.simulasi', $bayar->order_id), $res->headers->get('Location'));

        $this->get(route('pembayaran.simulasi', $bayar->order_id))->assertOk()->assertSee('Simulasi')->assertSee('1.890.000');
        $kembali = $this->post(route('pembayaran.simulasi.kirim', $bayar->order_id), ['status' => 'berhasil']);
        $this->assertStringContainsString('/admin/langganan?bayar=kembali', $kembali->headers->get('Location'));

        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame(6, Langganan::where('sekolah_id', $this->sekolah->id)->first()->durasi_bulan);
        $this->get('/admin/langganan?bayar=kembali')->assertOk()->assertSee('Terima kasih')->assertSee('Kuitansi');
    }

    public function test_simulasi_gagal_membiarkan_invoice_menunggu_dan_bisa_dibayar_lagi(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'fake']);
        $invoice = $this->invoice();
        $this->actingAs($this->admin());
        $this->post('/admin/langganan/invoice/'.$invoice->id.'/bayar');
        $bayar = PembayaranGateway::first();

        $this->post(route('pembayaran.simulasi.kirim', $bayar->order_id), ['status' => 'gagal']);
        $this->assertSame('gagal', $bayar->fresh()->status);
        $this->assertSame('menunggu', $invoice->fresh()->status);

        $this->post('/admin/langganan/invoice/'.$invoice->id.'/bayar')->assertRedirect(); // tagihan baru
        $this->assertSame(2, PembayaranGateway::count());
    }

    public function test_callback_simulasi_lewat_route_api_memerlukan_tanda_tangan(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'fake']);
        $invoice = $this->invoice();
        $bayar = Sewa::mulai($invoice, 'A', null);
        $isi = ['order_id' => $bayar->order_id, 'status' => 'berhasil', 'jumlah' => 200000];

        $this->postJson('/api/webhook/pembayaran/fake', $isi)->assertStatus(401);
        $this->postJson('/api/webhook/pembayaran/fake', $isi, ['X-Fake-Signature' => FakeGateway::tandatangani($bayar->order_id, 'berhasil', 200000)])->assertOk();
        $this->assertSame('lunas', $invoice->fresh()->status);
    }

    public function test_bayar_online_ditolak_bila_nonaktif_invoice_orang_lain_atau_sudah_lunas(): void
    {
        $invoice = $this->invoice();
        $this->actingAs($this->admin());

        $this->post('/admin/langganan/invoice/'.$invoice->id.'/bayar')->assertRedirect(route('admin/langganan'))->assertSessionHas('toast_error');
        $this->get('/admin/langganan')->assertOk()->assertDontSee('Bayar Online');

        PengaturanPlatform::put(['pembayaran_driver' => 'fake']);
        $lain = \App\Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif']);
        $invoiceLain = Penyewaan::buatInvoice($lain, Paket::where('kode', 'basic')->first(), 1);
        $this->post('/admin/langganan/invoice/'.$invoiceLain->id.'/bayar')->assertNotFound();

        Penyewaan::konfirmasiLunas($invoice);
        $this->post('/admin/langganan/invoice/'.$invoice->id.'/bayar')->assertSessionHas('toast_error');
    }

    public function test_sekolah_baca_saja_tetap_boleh_membayar(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'fake']);
        $invoice = $this->invoice(); // sekolah belum punya langganan = mode baca-saja
        $admin = $this->admin();
        AksesSekolah::lupakan();
        $this->actingAs($admin);
        $this->assertTrue(AksesSekolah::bacaSaja());

        $this->post('/admin/langganan/invoice/'.$invoice->id.'/bayar')->assertRedirect()->assertSessionMissing('toast_error');
        $this->assertSame(1, PembayaranGateway::count());
    }

    // ------------------------------------------------------------ PDF

    public function test_invoice_dan_kuitansi_pdf(): void
    {
        $invoice = $this->invoice('premium', 12);
        $this->actingAs($this->admin());

        $res = $this->get('/admin/langganan/invoice/'.$invoice->id.'/pdf')->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('invoice-'.$invoice->nomor.'.pdf', $res->headers->get('Content-Disposition'));

        $this->get('/admin/langganan/invoice/'.$invoice->id.'/kuitansi')->assertNotFound(); // belum lunas

        Penyewaan::konfirmasiLunas($invoice->fresh(), 'Transfer');
        $k = $this->get('/admin/langganan/invoice/'.$invoice->id.'/kuitansi')->assertOk();
        $this->assertStringContainsString('kuitansi-'.$invoice->nomor.'.pdf', $k->headers->get('Content-Disposition'));
    }

    public function test_pdf_invoice_sekolah_lain_tidak_bisa_diunduh_tetapi_super_admin_bisa(): void
    {
        $lain = \App\Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif']);
        $invoiceLain = Penyewaan::buatInvoice($lain, Paket::where('kode', 'basic')->first(), 1);

        $this->actingAs($this->admin())->get('/admin/langganan/invoice/'.$invoiceLain->id.'/pdf')->assertNotFound();

        $super = SekolahAktif::jalankan(null, function () {
            $u = User::factory()->create(['username' => 'super', 'sekolah_id' => null]);
            $u->setPeran([Peran::SUPER_ADMIN]);

            return $u;
        });
        auth()->logout();
        $this->actingAs($super)->get('/platform/invoice/'.$invoiceLain->id.'/pdf')->assertOk();
    }

    // ------------------------------------------------------------ pengaturan platform

    public function test_kunci_gerbang_disimpan_terenkripsi_dan_tidak_pernah_tampil_utuh(): void
    {
        $super = SekolahAktif::jalankan(null, function () {
            $u = User::factory()->create(['username' => 'super', 'sekolah_id' => null]);
            $u->setPeran([Peran::SUPER_ADMIN]);

            return $u;
        });
        $this->actingAs($super);
        $dasar = ['nama_produk' => 'SIAKAD', 'trial_hari' => 14, 'pembayaran_driver' => 'midtrans', 'pembayaran_sandbox' => 1];

        $this->put('/platform/pengaturan', $dasar + ['midtrans_server_key' => self::KUNCI_MIDTRANS])->assertRedirect('/platform/pengaturan');
        $this->assertSame(self::KUNCI_MIDTRANS, PengaturanPlatform::rahasia('midtrans_server_key'));
        $this->assertStringNotContainsString('RAHASIA', (string) \DB::table('platform_setting')->where('key', 'midtrans_server_key')->value('value'));

        $halaman = $this->get('/platform/pengaturan')->assertOk()->assertSee('/api/webhook/pembayaran/midtrans');
        $this->assertStringNotContainsString('RAHASIA123', $halaman->getContent());
        $halaman->assertSee('3456'); // hanya empat karakter terakhir

        // kunci dikosongkan = tidak berubah
        $this->put('/platform/pengaturan', $dasar + ['midtrans_server_key' => ''])->assertRedirect();
        $this->assertSame(self::KUNCI_MIDTRANS, PengaturanPlatform::rahasia('midtrans_server_key'));
        $this->assertInstanceOf(MidtransGateway::class, Gerbang::aktif());

        $this->put('/platform/pengaturan', ['pembayaran_driver' => 'bukan-gerbang'] + $dasar)->assertSessionHasErrors('pembayaran_driver');
    }

    public function test_driver_simulasi_tidak_tersedia_di_produksi(): void
    {
        PengaturanPlatform::put(['pembayaran_driver' => 'fake']);
        $this->assertNotNull(Gerbang::aktif());

        $this->app['env'] = 'production';
        $this->assertNull(Gerbang::driver('fake'));
        $this->get('/pembayaran/simulasi/APA-SAJA')->assertNotFound();
    }
}
