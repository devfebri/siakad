<?php

namespace Tests\Feature;

use App\Kelas;
use App\Langganan;
use App\Paket;
use App\Sekolah;
use App\Siswa;
use App\SsoKlien;
use App\Support\AksesSekolah;
use App\Support\Integrasi;
use App\Support\SekolahAktif;
use App\Support\UrlAman;
use App\User;
use App\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Tahap 3 poin 1: API data induk v1 (client credentials), isolasi per sekolah, webhook bertanda tangan, dan menu Integrasi. */
class ApiElearningTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $lain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (! is_file(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys');
        }

        $this->lain = Sekolah::create(['nama' => 'Sekolah Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
    }

    private function siswa(Sekolah $sekolah, string $nama, string $nisn): Siswa
    {
        return SekolahAktif::jalankan($sekolah, function () use ($nama, $nisn) {
            $user = User::factory()->create(['role' => 'siswa', 'password' => 'rahasia-banget']);

            return Siswa::create(['user_id' => $user->id, 'nisn' => $nisn, 'nama_siswa' => $nama, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01']);
        });
    }

    /** @return array{0: array, 1: string} [kunci, token] */
    private function masukSebagaiKlien(?Sekolah $sekolah = null): array
    {
        $kunci = Integrasi::siapkan($sekolah ?? $this->sekolah);
        $res = $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials', 'client_id' => $kunci['klien']->client_id, 'client_secret' => $kunci['client_secret'],
        ])->assertOk();

        return [$kunci, $res->json('access_token')];
    }

    private function api(string $url, string $token)
    {
        return $this->withToken($token)->getJson($url);
    }

    public function test_api_menolak_tanpa_token_dan_token_palsu(): void
    {
        $this->getJson('/api/v1/siswa')->assertStatus(401);
        $this->withToken('bukan-token')->getJson('/api/v1/siswa')->assertStatus(401);
    }

    public function test_klien_hanya_melihat_data_sekolahnya_dan_tanpa_data_login(): void
    {
        $this->siswa($this->sekolah, 'Andi Sekolah Uji', '1000000001');
        $this->siswa($this->lain, 'Budi Sekolah Lain', '2000000002');
        [, $token] = $this->masukSebagaiKlien();

        $res = $this->api('/api/v1/siswa', $token)->assertOk();
        $this->assertSame(['Andi Sekolah Uji'], collect($res->json('data'))->pluck('nama')->all());
        $this->assertStringNotContainsString('Budi Sekolah Lain', $res->getContent());
        foreach (['password', 'cek_password', 'remember_token'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $res->getContent());
        }
        $this->assertNotEmpty($res->json('data.0.username'));

        // klien sekolah lain hanya melihat datanya sendiri
        [, $tokenLain] = $this->masukSebagaiKlien($this->lain);
        $this->assertSame(['Budi Sekolah Lain'], collect($this->api('/api/v1/siswa', $tokenLain)->json('data'))->pluck('nama')->all());
        $this->assertSame('lain', $this->api('/api/v1/sekolah', $tokenLain)->json('data.subdomain'));
    }

    public function test_paginasi_filter_dan_updated_since(): void
    {
        foreach (range(1, 5) as $i) {
            $this->siswa($this->sekolah, "Siswa {$i}", "300000000{$i}");
        }
        Siswa::where('nisn', '3000000001')->update(['updated_at' => now()->subDays(10)]);
        [, $token] = $this->masukSebagaiKlien();

        $hal = $this->api('/api/v1/siswa?per_page=2', $token)->assertOk();
        $this->assertCount(2, $hal->json('data'));
        $this->assertSame(5, $hal->json('meta.total'));
        $this->assertNotNull($hal->json('links.next'));

        $baru = $this->api('/api/v1/siswa?updated_since='.urlencode(now()->subDay()->toIso8601String()), $token)->assertOk();
        $this->assertSame(4, $baru->json('meta.total'));

        $this->api('/api/v1/siswa?per_page=500', $token)->assertStatus(422);
        $this->api('/api/v1/siswa?updated_since=bukan-tanggal', $token)->assertStatus(422);
        $this->api('/api/v1/tidak-ada', $token)->assertNotFound();
    }

    public function test_semua_sumber_dapat_dibuka_dan_tahun_ajaran_aktif(): void
    {
        [, $token] = $this->masukSebagaiKlien();

        foreach (['tahun-ajaran', 'rombel', 'rombel-siswa', 'mapel', 'guru', 'siswa', 'jadwal'] as $sumber) {
            $this->api("/api/v1/{$sumber}", $token)->assertOk()->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'last_page'], 'links']);
        }

        $aktif = $this->api('/api/v1/tahun-ajaran/aktif', $token)->assertOk();
        $this->assertTrue($aktif->json('data.semester.aktif'));
        $this->assertNotEmpty($aktif->json('data.tahun_ajaran.semester'));
    }

    public function test_klien_nonaktif_dan_paket_tanpa_integrasi_ditolak(): void
    {
        [$kunci, $token] = $this->masukSebagaiKlien();
        $this->api('/api/v1/sekolah', $token)->assertOk();

        // paket Basic tidak mencakup integrasi
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'basic')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        $this->api('/api/v1/sekolah', $token)->assertStatus(403);

        // Premium mencakup
        Langganan::where('sekolah_id', $this->sekolah->id)->update(['paket_id' => Paket::where('kode', 'premium')->value('id')]);
        $this->api('/api/v1/sekolah', $token)->assertOk();

        // dinonaktifkan oleh admin sekolah
        Integrasi::nonaktifkan($kunci['klien']->fresh());
        $this->api('/api/v1/sekolah', $token)->assertStatus(401); // token dicabut
    }

    public function test_token_pengguna_biasa_tidak_bisa_membuka_api_data_induk(): void
    {
        [$kunci] = $this->masukSebagaiKlien();
        $user = User::factory()->create(['role' => 'admin']);
        \Laravel\Passport\Passport::actingAs($user);

        $this->getJson('/api/v1/siswa')->assertStatus(401);
    }

    public function test_pembatasan_laju_per_klien(): void
    {
        [$kunci, $token] = $this->masukSebagaiKlien();
        $kunciLaju = 'api-klien:'.$kunci['klien']->id;
        RateLimiter::clear($kunciLaju);
        for ($i = 0; $i < 120; $i++) {
            RateLimiter::hit($kunciLaju, 60);
        }

        $this->api('/api/v1/sekolah', $token)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_jumlah_request_dicatat_untuk_monitoring(): void
    {
        [, $token] = $this->masukSebagaiKlien();
        $this->api('/api/v1/sekolah', $token);
        $this->api('/api/v1/mapel', $token);

        $this->assertSame(2, (int) \DB::table('api_request_log')->where('sekolah_id', $this->sekolah->id)->value('jumlah'));
        $this->assertNotNull(SsoKlien::first()->terakhir_dipakai);
    }

    // ------------------------------------------------------------ webhook

    public function test_perubahan_siswa_mengirim_webhook_bertanda_tangan_hmac(): void
    {
        Http::fake(['elearning.test/*' => Http::response(['ok' => true], 200)]);
        $kunci = Integrasi::siapkan($this->sekolah);
        $kunci['klien']->update(['webhook_url' => 'https://elearning.test/hook']);

        $siswa = $this->siswa($this->sekolah, 'Siswa Webhook', '4000000001');

        Http::assertSent(function (HttpRequest $r) use ($kunci) {
            $isi = $r->body();
            $harapan = 'sha256='.hash_hmac('sha256', $r->header('X-Siakad-Timestamp')[0].'.'.$isi, $kunci['webhook_secret']);

            return $r->url() === 'https://elearning.test/hook'
                && $r->header('X-Siakad-Signature')[0] === $harapan
                && $r->header('X-Siakad-Event')[0] === 'siswa.created'
                && json_decode($isi, true)['data']['nama'] === 'Siswa Webhook'
                && ! str_contains($isi, 'password');
        });
        $this->assertSame('berhasil', WebhookLog::where('event', 'siswa.created')->value('status'));

        $siswa = $siswa->fresh();
        $siswa->update(['nama_siswa' => 'Siswa Diubah']);
        $this->assertTrue(WebhookLog::where('event', 'siswa.updated')->exists());

        $siswa->delete();
        Http::assertSent(fn (HttpRequest $r) => ($r->header('X-Siakad-Event')[0] ?? '') === 'siswa.deleted' && json_decode($r->body(), true)['data'] === ['id' => $siswa->id]);
    }

    public function test_webhook_gagal_dicatat_dan_dijadwalkan_ulang(): void
    {
        Http::fake(['elearning.test/*' => Http::response('error', 500)]);
        $kunci = Integrasi::siapkan($this->sekolah);
        $kunci['klien']->update(['webhook_url' => 'https://elearning.test/hook']);

        $this->siswa($this->sekolah, 'Siswa Gagal', '4000000002');

        $log = WebhookLog::where('event', 'siswa.created')->first();
        $this->assertSame('gagal', $log->status);
        $this->assertSame(500, $log->http_status);
    }

    public function test_tanpa_integrasi_aktif_tidak_ada_webhook(): void
    {
        Http::fake();
        $this->siswa($this->sekolah, 'Siswa Biasa', '4000000003');

        Http::assertNothingSent();
        $this->assertSame(0, WebhookLog::count());
    }

    public function test_webhook_sekolah_lain_tidak_bocor(): void
    {
        Http::fake(['elearning.test/*' => Http::response('', 200)]);
        $kunci = Integrasi::siapkan($this->sekolah);
        $kunci['klien']->update(['webhook_url' => 'https://elearning.test/hook']);

        $this->siswa($this->lain, 'Siswa Sekolah Lain', '5000000001');

        Http::assertNothingSent();
    }

    public function test_url_berbahaya_ditolak(): void
    {
        $this->assertNotNull(UrlAman::galat('https://10.0.0.5/hook'));
        $this->assertNotNull(UrlAman::galat('https://169.254.169.254/latest/meta-data'));
        $this->assertNotNull(UrlAman::galat('ftp://contoh.sch.id/x'));
        $this->assertNotNull(UrlAman::galat('https://user:pass@contoh.sch.id/x'));
        $this->assertNull(UrlAman::galat('https://elearning.contoh.sch.id/webhook'));
    }

    // ------------------------------------------------------------ menu admin

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_mengaktifkan_integrasi_dan_kunci_tampil_sekali(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/integrasi')->assertOk()->assertSee('Nyalakan Sambungan');

        $this->post('/admin/integrasi/aktifkan')->assertRedirect('/admin/integrasi');
        $klien = SsoKlien::first();
        $this->assertTrue($klien->aktif);

        $pertama = $this->get('/admin/integrasi')->assertOk()->assertSee('Simpan kunci ini sekarang')->assertSee($klien->client_id);
        $this->assertSame(1, preg_match('/Kunci Rahasia<\/dt>\s*<dd[^>]*>\s*<code[^>]*>([A-Za-z0-9]{48})</', $pertama->getContent(), $m));

        // kunci lengkap tidak tampil lagi, dan rahasia klien di database tersimpan sebagai hash
        $this->get('/admin/integrasi')->assertOk()->assertDontSee('Simpan kunci ini sekarang')->assertDontSee($m[1]);
        $this->assertNotSame($m[1], \DB::table('oauth_clients')->where('id', $klien->client_id)->value('secret'));
        $this->assertNotSame($klien->getRawOriginal('webhook_secret'), $klien->webhook_secret);
    }

    public function test_admin_menyimpan_alamat_webhook_dan_menolak_alamat_internal(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/integrasi/aktifkan');

        $this->put('/admin/integrasi/webhook', ['webhook_url' => 'https://10.0.0.5/hook'])->assertSessionHasErrors('webhook_url');
        $this->put('/admin/integrasi/webhook', ['webhook_url' => 'https://elearning.contoh.sch.id/hook'])->assertRedirect('/admin/integrasi');
        $this->assertSame('https://elearning.contoh.sch.id/hook', SsoKlien::first()->webhook_url);
    }

    public function test_admin_mematikan_integrasi_mencabut_akses(): void
    {
        $admin = $this->admin();
        [, $token] = $this->masukSebagaiKlien();
        $this->api('/api/v1/sekolah', $token)->assertOk();

        $this->actingAs($admin); // guard direset oleh request API
        $this->post('/admin/integrasi/nonaktifkan')->assertRedirect('/admin/integrasi');
        $this->api('/api/v1/sekolah', $token)->assertStatus(401); // token dicabut
    }

    public function test_menu_integrasi_hanya_untuk_paket_premium(): void
    {
        $this->sekolah->update(['tanpa_batas' => false]);
        $admin = $this->admin();
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'pro')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();
        $this->actingAs($admin);

        $this->get('/admin/integrasi')->assertRedirect();
        $this->get('/admin/dashboard')->assertDontSee(route('admin/integrasi'));

        Langganan::where('sekolah_id', $this->sekolah->id)->update(['paket_id' => Paket::where('kode', 'premium')->value('id')]);
        AksesSekolah::lupakan();
        $this->get('/admin/integrasi')->assertOk();
        $this->get('/admin/dashboard')->assertSee(route('admin/integrasi'));
    }
}
