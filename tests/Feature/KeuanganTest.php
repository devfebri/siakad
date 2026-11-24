<?php

namespace Tests\Feature;

use App\JenisBiaya;
use App\Kelas;
use App\Langganan;
use App\OrangTua;
use App\Paket;
use App\PembayaranSpp;
use App\Sekolah;
use App\Siswa;
use App\Support\AksesSekolah;
use App\Support\Keuangan;
use App\Support\SekolahAktif;
use App\Tagihan;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Tahap 3 poin 5a: keuangan dasar (jenis biaya, tagihan massal, pembayaran, kuitansi), panel bendahara, dan tagihan di portal orang tua. */
class KeuanganTest extends TestCase
{
    use RefreshDatabase;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->kelas = Kelas::create(['nama_kelas' => 'VII A', 'tingkat' => 7]);
    }

    private function siswa(string $nama, ?Kelas $kelas = null, string $status = 'aktif', ?Sekolah $sekolah = null): Siswa
    {
        return SekolahAktif::jalankan($sekolah ?? $this->sekolah, function () use ($nama, $kelas, $status, $sekolah) {
            $user = User::factory()->create(['role' => 'siswa']);
            $k = $kelas ?? ($sekolah ? Kelas::create(['nama_kelas' => 'X', 'tingkat' => 7]) : $this->kelas);

            return Siswa::create(['user_id' => $user->id, 'nisn' => fake()->unique()->numerify('##########'), 'nama_siswa' => $nama, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $k->id, 'status' => $status]);
        });
    }

    private function spp(int $nominal = 150000, string $jenis = 'bulanan'): JenisBiaya
    {
        return JenisBiaya::create(['nama' => 'SPP', 'nominal' => $nominal, 'jenis' => $jenis, 'aktif' => true]);
    }

    private function masukAdmin(): User
    {
        return tap(User::factory()->create(['role' => 'admin']), fn ($u) => $this->actingAs($u));
    }

    private function masukBendahara(): User
    {
        return tap(User::factory()->create(['role' => 'bendahara']), fn ($u) => $this->actingAs($u));
    }

    private function paket(string $kode): void
    {
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', $kode)->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();
    }

    // ------------------------------------------------------------ jenis biaya

    public function test_admin_mengelola_jenis_biaya(): void
    {
        $this->masukAdmin();

        $this->get('/admin/keuangan/biaya')->assertOk()->assertSee('Jenis Biaya');
        $this->postJson('/admin/keuangan/biaya', ['nama' => 'SPP', 'nominal' => 150000, 'jenis' => 'bulanan'])->assertCreated();
        $this->postJson('/admin/keuangan/biaya', ['nama' => 'SPP', 'nominal' => 1, 'jenis' => 'bulanan'])->assertStatus(422)->assertJsonValidationErrors('nama');
        $this->postJson('/admin/keuangan/biaya', ['nama' => 'Seragam', 'nominal' => -5, 'jenis' => 'sekali'])->assertStatus(422)->assertJsonValidationErrors('nominal');

        $biaya = JenisBiaya::first();
        $this->putJson('/admin/keuangan/biaya/'.$biaya->id, ['nama' => 'SPP Bulanan', 'nominal' => 175000, 'jenis' => 'bulanan', 'aktif' => 0])->assertOk();
        $this->assertSame(175000, $biaya->fresh()->nominal);
        $this->assertFalse($biaya->fresh()->aktif);

        $this->getJson('/admin/keuangan/biaya/data')->assertOk()->assertJsonPath('data.0.nominal_rp', 'Rp 175.000');

        Tagihan::create(['siswa_id' => $this->siswa('A')->id, 'jenis_biaya_id' => $biaya->id, 'nama' => 'SPP', 'periode' => '2026-11', 'jumlah' => 1000, 'jatuh_tempo' => today()]);
        $this->deleteJson('/admin/keuangan/biaya/'.$biaya->id)->assertStatus(409);
    }

    // ------------------------------------------------------------ tagihan massal

    public function test_tagihan_massal_bulanan_hanya_untuk_siswa_aktif_berkelas_dan_tidak_ganda(): void
    {
        $this->siswa('Andi');
        $this->siswa('Budi');
        $this->siswa('Pindah', null, 'lulus');
        $lain = Kelas::create(['nama_kelas' => 'VIII B', 'tingkat' => 8]);
        $this->siswa('Citra', $lain);
        $biaya = $this->spp();
        $this->masukAdmin();
        $isi = ['jenis_biaya_id' => $biaya->id, 'kelas_id' => $this->kelas->id, 'bulan_mulai' => '2026-11', 'jumlah_bulan' => 3, 'tanggal_jatuh_tempo' => 10];

        $this->postJson('/admin/keuangan/tagihan', $isi)->assertCreated()->assertJsonPath('message', '6 tagihan dibuat.'); // 2 siswa x 3 bulan
        $this->assertSame(6, Tagihan::count());
        $t = Tagihan::where('periode', '2026-12')->first();
        $this->assertSame('SPP Desember 2026', $t->nama);
        $this->assertSame('2026-12-10', $t->jatuh_tempo->toDateString());
        $this->assertSame(150000, $t->jumlah);
        $this->assertSame('belum', $t->status);

        // diulang: tidak ada tagihan ganda
        $this->postJson('/admin/keuangan/tagihan', $isi)->assertCreated()->assertJsonPath('message', '0 tagihan dibuat (6 sudah ada, dilewati).');
        $this->assertSame(6, Tagihan::count());

        // semua kelas, bulan yang sama: hanya tambahan siswa kelas lain
        $this->postJson('/admin/keuangan/tagihan', ['kelas_id' => ''] + $isi)->assertCreated();
        $this->assertSame(9, Tagihan::count());
        $this->assertSame(0, Tagihan::whereHas('siswa', fn ($q) => $q->where('nama_siswa', 'Pindah'))->count());
    }

    public function test_tagihan_sekali_bayar_nominal_khusus_dan_validasi(): void
    {
        $this->siswa('Andi');
        $biaya = $this->spp(500000, 'sekali');
        $this->masukAdmin();

        $this->postJson('/admin/keuangan/tagihan', ['jenis_biaya_id' => $biaya->id, 'bulan_mulai' => '2026-07', 'tanggal_jatuh_tempo' => 15, 'nominal' => 450000, 'jumlah_bulan' => 12])->assertCreated();
        $this->assertSame(1, Tagihan::count()); // sekali bayar: abaikan jumlah bulan
        $this->assertSame(450000, Tagihan::first()->jumlah);

        $this->postJson('/admin/keuangan/tagihan', ['jenis_biaya_id' => 999, 'bulan_mulai' => '2026-07', 'tanggal_jatuh_tempo' => 15])->assertStatus(422);
        $this->postJson('/admin/keuangan/tagihan', ['jenis_biaya_id' => $biaya->id, 'bulan_mulai' => 'salah', 'tanggal_jatuh_tempo' => 15])->assertStatus(422)->assertJsonValidationErrors('bulan_mulai');
        $this->postJson('/admin/keuangan/tagihan', ['jenis_biaya_id' => $biaya->id, 'bulan_mulai' => '2026-07', 'tanggal_jatuh_tempo' => 40])->assertStatus(422);

        $nol = JenisBiaya::create(['nama' => 'Gratis', 'nominal' => 0, 'jenis' => 'bulanan']);
        $this->postJson('/admin/keuangan/tagihan', ['jenis_biaya_id' => $nol->id, 'bulan_mulai' => '2026-07', 'tanggal_jatuh_tempo' => 10])->assertStatus(422)->assertJsonValidationErrors('jenis_biaya_id');
    }

    public function test_tagihan_massal_tanpa_siswa_memberi_pesan_jelas(): void
    {
        $biaya = $this->spp();
        $this->masukAdmin();

        $this->postJson('/admin/keuangan/tagihan', ['jenis_biaya_id' => $biaya->id, 'bulan_mulai' => '2026-07', 'tanggal_jatuh_tempo' => 10])
            ->assertCreated()->assertJsonPath('message', 'Tidak ada siswa aktif yang bisa ditagih. Pastikan siswa sudah masuk kelas.');
    }

    // ------------------------------------------------------------ pembayaran

    private function tagihan(int $jumlah = 150000, ?Siswa $siswa = null, string $periode = '2026-11', ?string $jatuhTempo = null): Tagihan
    {
        return Tagihan::create(['siswa_id' => ($siswa ?? $this->siswa('Andi'))->id, 'nama' => 'SPP', 'periode' => $periode, 'jumlah' => $jumlah, 'jatuh_tempo' => $jatuhTempo ?? today()->addDays(5)]);
    }

    public function test_pembayaran_cicilan_sampai_lunas_dengan_nomor_kuitansi_berurutan(): void
    {
        $t = $this->tagihan(150000);
        $this->masukAdmin();

        $this->postJson("/admin/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 50000, 'metode' => 'tunai'])->assertOk()->assertJsonStructure(['message', 'kuitansi']);
        $t->refresh();
        $this->assertSame('sebagian', $t->status);
        $this->assertSame(50000, $t->terbayar);
        $this->assertSame(100000, $t->sisa);

        $this->postJson("/admin/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 100000, 'metode' => 'transfer', 'catatan' => 'BCA'])->assertOk();
        $this->assertSame('lunas', $t->fresh()->status);

        $nomor = PembayaranSpp::orderBy('id')->pluck('nomor')->all();
        $this->assertSame(['KW-'.now()->format('Ym').'-0001', 'KW-'.now()->format('Ym').'-0002'], $nomor);
        $this->assertSame(auth()->id(), PembayaranSpp::first()->diterima_oleh);
    }

    public function test_pembayaran_ditolak_bila_melebihi_sisa_sudah_lunas_atau_tidak_sah(): void
    {
        $t = $this->tagihan(100000);
        $this->masukAdmin();
        $url = "/admin/keuangan/tagihan/{$t->id}/bayar";

        $this->postJson($url, ['jumlah' => 100001, 'metode' => 'tunai'])->assertStatus(422)->assertJsonValidationErrors('jumlah');
        $this->postJson($url, ['jumlah' => 0, 'metode' => 'tunai'])->assertStatus(422);
        $this->postJson($url, ['jumlah' => 1000, 'metode' => 'bitcoin'])->assertStatus(422)->assertJsonValidationErrors('metode');
        $this->postJson($url, ['jumlah' => 1000, 'metode' => 'online'])->assertOk(); // metode valid di sistem (dipakai pembayaran online)
        $this->postJson($url, ['jumlah' => 1000, 'metode' => 'tunai', 'tanggal' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('tanggal');

        $this->postJson($url, ['jumlah' => 99000, 'metode' => 'tunai'])->assertOk();
        $this->postJson($url, ['jumlah' => 1, 'metode' => 'tunai'])->assertStatus(422)->assertJsonValidationErrors('jumlah'); // sudah lunas
        $this->assertSame(2, PembayaranSpp::count());
    }

    public function test_batalkan_pembayaran_mengembalikan_status_tagihan(): void
    {
        $t = $this->tagihan(100000);
        $this->masukAdmin();
        $this->postJson("/admin/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 100000, 'metode' => 'tunai']);
        $p = PembayaranSpp::first();
        $this->assertSame('lunas', $t->fresh()->status);

        $this->postJson("/admin/keuangan/pembayaran/{$p->id}/batal", [])->assertStatus(422)->assertJsonValidationErrors('alasan');
        $this->postJson("/admin/keuangan/pembayaran/{$p->id}/batal", ['alasan' => 'Salah input nominal'])->assertOk();

        $t->refresh();
        $this->assertSame('belum', $t->status);
        $this->assertSame(0, $t->terbayar);
        $this->assertTrue($p->fresh()->dibatalkan());
        $this->assertSame('Salah input nominal', $p->fresh()->alasan_batal);
        $this->assertSame(1, PembayaranSpp::count()); // tidak dihapus
        $this->postJson("/admin/keuangan/pembayaran/{$p->id}/batal", ['alasan' => 'lagi'])->assertStatus(422); // tidak bisa dua kali

        // bisa dibayar lagi, dengan nomor kuitansi baru
        $this->postJson("/admin/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 100000, 'metode' => 'tunai'])->assertOk();
        $this->assertSame('KW-'.now()->format('Ym').'-0002', PembayaranSpp::orderByDesc('id')->value('nomor'));
    }

    public function test_batalkan_tagihan_hanya_bila_belum_ada_pembayaran(): void
    {
        $a = $this->tagihan(100000, null, '2026-11');
        $b = $this->tagihan(100000, null, '2026-12');
        $this->masukAdmin();

        $this->postJson("/admin/keuangan/tagihan/{$a->id}/batal")->assertOk();
        $this->assertSame('batal', $a->fresh()->status);
        $this->postJson("/admin/keuangan/tagihan/{$a->id}/bayar", ['jumlah' => 1000, 'metode' => 'tunai'])->assertStatus(422); // sudah dibatalkan

        $this->postJson("/admin/keuangan/tagihan/{$b->id}/bayar", ['jumlah' => 1000, 'metode' => 'tunai']);
        $this->postJson("/admin/keuangan/tagihan/{$b->id}/batal")->assertStatus(422);
        $this->assertSame('sebagian', $b->fresh()->status);
    }

    public function test_data_tagihan_dapat_disaring_dan_dicari(): void
    {
        $andi = $this->siswa('Andi Pratama');
        $this->tagihan(100000, $andi, '2026-10', today()->subDays(3)->toDateString()); // terlambat
        $lunas = $this->tagihan(100000, $this->siswa('Budi'), '2026-10', today()->subDays(3)->toDateString());
        Keuangan::bayar($lunas, 100000);
        $this->masukAdmin();

        $this->getJson('/admin/keuangan/tagihan/data')->assertOk()->assertJsonPath('recordsTotal', 2);
        $this->getJson('/admin/keuangan/tagihan/data?terlambat=1')->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.siswa', 'Andi Pratama')->assertJsonPath('data.0.status', 'Terlambat|danger');
        $this->getJson('/admin/keuangan/tagihan/data?status=lunas')->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.siswa', 'Budi');
        $this->getJson('/admin/keuangan/tagihan/data?search[value]=Pratama')->assertJsonPath('recordsFiltered', 1);
    }

    public function test_ringkasan_angka_keuangan(): void
    {
        $a = $this->tagihan(200000, null, '2026-10', today()->subDays(2)->toDateString());
        $b = $this->tagihan(100000, $this->siswa('Budi'));
        Keuangan::bayar($a, 50000);
        $this->masukAdmin();

        $r = Keuangan::ringkasan();
        $this->assertSame(250000, $r['sisa_tagihan']);      // 150.000 + 100.000
        $this->assertSame(1, $r['jumlah_menunggak']);
        $this->assertSame(50000, $r['tertagih_hari_ini']);

        $this->get('/admin/keuangan')->assertOk()->assertSee('Rp 250.000')->assertSee('Rp 50.000')->assertSee('Tagihan lewat jatuh tempo');
    }

    public function test_data_keuangan_terpisah_antar_sekolah(): void
    {
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        $tLain = SekolahAktif::jalankan($lain, fn () => Tagihan::create(['siswa_id' => $this->siswa('Orang Lain', null, 'aktif', $lain)->id, 'nama' => 'SPP', 'periode' => '2026-11', 'jumlah' => 99000]));
        $this->tagihan(150000);
        $this->masukAdmin();

        $this->postJson("/admin/keuangan/tagihan/{$tLain->id}/bayar", ['jumlah' => 1000, 'metode' => 'tunai'])->assertNotFound();
        $this->getJson('/admin/keuangan/tagihan/data')->assertJsonPath('recordsTotal', 1);
        $this->assertSame(0, PembayaranSpp::withoutGlobalScopes()->count());
    }

    public function test_kuitansi_pdf_admin(): void
    {
        $t = $this->tagihan(1080000);
        $this->masukAdmin();
        $this->postJson("/admin/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 1080000, 'metode' => 'tunai']);
        $p = PembayaranSpp::first();

        $res = $this->get("/admin/keuangan/pembayaran/{$p->id}/kuitansi")->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString("kuitansi-{$p->nomor}.pdf", $res->headers->get('Content-Disposition'));
    }

    // ------------------------------------------------------------ panel bendahara

    public function test_bendahara_punya_panel_sendiri_dan_hanya_membuka_keuangan(): void
    {
        $user = User::factory()->create(['role' => 'bendahara', 'username' => 'bendahara1', 'password' => 'rahasia123']);
        $this->assertSame('bendahara', $user->panel);

        $this->post('/login', ['username' => 'bendahara1', 'password' => 'rahasia123'])->assertRedirect(route('bendahara/dashboard'));
        foreach (['/bendahara/dashboard', '/bendahara/keuangan/tagihan', '/bendahara/keuangan/pembayaran', '/bendahara/keuangan/biaya'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/bendahara/dashboard')->assertSee('Ringkasan')->assertSee('Jenis Biaya');

        // bukan area admin
        $this->get('/admin/dashboard')->assertRedirect();
        $this->get('/admin/keuangan')->assertRedirect();
    }

    public function test_bendahara_mencatat_pembayaran_dan_admin_juga_bisa(): void
    {
        $t = $this->tagihan(100000);
        $this->masukBendahara();

        $this->postJson("/bendahara/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 40000, 'metode' => 'tunai'])->assertOk()
            ->assertJsonPath('kuitansi', fn ($u) => str_contains($u, '/bendahara/keuangan/pembayaran/'));
        $this->assertSame('sebagian', $t->fresh()->status);
    }

    public function test_guru_dan_siswa_tidak_boleh_membuka_keuangan(): void
    {
        $t = $this->tagihan();
        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->postJson("/admin/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 1000, 'metode' => 'tunai'])->assertStatus(403);
        $this->postJson("/bendahara/keuangan/tagihan/{$t->id}/bayar", ['jumlah' => 1000, 'metode' => 'tunai'])->assertStatus(403);
        $this->assertSame(0, PembayaranSpp::count());
    }

    // ------------------------------------------------------------ modul per paket

    public function test_keuangan_hanya_untuk_paket_pro_dan_premium(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $bendahara = User::factory()->create(['role' => 'bendahara']);
        $this->paket('basic');

        $this->actingAs($admin)->get('/admin/keuangan')->assertRedirect();
        $this->get('/admin/dashboard')->assertDontSee(route('admin/keuangan'));
        $this->actingAs($bendahara)->get('/bendahara/dashboard')->assertNotFound();

        Langganan::where('sekolah_id', $this->sekolah->id)->update(['paket_id' => Paket::where('kode', 'pro')->value('id')]);
        AksesSekolah::lupakan();
        $this->actingAs($admin)->get('/admin/keuangan')->assertOk();
        $this->get('/admin/dashboard')->assertSee(route('admin/keuangan'));
        $this->actingAs($bendahara)->get('/bendahara/dashboard')->assertOk();
    }

    // ------------------------------------------------------------ orang tua

    private function ortuDengan(Siswa $anak): User
    {
        $user = User::factory()->create(['role' => 'orang_tua']);
        $ortu = OrangTua::create(['user_id' => $user->id, 'nama' => 'Bapak Andi']);
        $ortu->hubungkan($anak, 'ayah');

        return $user;
    }

    public function test_orang_tua_melihat_tagihan_dan_mengunduh_kuitansi_anaknya_saja(): void
    {
        $andi = $this->siswa('Andi');
        $budi = $this->siswa('Budi');
        $a = $this->tagihan(150000, $andi, '2026-10');
        $this->tagihan(100000, $andi, '2026-11');
        $batal = $this->tagihan(70000, $andi, '2026-09');
        Keuangan::batalkanTagihan($batal);
        $punyaBudi = $this->tagihan(90000, $budi, '2026-10');
        $bayarAndi = Keuangan::bayar($a, 150000);
        $bayarBudi = Keuangan::bayar($punyaBudi, 90000);
        $ortu = $this->ortuDengan($andi);

        $this->actingAs($ortu)->get('/ortu/tagihan')->assertOk()
            ->assertSee('Rp 100.000')                 // sisa yang harus dibayar (tagihan lunas dan batal tidak dihitung)
            ->assertSee('Rp 150.000')                 // sudah dibayar
            ->assertSee($bayarAndi->tanggal->translatedFormat('d M Y'))
            ->assertDontSee('SPP September 2026')->assertDontSee('Rp 90.000')->assertDontSee('Budi');

        $this->get('/ortu/kuitansi/'.$bayarAndi->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/ortu/kuitansi/'.$bayarBudi->id)->assertNotFound(); // kuitansi anak orang lain
    }

    public function test_ortu_di_paket_pro_membuka_tagihan_tetapi_bukan_nilai_dan_kehadiran(): void
    {
        $andi = $this->siswa('Andi');
        $this->tagihan(100000, $andi);
        $ortu = $this->ortuDengan($andi);
        $this->paket('pro');
        $this->actingAs($ortu);

        $this->get('/ortu/dashboard')->assertOk();
        $this->get('/ortu/tagihan')->assertOk()->assertSee('Tagihan & Pembayaran');
        $this->get('/ortu/nilai')->assertStatus(403);       // Portal Orang Tua (Premium)
        $this->get('/ortu/kehadiran')->assertStatus(403);
        $this->get('/ortu/dashboard')->assertSee(route('ortu/tagihan'))->assertDontSee(route('ortu/nilai'));

        // Premium: semuanya
        Langganan::where('sekolah_id', $this->sekolah->id)->update(['paket_id' => Paket::where('kode', 'premium')->value('id')]);
        AksesSekolah::lupakan();
        $this->get('/ortu/nilai')->assertOk();
    }

    public function test_ortu_di_paket_basic_tidak_punya_panel(): void
    {
        $andi = $this->siswa('Andi');
        $ortu = $this->ortuDengan($andi);
        $this->paket('basic');

        $this->actingAs($ortu)->get('/ortu/tagihan')->assertStatus(403);
        $this->get('/ortu/dashboard')->assertStatus(403);
    }

    public function test_pembayaran_tidak_bisa_diubah_langsung_menjadi_negatif_dan_hitung_ulang_konsisten(): void
    {
        $t = $this->tagihan(100000);
        $p1 = Keuangan::bayar($t, 30000);
        Keuangan::bayar($t->fresh(), 20000);
        $this->assertSame(50000, $t->fresh()->terbayar);

        Keuangan::batalkanPembayaran($p1, 'koreksi');
        $this->assertSame(20000, $t->fresh()->terbayar);
        $this->assertSame('sebagian', $t->fresh()->status);

        $this->expectException(ValidationException::class);
        Keuangan::bayar($t->fresh(), 80001); // sisa 80.000
    }
}
