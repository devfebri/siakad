<?php

namespace Tests\Feature;

use App\Absensi;
use App\JenisBiaya;
use App\Kelas;
use App\Langganan;
use App\OrangTua;
use App\Paket;
use App\PembayaranSpp;
use App\PengaturanPembayaranSekolah;
use App\PengaturanWa;
use App\PpdbPendaftar;
use App\Siswa;
use App\Support\AksesSekolah;
use App\Support\Keuangan;
use App\Support\SekolahAktif;
use App\Tagihan;
use App\User;
use App\Wa\Driver\CloudSender;
use App\Wa\Driver\FakeSender;
use App\Wa\Driver\FonnteSender;
use App\Wa\Driver\WablasSender;
use App\Wa\NomorWa;
use App\Wa\Wa;
use App\WaLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Tahap 3 poin 6: notifikasi WhatsApp per sekolah (kejadian, templat, antrian, batas laju, tanpa kirim ulang). */
class NotifikasiWaTest extends TestCase
{
    use RefreshDatabase;

    private Kelas $kelas;
    private Siswa $andi;
    private OrangTua $ortu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        FakeSender::reset();
        RateLimiter::clear('wa:'.$this->sekolah->id);

        $this->kelas = Kelas::create(['nama_kelas' => 'VII A', 'tingkat' => 7]);
        $this->andi = $this->siswa('Andi Pratama');
        $this->ortu = $this->tambahOrtu($this->andi, 'Bapak Andi', '081234567890');
    }

    private function siswa(string $nama): Siswa
    {
        return Siswa::create(['user_id' => User::factory()->create(['role' => 'siswa'])->id, 'nisn' => fake()->unique()->numerify('##########'), 'nama_siswa' => $nama, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $this->kelas->id]);
    }

    private function tambahOrtu(Siswa $anak, string $nama, ?string $hp): OrangTua
    {
        $o = OrangTua::create(['user_id' => User::factory()->create(['role' => 'orang_tua'])->id, 'nama' => $nama, 'no_hp' => $hp]);
        $o->hubungkan($anak, 'ayah');

        return $o;
    }

    private function nyalakan(array $ubah = []): PengaturanWa
    {
        $pg = PengaturanWa::milikSekolahAktif();
        $pg->forceFill($ubah + ['aktif' => true, 'driver' => 'fake'])->save();

        return $pg;
    }

    private function alpa(Siswa $s, ?string $tanggal = null): Absensi
    {
        return Absensi::create(['siswa_id' => $s->id, 'kelas_id' => $s->kelas_id, 'tanggal' => $tanggal ?? today()->toDateString(), 'status' => 'alpa']);
    }

    private function pakaiPaket(string $kode): void
    {
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', $kode)->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();
    }

    // ------------------------------------------------------------ pembantu

    public function test_nomor_wa_diseragamkan_ke_format_62(): void
    {
        $this->assertSame('6281234567890', NomorWa::normalkan('0812-3456-7890'));
        $this->assertSame('6281234567890', NomorWa::normalkan('+62 812 3456 7890'));
        $this->assertSame('6281234567890', NomorWa::normalkan('81234567890'));
        $this->assertSame('6281234567890', NomorWa::normalkan('6281234567890'));
        $this->assertNull(NomorWa::normalkan('021-5551234'));   // telepon rumah
        $this->assertNull(NomorWa::normalkan('12345'));
        $this->assertNull(NomorWa::normalkan(null));
        $this->assertNull(NomorWa::normalkan('abc'));
    }

    public function test_render_mengganti_placeholder_dan_membiarkan_yang_tak_dikenal(): void
    {
        $this->assertSame('Halo Budi, jumlah Rp 5.000 {tidak_ada}', Wa::render('Halo {nama}, jumlah {nominal} {tidak_ada}', ['nama' => 'Budi', 'nominal' => 'Rp 5.000']));
    }

    // ------------------------------------------------------------ kejadian alpa

    public function test_bawaan_nonaktif_tidak_mengirim_apa_pun(): void
    {
        $this->alpa($this->andi);

        $this->assertSame(0, WaLog::count());
        $this->assertSame([], FakeSender::$terkirim);
        $this->assertFalse(PengaturanWa::milikSekolahAktif()->aktif);
    }

    public function test_siswa_alpa_mengabari_orang_tua_dengan_templat_terisi(): void
    {
        $this->nyalakan();

        $this->alpa($this->andi);

        $this->assertCount(1, FakeSender::$terkirim);
        $pesan = FakeSender::$terkirim[0];
        $this->assertSame('6281234567890', $pesan['nomor']);
        $this->assertStringContainsString('Bapak Andi', $pesan['pesan']);
        $this->assertStringContainsString('Andi Pratama', $pesan['pesan']);
        $this->assertStringContainsString('VII A', $pesan['pesan']);
        $this->assertStringContainsString('alpa', $pesan['pesan']);
        $this->assertStringContainsString($this->sekolah->nama, $pesan['pesan']);
        $this->assertStringNotContainsString('{', $pesan['pesan']);

        $log = WaLog::first();
        $this->assertSame('terkirim', $log->status);
        $this->assertSame('alpa', $log->kejadian);
        $this->assertNotNull($log->terkirim_pada);
    }

    public function test_status_lain_dan_ortu_tanpa_nomor_sah_tidak_dikirimi(): void
    {
        $this->nyalakan();
        $budi = $this->siswa('Budi');
        $this->tambahOrtu($budi, 'Ibu Budi', null);
        $citra = $this->siswa('Citra');
        $this->tambahOrtu($citra, 'Ibu Citra', '021-555');

        Absensi::create(['siswa_id' => $this->andi->id, 'kelas_id' => $this->kelas->id, 'tanggal' => today(), 'status' => 'sakit']);
        $this->alpa($budi);
        $this->alpa($citra);

        $this->assertSame(0, WaLog::count());
    }

    public function test_kejadian_yang_sama_tidak_dikirim_ulang(): void
    {
        $this->nyalakan();
        $a = $this->alpa($this->andi);

        $a->update(['keterangan' => 'diperbarui']);
        $a->update(['status' => 'sakit']);
        $a->update(['status' => 'alpa']); // balik jadi alpa pada tanggal yang sama
        $this->alpa($this->andi, today()->addDay()->toDateString()); // hari lain = kejadian lain

        $this->assertSame(2, WaLog::count());
        $this->assertCount(2, FakeSender::$terkirim);
    }

    public function test_dua_orang_tua_dan_kejadian_dimatikan(): void
    {
        $pg = $this->nyalakan();
        $this->tambahOrtu($this->andi, 'Ibu Andi', '082111222333');

        $this->alpa($this->andi);
        $this->assertCount(2, FakeSender::$terkirim);

        FakeSender::reset();
        $pg->update(['event_aktif' => ['alpa' => false]]);
        $this->alpa($this->andi, today()->addDays(2)->toDateString());
        $this->assertCount(0, FakeSender::$terkirim);
    }

    public function test_templat_buatan_sekolah_dipakai(): void
    {
        $this->nyalakan(['templat' => ['alpa' => 'Anak {nama_siswa} alpa tanggal {tanggal}!']]);

        $this->alpa($this->andi);

        $this->assertSame('Anak Andi Pratama alpa tanggal '.today()->translatedFormat('d F Y').'!', FakeSender::$terkirim[0]['pesan']);
    }

    public function test_notifikasi_wa_hanya_untuk_paket_pro_dan_premium(): void
    {
        $this->nyalakan();
        $this->pakaiPaket('basic');

        $this->alpa($this->andi);
        $this->assertSame(0, WaLog::count());

        Langganan::query()->update(['paket_id' => Paket::where('kode', 'pro')->value('id')]);
        AksesSekolah::lupakan();
        $this->alpa($this->andi, today()->addDay()->toDateString());
        $this->assertSame(1, WaLog::count());
    }

    // ------------------------------------------------------------ tagihan dan pembayaran

    public function test_tagihan_terbit_satu_pesan_per_siswa_walau_banyak_bulan_dan_tidak_diulang(): void
    {
        $this->nyalakan();
        $biaya = JenisBiaya::create(['nama' => 'SPP', 'nominal' => 150000, 'jenis' => 'bulanan']);

        Keuangan::buatMassal($biaya, [], '2026-11', 3, 10);

        $this->assertCount(1, FakeSender::$terkirim);
        $p = FakeSender::$terkirim[0]['pesan'];
        $this->assertStringContainsString('Andi Pratama', $p);
        $this->assertStringContainsString('SPP November 2026 dan 2 tagihan lain', $p);
        $this->assertStringContainsString('Rp 450.000', $p);       // jumlah seluruh tagihan yang terbit
        $this->assertStringContainsString('10 November 2026', $p);

        Keuangan::buatMassal($biaya, [], '2026-11', 3, 10);        // diulang: tidak ada tagihan baru, tidak ada pesan baru
        $this->assertCount(1, FakeSender::$terkirim);
    }

    public function test_pembayaran_manual_mengabari_orang_tua(): void
    {
        $this->nyalakan();
        $t = Tagihan::create(['siswa_id' => $this->andi->id, 'nama' => 'SPP Nov', 'periode' => '2026-11', 'jumlah' => 150000, 'jatuh_tempo' => today()->addDays(10)]);

        $bayar = Keuangan::bayar($t, 150000, 'tunai');

        $this->assertCount(1, FakeSender::$terkirim);
        $this->assertStringContainsString('Rp 150.000', FakeSender::$terkirim[0]['pesan']);
        $this->assertStringContainsString($bayar->nomor, FakeSender::$terkirim[0]['pesan']);
        $this->assertSame('pembayaran_diterima', WaLog::first()->kejadian);
    }

    public function test_pembayaran_online_banyak_tagihan_hanya_satu_pesan_gabungan(): void
    {
        $this->nyalakan();
        $t1 = Tagihan::create(['siswa_id' => $this->andi->id, 'nama' => 'SPP Nov', 'periode' => '2026-11', 'jumlah' => 100000, 'jatuh_tempo' => today()->addDays(10)]);
        $t2 = Tagihan::create(['siswa_id' => $this->andi->id, 'nama' => 'SPP Des', 'periode' => '2026-12', 'jumlah' => 50000, 'jatuh_tempo' => today()->addDays(40)]);
        $pg = PengaturanPembayaranSekolah::milikSekolahAktif();
        $pg->forceFill(['driver' => 'fake'])->save();

        // transaksi online diproses lewat callback bertanda tangan, seperti pembayaran sungguhan
        Http::fake();
        $user = $this->ortu->user;
        $this->actingAs($user)->post('/ortu/tagihan/bayar', ['tagihan_id' => [$t1->id, $t2->id]]);
        $transaksi = \App\TransaksiSpp::first();
        $callback = \Illuminate\Http\Request::create('/', 'POST', ['order_id' => $transaksi->order_id, 'status' => 'berhasil', 'jumlah' => $transaksi->total, 'nonce' => 'x'], [], [], ['HTTP_X_FAKE_SIGNATURE' => \App\Pembayaran\Driver\FakeGateway::tandatangani($transaksi->order_id, 'berhasil', $transaksi->total)]);
        \App\Pembayaran\SppOnline::terima($pg->fresh(), 'fake', $callback);

        $this->assertSame(2, PembayaranSpp::count());
        $this->assertCount(1, FakeSender::$terkirim);               // satu pesan, bukan dua
        $this->assertStringContainsString('Rp 150.000', FakeSender::$terkirim[0]['pesan']);
    }

    // ------------------------------------------------------------ PPDB

    public function test_hasil_ppdb_dikabarkan_sekali_per_status(): void
    {
        $this->nyalakan();
        $p = PpdbPendaftar::daftar([
            'tahun_ajaran' => '2027/2028', 'nama' => 'Calon Siswa', 'jenis_kelamin' => 'Perempuan', 'tempat_lahir' => 'Jambi', 'tanggal_lahir' => '2012-05-05', 'asal_sekolah' => 'SD 1',
            'alamat' => 'Jl. A', 'nama_ortu' => 'Ibu Calon', 'telepon' => '0813 1111 2222', 'jalur' => 'Reguler', 'status' => 'menunggu',
        ]);
        $this->assertSame(0, WaLog::count());           // pendaftaran baru/menunggu: tidak dikabari

        $p->update(['status' => 'diterima']);
        $p->update(['catatan' => 'lengkap']);
        $this->assertCount(1, FakeSender::$terkirim);
        $this->assertSame('6281311112222', FakeSender::$terkirim[0]['nomor']);
        $this->assertStringContainsString('DITERIMA', FakeSender::$terkirim[0]['pesan']);
        $this->assertStringContainsString('Calon Siswa', FakeSender::$terkirim[0]['pesan']);

        $p->update(['status' => 'ditolak']);             // status berubah lagi = kabar baru
        $this->assertCount(2, FakeSender::$terkirim);
    }

    // ------------------------------------------------------------ pengingat tagihan (harian)

    public function test_pengingat_jatuh_tempo_dan_tunggakan_tidak_terkirim_dua_kali(): void
    {
        $this->nyalakan();
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:30:00'));
        Tagihan::create(['siswa_id' => $this->andi->id, 'nama' => 'SPP Nov', 'periode' => '2026-11', 'jumlah' => 100000, 'jatuh_tempo' => '2026-11-13']); // 3 hari lagi
        Tagihan::create(['siswa_id' => $this->andi->id, 'nama' => 'SPP Okt', 'periode' => '2026-10', 'jumlah' => 80000, 'jatuh_tempo' => '2026-11-09']); // lewat 1 hari
        Tagihan::create(['siswa_id' => $this->andi->id, 'nama' => 'SPP Sep', 'periode' => '2026-09', 'jumlah' => 60000, 'jatuh_tempo' => '2026-11-09']);

        $this->artisan('wa:tagihan-pengingat')->assertSuccessful();
        $this->assertSame(2, WaLog::count());
        $this->assertSame(['jatuh_tempo', 'tunggakan'], WaLog::orderBy('id')->pluck('kejadian')->sort()->values()->all());
        $tunggak = WaLog::where('kejadian', 'tunggakan')->first()->pesan;
        $this->assertStringContainsString('2 tagihan', $tunggak);
        $this->assertStringContainsString('Rp 140.000', $tunggak);

        $this->artisan('wa:tagihan-pengingat')->assertSuccessful();       // dijalankan ulang di hari yang sama
        $this->assertSame(2, WaLog::count());

        Carbon::setTestNow(Carbon::parse('2026-11-11 08:30:00'));          // hari ke-2 setelah jatuh tempo: tidak ada pengingat tunggakan
        $this->artisan('wa:tagihan-pengingat');
        $this->assertSame(2, WaLog::count());

        Carbon::setTestNow(Carbon::parse('2026-11-17 08:30:00'));          // 8 hari setelah jatuh tempo: pengingat mingguan
        $this->artisan('wa:tagihan-pengingat');
        $this->assertSame(3, WaLog::count());

        Carbon::setTestNow();
    }

    public function test_tagihan_lunas_tidak_diingatkan(): void
    {
        $this->nyalakan();
        $t = Tagihan::create(['siswa_id' => $this->andi->id, 'nama' => 'SPP', 'periode' => '2026-11', 'jumlah' => 100000, 'jatuh_tempo' => today()->addDays(3)]);
        Keuangan::bayar($t, 100000);
        $sebelum = WaLog::count();

        $this->artisan('wa:tagihan-pengingat');

        $this->assertSame($sebelum, WaLog::count());
    }

    // ------------------------------------------------------------ pengingat langganan ke admin

    public function test_pengingat_langganan_dikirim_ke_nomor_admin_sekali(): void
    {
        $this->nyalakan(['nomor_admin' => '0811 2222 3333']);
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'pro')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDays(24), 'berakhir' => today()->addDays(6), 'status' => 'aktif']);
        AksesSekolah::lupakan();
        \Illuminate\Support\Facades\Mail::fake();

        $this->artisan('langganan:pengingat')->assertSuccessful();
        $this->artisan('langganan:pengingat')->assertSuccessful();

        $this->assertCount(1, FakeSender::$terkirim);
        $this->assertSame('628112222333'.'3', FakeSender::$terkirim[0]['nomor']);
        $this->assertStringContainsString('Pro', FakeSender::$terkirim[0]['pesan']);
        $this->assertStringContainsString('6 hari', FakeSender::$terkirim[0]['pesan']);
    }

    // ------------------------------------------------------------ antrian, batas laju, galat

    public function test_pengiriman_dibatasi_laju_per_sekolah_dan_sisanya_menunggu(): void
    {
        $this->nyalakan(['batas_per_menit' => 2]);
        for ($i = 1; $i <= 5; $i++) {
            Wa::kabari('alpa', "uji:$i", '0812345678'.$i.'0', ['nama_ortu' => 'O', 'nama_siswa' => 'S', 'kelas' => 'K', 'tanggal' => 'hari ini']);
        }

        $this->assertCount(2, FakeSender::$terkirim);
        $this->assertSame(2, WaLog::where('status', 'terkirim')->count());
        $this->assertSame(3, WaLog::where('status', 'antri')->count()); // tidak hilang, menunggu giliran
    }

    public function test_kesalahan_permanen_ditandai_gagal_dan_gangguan_sementara_tetap_antri(): void
    {
        $this->nyalakan();
        FakeSender::$nomorGagal = ['6281234567890'];
        $this->alpa($this->andi);
        $log = WaLog::first();
        $this->assertSame('gagal', $log->status);
        $this->assertStringContainsString('tidak terdaftar', $log->galat);

        // gerbang sungguhan down: percobaan dicatat, pesan tetap antri untuk dicoba lagi
        FakeSender::reset();
        $pg = PengaturanWa::first();
        $pg->forceFill(['driver' => 'fonnte', 'token' => 'tok-uji'])->save();
        Http::fake(['api.fonnte.com/*' => Http::response('error', 502)]);
        Wa::kabari('alpa', 'uji:down', '081299990000', ['nama_ortu' => 'O', 'nama_siswa' => 'S', 'kelas' => 'K', 'tanggal' => 'x']);

        $log2 = WaLog::where('referensi', 'uji:down')->first();
        $this->assertSame('antri', $log2->status);
        $this->assertSame(1, $log2->percobaan);
        $this->assertNotNull($log2->galat);
    }

    public function test_pesan_dilewati_bila_whatsapp_dimatikan_sebelum_terkirim(): void
    {
        $pg = $this->nyalakan(['batas_per_menit' => 1]);
        Wa::kabari('alpa', 'uji:1', '081200000001', ['nama_ortu' => 'O', 'nama_siswa' => 'S', 'kelas' => 'K', 'tanggal' => 'x']);
        Wa::kabari('alpa', 'uji:2', '081200000002', ['nama_ortu' => 'O', 'nama_siswa' => 'S', 'kelas' => 'K', 'tanggal' => 'x']); // tertahan batas laju

        $pg->update(['aktif' => false]);
        \App\Jobs\KirimWa::dispatchSync(WaLog::where('referensi', 'uji:2')->value('id'), $this->sekolah->id, 100); // batas laju longgar: kini giliran pesan yang tertahan

        $this->assertSame('dilewati', WaLog::where('referensi', 'uji:2')->value('status'));
        $this->assertCount(1, FakeSender::$terkirim);
    }

    // ------------------------------------------------------------ driver

    public function test_driver_fonnte_wablas_dan_cloud_memanggil_api_yang_benar(): void
    {
        Http::fake([
            'api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['f-1']], 200),
            'pati.wablas.com/*' => Http::response(['status' => true, 'data' => ['messages' => [['id' => 'w-1']]]], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200),
        ]);

        $f = (new FonnteSender('tok-fonnte'))->kirim('6281234567890', 'Halo');
        $w = (new WablasSender('tok-wablas', 'https://pati.wablas.com/', 'sekret'))->kirim('6281234567890', 'Halo');
        $c = (new CloudSender('tok-cloud', '1234567890'))->kirim('6281234567890', 'Halo');

        $this->assertSame(['f-1', 'w-1', 'wamid.1'], [$f->idPesan, $w->idPesan, $c->idPesan]);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.fonnte.com/send' && $r->hasHeader('Authorization', 'tok-fonnte') && $r['target'] === '6281234567890' && $r['countryCode'] === '62');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://pati.wablas.com/api/send-message' && $r->hasHeader('Authorization', 'tok-wablas.sekret') && $r['phone'] === '6281234567890');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://graph.facebook.com/v20.0/1234567890/messages' && $r->hasHeader('Authorization', 'Bearer tok-cloud') && $r['to'] === '6281234567890' && $r['text']['body'] === 'Halo');
    }

    public function test_driver_membedakan_galat_permanen_dan_sementara(): void
    {
        Http::fake(['api.fonnte.com/*' => Http::sequence()->push(['status' => false, 'reason' => 'token invalid'], 200)->push('error', 503)]);

        $permanen = (new FonnteSender('x'))->kirim('6281234567890', 'Halo');
        $sementara = (new FonnteSender('x'))->kirim('6281234567890', 'Halo');

        $this->assertFalse($permanen->ok);
        $this->assertFalse($permanen->ulang);
        $this->assertStringContainsString('token invalid', $permanen->galat);
        $this->assertFalse($sementara->ok);
        $this->assertTrue($sementara->ulang);
    }

    // ------------------------------------------------------------ menu pengaturan admin

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_menyimpan_pengaturan_dengan_kunci_terenkripsi_dan_tersembunyi(): void
    {
        $this->actingAs($this->admin());
        $dasar = ['driver' => 'fonnte', 'batas_per_menit' => 15, 'nomor_admin' => '0811 2222 3333', 'event' => ['alpa' => 1, 'pembayaran_diterima' => 1]];

        $this->get('/admin/wa')->assertOk()->assertSee('WhatsApp masih mati')->assertSee('Siswa alpa');
        $this->put('/admin/wa', $dasar + ['aktif' => 1])->assertSessionHasErrors('aktif');   // kunci belum diisi: tidak boleh menyala
        $this->put('/admin/wa', $dasar + ['aktif' => 1, 'token' => 'TOKEN-RAHASIA-FONNTE-7777'])->assertRedirect('/admin/wa');

        $pg = PengaturanWa::first();
        $this->assertTrue($pg->aktif);
        $this->assertSame('TOKEN-RAHASIA-FONNTE-7777', $pg->token);
        $this->assertStringNotContainsString('RAHASIA', (string) \DB::table('pengaturan_wa_sekolah')->value('token'));
        $this->assertSame('6281122223333', $pg->nomor_admin);
        $this->assertSame(['alpa' => true, 'tagihan_terbit' => false, 'jatuh_tempo' => false, 'tunggakan' => false, 'pembayaran_diterima' => true, 'ppdb' => false, 'langganan' => false], $pg->event_aktif);
        $this->assertArrayNotHasKey('token', $pg->toArray());

        $halaman = $this->get('/admin/wa')->assertOk()->assertSee('WhatsApp menyala');
        $this->assertStringNotContainsString('RAHASIA', $halaman->getContent());
        $halaman->assertSee('7777');

        $this->put('/admin/wa', $dasar + ['aktif' => 1, 'token' => ''])->assertRedirect('/admin/wa'); // kosong = kunci lama dipertahankan
        $this->assertSame('TOKEN-RAHASIA-FONNTE-7777', $pg->fresh()->token);
    }

    public function test_templat_diperiksa_placeholder_nomor_dan_alamat(): void
    {
        $this->actingAs($this->admin());
        $dasar = ['driver' => 'wablas', 'batas_per_menit' => 15, 'token' => 'tok'];

        $this->put('/admin/wa', $dasar + ['templat' => ['alpa' => 'Halo {nama_siswa} dan {bukan_isian}']])->assertSessionHasErrors('templat.alpa');
        $this->put('/admin/wa', $dasar + ['nomor_admin' => '021-555'])->assertSessionHasErrors('nomor_admin');
        $this->put('/admin/wa', $dasar + ['server_url' => 'https://10.0.0.1'])->assertSessionHasErrors('server_url');
        $this->put('/admin/wa', ['batas_per_menit' => 999] + $dasar)->assertSessionHasErrors('batas_per_menit');

        $this->put('/admin/wa', $dasar + ['templat' => ['alpa' => 'Halo {nama_ortu}, {nama_siswa} alpa. {nama_sekolah}']])->assertRedirect('/admin/wa');
        $this->assertSame('Halo {nama_ortu}, {nama_siswa} alpa. {nama_sekolah}', PengaturanWa::first()->templat['alpa']);

        // teks sama dengan bawaan tidak disimpan sebagai tiruan
        $this->put('/admin/wa', $dasar + ['templat' => ['alpa' => config('wa-templat.alpa.templat')]])->assertRedirect('/admin/wa');
        $this->assertNull(PengaturanWa::first()->templat);
    }

    public function test_uji_kirim_memakai_pengirim_sekolah(): void
    {
        $this->actingAs($this->admin());
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['t-1']], 200)]);

        $this->post('/admin/wa/uji', ['nomor_uji' => '0812 3456 7890'])->assertSessionHasErrors('nomor_uji'); // belum ada kunci

        PengaturanWa::milikSekolahAktif()->forceFill(['driver' => 'fonnte', 'token' => 'tok'])->save();
        $this->post('/admin/wa/uji', ['nomor_uji' => 'bukan nomor'])->assertSessionHasErrors('nomor_uji');
        $this->post('/admin/wa/uji', ['nomor_uji' => '0812 3456 7890'])->assertRedirect('/admin/wa')->assertSessionHas('toast_success');
        Http::assertSent(fn (HttpRequest $r) => $r['target'] === '6281234567890' && str_contains($r['message'], 'Pesan percobaan'));
        $this->assertSame(0, WaLog::count()); // uji tidak masuk riwayat kejadian
    }

    public function test_riwayat_menampilkan_nomor_tersamar_dan_menu_hanya_admin_dan_paket(): void
    {
        $this->nyalakan();
        $this->alpa($this->andi);
        $this->actingAs($this->admin());

        $this->get('/admin/wa')->assertOk()->assertSee('Terkirim: 1')->assertSee('62812••••890')->assertDontSee('6281234567890');

        $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/admin/wa')->assertRedirect();

        $this->pakaiPaket('basic');
        $this->actingAs($this->admin())->get('/admin/wa')->assertRedirect();
        $this->get('/admin/dashboard')->assertDontSee(route('admin/wa'));
    }

    public function test_data_wa_terpisah_antar_sekolah(): void
    {
        $this->nyalakan();
        $lain = \App\Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        SekolahAktif::jalankan($lain, fn () => PengaturanWa::milikSekolahAktif()->forceFill(['aktif' => false, 'driver' => 'fake'])->save());

        $this->alpa($this->andi);

        $this->assertSame(1, WaLog::count());
        $this->assertSame(0, WaLog::withoutGlobalScopes()->where('sekolah_id', $lain->id)->count());
        $this->assertSame(2, PengaturanWa::withoutGlobalScopes()->count());
    }
}
