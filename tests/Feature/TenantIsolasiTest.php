<?php

namespace Tests\Feature;

use App\Berita;
use App\ChatFaq;
use App\ChatSession;
use App\ChatSetting;
use App\Kategori;
use App\Kelas;
use App\Mail\ChatMail;
use App\Mapel;
use App\Pengaturan;
use App\Scopes\SekolahScope;
use App\Sekolah;
use App\Setting;
use App\Siswa;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Isolasi data antar sekolah (multi-tenant): sekolah A tidak boleh melihat atau mengubah data sekolah B. */
class TenantIsolasiTest extends TestCase
{
    use RefreshDatabase;

    private Sekolah $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->b = Sekolah::create(['nama' => 'SMA Beta', 'subdomain' => 'beta', 'jenjang' => 'SMA', 'status' => 'aktif', 'tanpa_batas' => true]);
    }

    /** Jalankan di konteks sekolah B. */
    private function diB(callable $fn): mixed
    {
        return SekolahAktif::jalankan($this->b, $fn);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'username' => 'admin']);
    }

    private function siswaDi(string $nisn, Kelas $kelas): Siswa
    {
        $user = User::factory()->create(['role' => 'siswa']);

        return Siswa::create(['user_id' => $user->id, 'nisn' => $nisn, 'nama_siswa' => 'S '.$nisn, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $kelas->id]);
    }

    private function dt(): string
    {
        return http_build_query(['draw' => 1, 'start' => 0, 'length' => 50, 'search' => ['value' => ''], 'columns' => [['data' => null]]]);
    }

    // ------------------------------------------------------------ model & scope

    public function test_data_baru_otomatis_memakai_sekolah_aktif_dan_difilter(): void
    {
        $a = Kelas::create(['nama_kelas' => 'X-A']);
        $b = $this->diB(fn () => Kelas::create(['nama_kelas' => 'X-B']));

        $this->assertSame($this->sekolah->id, $a->sekolah_id);
        $this->assertSame($this->b->id, $b->sekolah_id);
        $this->assertSame(['X-A'], Kelas::pluck('nama_kelas')->all());
        $this->assertSame(['X-B'], $this->diB(fn () => Kelas::pluck('nama_kelas')->all()));
        $this->assertNull(Kelas::find($b->id), 'find() lintas sekolah harus kosong');
        $this->assertSame(2, Kelas::withoutGlobalScope(SekolahScope::class)->count());
    }

    public function test_tanpa_konteks_sekolah_tidak_ada_filter_dan_sekolah_id_tidak_dipaksa(): void
    {
        Kelas::create(['nama_kelas' => 'X-A']);
        $this->diB(fn () => Kelas::create(['nama_kelas' => 'X-B']));

        SekolahAktif::instance()->set(null); // console / panel platform
        $this->assertSame(2, Kelas::count());
        $this->assertNull(Kelas::create(['nama_kelas' => 'Tanpa Sekolah'])->sekolah_id);
    }

    public function test_jalankan_memulihkan_konteks_dan_config_semula(): void
    {
        $this->diB(fn () => Setting::put(['nama_sekolah' => 'Nama Beta']));
        config(['school.name' => 'Asli']);

        $dalam = SekolahAktif::jalankan($this->b, fn () => [SekolahAktif::instance()->id(), config('school.name')]);

        $this->assertSame([$this->b->id, 'Nama Beta'], $dalam);
        $this->assertSame($this->sekolah->id, SekolahAktif::instance()->id());
        $this->assertSame('Asli', config('school.name'));
    }

    // ------------------------------------------------------------ HTTP: daftar dan akses langsung

    public function test_admin_sekolah_a_tidak_melihat_data_sekolah_b_di_daftar(): void
    {
        $ka = Kelas::create(['nama_kelas' => 'X-A']);
        $this->siswaDi('1111111111', $ka);
        $this->diB(function () {
            $kb = Kelas::create(['nama_kelas' => 'X-B']);
            $this->siswaDi('2222222222', $kb);
            Mapel::create(['mapel_nama' => 'Mapel Beta']);
        });

        $this->actingAs($this->admin());
        $siswa = $this->getJson('/admin/siswa/data?'.$this->dt())->assertOk();
        $this->assertSame(['1111111111'], collect($siswa->json('data'))->pluck('nisn')->all());
        $this->assertSame(['X-A'], collect($this->getJson('/admin/kelas/data?'.$this->dt())->json('data'))->pluck('nama_kelas')->all());
        $this->assertSame(0, $this->getJson('/admin/mapel/data?'.$this->dt())->json('recordsTotal'));
    }

    public function test_akses_langsung_ke_data_sekolah_lain_ditolak_404(): void
    {
        $idB = $this->diB(function () {
            $kb = Kelas::create(['nama_kelas' => 'X-B']);

            return ['kelas' => $kb->id, 'siswa' => $this->siswaDi('2222222222', $kb)->id, 'mapel' => Mapel::create(['mapel_nama' => 'Beta'])->id];
        });

        $this->actingAs($this->admin());
        $this->get("/admin/kelas/open/{$idB['kelas']}")->assertNotFound();
        $this->putJson("/admin/kelas/{$idB['kelas']}", ['nama_kelas' => 'Dibajak'])->assertNotFound();
        $this->deleteJson("/admin/siswa/{$idB['siswa']}")->assertNotFound();
        $this->putJson("/admin/mapel/{$idB['mapel']}", ['mapel_nama' => 'Dibajak'])->assertNotFound();
        $this->get("/admin/rapor/{$idB['siswa']}")->assertNotFound();

        $this->diB(function () use ($idB) {
            $this->assertSame('X-B', Kelas::findOrFail($idB['kelas'])->nama_kelas);
            $this->assertNotNull(Siswa::find($idB['siswa']));
        });
    }

    public function test_referensi_id_milik_sekolah_lain_ditolak_validasi(): void
    {
        $kb = $this->diB(fn () => Kelas::create(['nama_kelas' => 'X-B']));
        $mb = $this->diB(fn () => Mapel::create(['mapel_nama' => 'Beta']));
        $this->actingAs($this->admin());

        $this->postJson('/admin/jadwal', ['kelas_id' => $kb->id, 'mapel_id' => $mb->id, 'hari' => 1, 'jam_mulai' => '07:00', 'jam_selesai' => '08:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['kelas_id', 'mapel_id']);
        $this->postJson('/admin/absensi', ['kelas_id' => $kb->id, 'tanggal' => today()->toDateString(), 'items' => [['siswa_id' => 1, 'status' => 'hadir']]])
            ->assertStatus(422)->assertJsonValidationErrors('kelas_id');
        $this->postJson('/admin/siswa', ['nisn' => '3333333333', 'nama_siswa' => 'X', 'email' => 'x@a.test', 'kelas_id' => $kb->id, 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors('kelas_id');
    }

    // ------------------------------------------------------------ unik per sekolah

    public function test_nisn_username_dan_email_boleh_sama_di_sekolah_berbeda_tapi_tidak_dalam_satu_sekolah(): void
    {
        $this->diB(function () {
            $kb = Kelas::create(['nama_kelas' => 'X-B']);
            $s = $this->siswaDi('9999999999', $kb);
            $s->user->update(['username' => 'dewi', 'email' => 'dewi@contoh.test']);
        });
        $ka = Kelas::create(['nama_kelas' => 'X-A']);
        $payload = ['nisn' => '9999999999', 'nama_siswa' => 'Dewi', 'email' => 'dewi@contoh.test', 'username' => 'dewi', 'kelas_id' => $ka->id, 'agama' => 'Islam', 'jenis_kelamin' => 'Perempuan', 'tanggal_lahir' => '2010-01-01'];

        $this->actingAs($this->admin());
        $this->postJson('/admin/siswa', $payload)->assertCreated(); // NISN, username, email yang sama sudah ada di sekolah B
        $this->postJson('/admin/siswa', $payload)->assertStatus(422)->assertJsonValidationErrors(['nisn', 'username', 'email']); // dalam sekolah yang sama tidak boleh
    }

    public function test_nama_kelas_dan_slug_berita_unik_per_sekolah(): void
    {
        Kategori::create(['kategori_nama' => 'Umum']);
        $katB = $this->diB(fn () => Kategori::create(['kategori_nama' => 'Umum']));
        $this->actingAs($this->admin());

        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A'])->assertCreated();
        $this->diB(fn () => Kelas::create(['nama_kelas' => 'X-A']));
        $this->postJson('/admin/kelas', ['nama_kelas' => 'X-A'])->assertStatus(422);

        $a = Berita::create(['berita_judul' => 'Judul Sama', 'berita_isi' => 'x', 'kategori_id' => Kategori::first()->id, 'author' => 'a', 'berita_views' => 0]);
        $b = $this->diB(fn () => Berita::create(['berita_judul' => 'Judul Sama', 'berita_isi' => 'x', 'kategori_id' => $katB->id, 'author' => 'b', 'berita_views' => 0]));
        $this->assertSame('judul-sama', $a->berita_slug);
        $this->assertSame('judul-sama', $b->berita_slug, 'slug sama boleh di sekolah lain');
    }

    // ------------------------------------------------------------ pengaturan, profil, chat

    public function test_pengaturan_situs_dan_halaman_profil_terpisah_per_sekolah(): void
    {
        Setting::put(['nama_sekolah' => 'SMA Alfa', 'hero_judul' => 'Judul Alfa']);
        $this->diB(function () {
            Setting::put(['nama_sekolah' => 'SMA Beta', 'hero_judul' => 'Judul Beta']);
            Pengaturan::create(['slot' => 1, 'judul' => 'Tentang Beta', 'deskripsi' => '<p>Isi Beta</p>']);
        });
        Pengaturan::create(['slot' => 1, 'judul' => 'Tentang Alfa', 'deskripsi' => '<p>Isi Alfa</p>']);

        $this->get('/')->assertOk()->assertSee('Judul Alfa')->assertDontSee('Judul Beta');
        $this->get('/?sekolah=beta')->assertOk()->assertSee('Judul Beta')->assertDontSee('Judul Alfa');
        $this->get('/profil?sekolah=beta')->assertOk()->assertSee('Isi Beta')->assertDontSee('Isi Alfa');
        $this->get('/profil?sekolah=uji')->assertOk()->assertSee('Isi Alfa')->assertDontSee('Isi Beta');
    }

    public function test_pengaturan_chat_dan_faq_terpisah_per_sekolah(): void
    {
        ChatSetting::put('maks_chat', '2');
        $this->diB(function () {
            ChatSetting::put('maks_chat', '9'); // kunci yang sama di sekolah lain tidak bentrok
            ChatFaq::create(['pertanyaan' => 'Khusus Beta', 'kata_kunci' => 'beta', 'jawaban' => 'x', 'urutan' => 0, 'aktif' => true]);
        });

        $this->assertSame('2', ChatSetting::get('maks_chat'));
        $this->assertSame('9', $this->diB(fn () => ChatSetting::get('maks_chat')));
        $this->assertSame(5, ChatFaq::count());
        $this->assertSame(1, $this->diB(fn () => ChatFaq::count()));
    }

    public function test_sesi_chat_dan_lampiran_sekolah_lain_tidak_bisa_diakses(): void
    {
        $token = $this->diB(fn () => ChatSession::create(['token' => ChatSession::newToken(), 'nama' => 'Tamu B', 'email' => 'b@c.test', 'topik' => 'Lainnya', 'status' => 'menunggu', 'persetujuan' => true])->token);

        $this->getJson("/chat/poll?token=$token")->assertNotFound(); // alamat sekolah A
        $this->getJson("/chat/poll?token=$token&sekolah=beta")->assertOk();
        $this->flushSession(); // ?sekolah=beta diingat di sesi; kembali ke alamat sekolah A
        $this->postJson('/chat/send', ['token' => $token, 'body' => 'halo'])->assertNotFound();

        $this->actingAs($this->admin());
        $this->assertSame(0, $this->getJson('/admin/chat/queue')->assertOk()->json('waiting.0') === null ? 0 : 1);
        $idSesi = $this->diB(fn () => ChatSession::first()->id);
        $this->getJson("/admin/chat/{$idSesi}/poll")->assertNotFound();
        $this->post("/admin/chat/{$idSesi}/take")->assertNotFound();
    }

    public function test_email_chat_memakai_identitas_sekolah_pemilik_chat_walau_dirender_di_konteks_lain(): void
    {
        $this->diB(fn () => Setting::put(['nama_sekolah' => 'SMA Beta Mandiri']));
        $sesi = $this->diB(fn () => ChatSession::create(['token' => ChatSession::newToken(), 'nama' => 'Tamu B', 'email' => 'b@c.test', 'topik' => 'Lainnya', 'status' => 'menunggu', 'persetujuan' => true]));

        SekolahAktif::instance()->set(null); // seperti queue worker: tanpa konteks request
        config(['school.name' => 'Nama Bawaan .env']);

        $html = (new ChatMail($sesi, 'dimulai'))->render();

        $this->assertStringContainsString('SMA Beta Mandiri', $html);
        $this->assertStringNotContainsString('Nama Bawaan .env', $html);
        $this->assertSame('Nama Bawaan .env', config('school.name'), 'config dipulihkan setelah render');
    }

    // ------------------------------------------------------------ login per sekolah

    public function test_login_hanya_berlaku_di_sekolah_pemilik_akun(): void
    {
        $this->diB(fn () => User::factory()->create(['role' => 'admin', 'username' => 'kepsek.b', 'password' => Hash::make('rahasia')]));

        $this->post('/login', ['username' => 'kepsek.b', 'password' => 'rahasia'])->assertSessionHasErrors('username'); // alamat sekolah A
        $this->assertGuest();

        $this->post('/login?sekolah=beta', ['username' => 'kepsek.b', 'password' => 'rahasia'])->assertRedirect(route('admin/dashboard'));
        $this->assertAuthenticated();
    }

    // ------------------------------------------------------------ pengenalan sekolah

    public function test_sekolah_dikenali_dari_subdomain_domain_kustom_dan_bawaan(): void
    {
        config(['sekolah.domain_utama' => 'siakad.test']);
        $this->b->update(['domain_kustom' => 'www.sekolahbeta.sch.id']);
        Setting::put(['nama_sekolah' => 'SMA Alfa']);
        $this->diB(fn () => Setting::put(['nama_sekolah' => 'SMA Beta']));

        $this->get('http://beta.siakad.test/')->assertOk()->assertSee('SMA Beta');
        $this->get('http://uji.siakad.test/')->assertOk()->assertSee('SMA Alfa');
        $this->b->update(['domain_kustom' => 'sekolahbeta.sch.id']);
        $this->b->forceFill(['domain_terverifikasi_pada' => now()])->save(); // domain kustom hanya dipakai bila sudah terverifikasi (lihat DomainKustomTest)
        $this->get('http://www.sekolahbeta.sch.id/')->assertOk()->assertSee('SMA Beta');
        $this->get('http://localhost/')->assertOk()->assertSee('SMA Alfa'); // bawaan
    }

    public function test_subdomain_tak_dikenal_404_dan_sekolah_nonaktif_403(): void
    {
        config(['sekolah.domain_utama' => 'siakad.test']);

        $this->get('http://tidakada.siakad.test/')->assertNotFound()->assertSee('Sekolah tidak ditemukan');
        $this->get('http://a.b.siakad.test/')->assertNotFound(); // bukan subdomain tunggal

        $this->b->update(['status' => 'nonaktif']);
        $this->get('http://beta.siakad.test/')->assertForbidden()->assertSee('Situs tidak aktif');
    }

    public function test_override_query_diingat_di_sesi_dan_dimatikan_di_produksi(): void
    {
        Setting::put(['nama_sekolah' => 'SMA Alfa']);
        $this->diB(fn () => Setting::put(['nama_sekolah' => 'SMA Beta']));

        $this->get('/?sekolah=beta')->assertSee('SMA Beta');
        $this->get('/berita')->assertOk()->assertSee('SMA Beta'); // tanpa ?sekolah= lagi: tetap sekolah B dari sesi

        config(['sekolah.izinkan_query' => false]);
        $this->flushSession();
        $this->get('/?sekolah=beta')->assertSee('SMA Alfa')->assertDontSee('SMA Beta');
    }

    public function test_kode_sekolah_override_yang_tidak_ada_menghasilkan_404(): void
    {
        $this->get('/?sekolah=tidakada')->assertNotFound()->assertSee('Sekolah tidak ditemukan');
    }

    public function test_alamat_platform_tidak_terikat_sekolah(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->get('/platform/probe', fn () => response()->json(['sekolah' => SekolahAktif::instance()->id()]));
        \Illuminate\Support\Facades\Route::middleware('web')->get('/probe-sekolah', fn () => response()->json(['sekolah' => SekolahAktif::instance()->id()]));

        $this->getJson('/probe-sekolah')->assertJsonPath('sekolah', $this->sekolah->id);
        $this->getJson('/platform/probe')->assertJsonPath('sekolah', null);
    }

    public function test_tanpa_sekolah_bawaan_dan_banyak_sekolah_alamat_asing_404(): void
    {
        config(['sekolah.default_subdomain' => null]);

        $this->get('/')->assertNotFound(); // ada 2 sekolah, tidak ada yang bisa dipilih

        $this->b->delete();
        $this->get('/')->assertOk(); // hanya satu sekolah: dipakai
    }
}
