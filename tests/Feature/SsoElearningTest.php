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
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Tahap 3 poin 2: login tunggal (OAuth2 authorization code), identitas pengguna, tombol "Buka E-Learning", dan pengaturannya. */
class SsoElearningTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK = 'https://elearning.test/callback';

    private array $kunci;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        if (! is_file(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys');
        }

        $this->kunci = Integrasi::siapkan($this->sekolah);
    }

    private function nyalakanSso(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/admin/integrasi/sso', ['elearning_url' => 'https://elearning.test', 'redirect_url' => self::CALLBACK, 'sso_aktif' => 1])->assertRedirect('/admin/integrasi');
        auth()->logout();
    }

    private function siswa(): User
    {
        $user = User::factory()->create(['role' => 'siswa', 'password' => 'rahasia123']);
        $kelas = Kelas::create(['nama_kelas' => 'VII A', 'tingkat' => 7]);
        Siswa::create(['user_id' => $user->id, 'nisn' => '9000000001', 'nama_siswa' => 'Andi', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $kelas->id]);

        return $user;
    }

    private function urlOtorisasi(string $tambah = ''): string
    {
        return '/oauth/authorize?'.http_build_query([
            'client_id' => $this->kunci['klien']->client_id, 'redirect_uri' => self::CALLBACK, 'response_type' => 'code', 'scope' => '', 'state' => 'abc',
        ]).$tambah;
    }

    private function kodeOtorisasi(): string
    {
        $res = $this->get($this->urlOtorisasi());
        $res->assertRedirect();
        $lokasi = $res->headers->get('Location');
        $this->assertStringStartsWith(self::CALLBACK.'?', $lokasi);
        parse_str(parse_url($lokasi, PHP_URL_QUERY), $q);
        $this->assertSame('abc', $q['state']);

        return $q['code'];
    }

    private function tukarKode(string $kode)
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => $this->kunci['klien']->client_id, 'client_secret' => $this->kunci['client_secret'],
            'redirect_uri' => self::CALLBACK, 'code' => $kode,
        ]);
    }

    public function test_alur_sso_lengkap_siswa_masuk_ke_elearning(): void
    {
        $this->nyalakanSso();
        $siswa = $this->siswa();
        $this->actingAs($siswa);

        $token = $this->tukarKode($this->kodeOtorisasi())->assertOk();
        $this->assertLessThanOrEqual(900, $token->json('expires_in')); // token akses singkat (15 menit)
        $this->assertNotEmpty($token->json('refresh_token'));

        $me = $this->withToken($token->json('access_token'))->getJson('/api/v1/me')->assertOk();
        $this->assertSame($siswa->id, $me->json('data.user_id'));
        $this->assertSame($this->sekolah->id, $me->json('data.sekolah_id'));
        $this->assertSame(['siswa'], $me->json('data.peran'));
        $this->assertSame('VII A', $me->json('data.rombel.0.nama'));
        $this->assertNotNull($me->json('data.siswa_id'));
        $this->assertStringNotContainsString('password', $me->getContent());

        // refresh token menerbitkan token baru
        $baru = $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token', 'refresh_token' => $token->json('refresh_token'),
            'client_id' => $this->kunci['klien']->client_id, 'client_secret' => $this->kunci['client_secret'],
        ])->assertOk();
        $this->assertNotSame($token->json('access_token'), $baru->json('access_token'));

        // token pengguna tidak bisa dipakai membaca API data induk (itu khusus token klien)
        $this->withToken($baru->json('access_token'))->getJson('/api/v1/siswa')->assertStatus(401);
    }

    public function test_kode_otorisasi_hanya_sekali_pakai_dan_rahasia_salah_ditolak(): void
    {
        $this->nyalakanSso();
        $this->actingAs($this->siswa());
        $kode = $this->kodeOtorisasi();

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code', 'client_id' => $this->kunci['klien']->client_id, 'client_secret' => 'salah',
            'redirect_uri' => self::CALLBACK, 'code' => $kode,
        ])->assertStatus(401);

        $this->tukarKode($kode)->assertOk();
        $this->tukarKode($kode)->assertStatus(400); // kode yang sama tidak bisa dipakai lagi
    }

    public function test_belum_masuk_diarahkan_ke_login_lalu_kembali_ke_otorisasi(): void
    {
        $this->nyalakanSso();
        $user = $this->siswa();

        $this->get($this->urlOtorisasi())->assertRedirect(route('login'));

        $res = $this->post('/login', ['username' => $user->username, 'password' => 'rahasia123']);
        $this->assertStringContainsString('/oauth/authorize?', $res->headers->get('Location'));
    }

    public function test_hanya_guru_dan_siswa_yang_boleh_masuk(): void
    {
        $this->nyalakanSso();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get($this->urlOtorisasi())->assertStatus(403);
    }

    public function test_sso_mati_atau_klien_sekolah_lain_ditolak(): void
    {
        $this->actingAs($this->siswa());

        // belum dinyalakan admin
        $this->get($this->urlOtorisasi())->assertStatus(403);

        // klien sekolah ini tidak bisa dipakai lewat alamat sekolah lain
        $this->nyalakanSso();
        Sekolah::create(['nama' => 'Sekolah Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        $this->actingAs($this->siswa())->get($this->urlOtorisasi().'&sekolah=lain')->assertStatus(403);
    }

    public function test_paket_tanpa_integrasi_menolak_sso(): void
    {
        $this->nyalakanSso();
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', 'pro')->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();

        $this->actingAs($this->siswa())->get($this->urlOtorisasi())->assertStatus(403);
        $this->assertNull(Integrasi::alamatElearning());
    }

    public function test_alamat_kembali_selain_yang_terdaftar_ditolak(): void
    {
        $this->nyalakanSso();
        $this->actingAs($this->siswa());

        $res = $this->get(str_replace(urlencode(self::CALLBACK), urlencode('https://penyerang.test/curi'), $this->urlOtorisasi()));
        $this->assertStringNotContainsString('code=', (string) $res->headers->get('Location'));
        $this->assertContains($res->getStatusCode(), [400, 401, 302]);
        $this->assertStringNotContainsString('penyerang.test', (string) $res->headers->get('Location'));
    }

    public function test_token_pengguna_ditolak_setelah_sso_dimatikan(): void
    {
        $this->nyalakanSso();
        $this->actingAs($this->siswa());
        $token = $this->tukarKode($this->kodeOtorisasi())->json('access_token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        SsoKlien::first()->update(['sso_aktif' => false]);
        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    // ------------------------------------------------------------ tombol dan pengaturan

    public function test_tombol_buka_elearning_tampil_hanya_bila_sso_menyala(): void
    {
        $siswa = $this->siswa();
        $this->actingAs($siswa)->get('/siswa/dashboard')->assertOk()->assertDontSee('Buka E-Learning');
        $this->get('/elearning')->assertNotFound();

        $this->nyalakanSso();
        $this->actingAs($siswa)->get('/siswa/dashboard')->assertOk()->assertSee('Buka E-Learning')->assertSee(route('elearning.buka'));
        $this->get('/elearning')->assertRedirect('https://elearning.test');

        $guru = User::factory()->create(['role' => 'guru']);
        $this->actingAs($guru)->get('/guru/dashboard')->assertOk()->assertSee('Buka E-Learning');
    }

    public function test_pengaturan_sso_divalidasi_dan_disinkronkan_ke_klien_oauth(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // menyalakan tombol tanpa alamat ditolak
        $this->put('/admin/integrasi/sso', ['sso_aktif' => 1])->assertSessionHasErrors('sso_aktif');
        // alamat internal ditolak
        $this->put('/admin/integrasi/sso', ['elearning_url' => 'https://10.0.0.1', 'redirect_url' => self::CALLBACK])->assertSessionHasErrors('elearning_url');

        $this->put('/admin/integrasi/sso', ['elearning_url' => 'https://elearning.test', 'redirect_url' => self::CALLBACK, 'sso_aktif' => 1])->assertRedirect('/admin/integrasi');
        $oauth = \Laravel\Passport\Passport::client()->newQuery()->find($this->kunci['klien']->client_id);
        $this->assertSame([self::CALLBACK], $oauth->redirect_uris);
    }

    public function test_uji_sambungan_menampilkan_hasil_per_bagian(): void
    {
        Http::fake(['elearning.test' => Http::response('ok', 200), 'elearning.test/*' => Http::response('', 200)]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/admin/integrasi/sso', ['elearning_url' => 'https://elearning.test', 'redirect_url' => self::CALLBACK]);
        $this->put('/admin/integrasi/webhook', ['webhook_url' => 'https://elearning.test/hook']);

        $this->post('/admin/integrasi/uji')->assertRedirect('/admin/integrasi');
        $hasil = collect(session('hasil_uji'))->keyBy(0);
        $this->assertTrue($hasil['Kunci akses'][1]);
        $this->assertTrue($hasil['Alamat E-Learning'][1]);
        $this->assertTrue($hasil['Penerima kabar'][1]);

        Http::assertSent(fn ($r) => ($r->header('X-Siakad-Event')[0] ?? '') === 'ping' && str_starts_with($r->header('X-Siakad-Signature')[0], 'sha256='));
        $this->get('/admin/integrasi')->assertOk()->assertSee('Penerima kabar');
    }
}
