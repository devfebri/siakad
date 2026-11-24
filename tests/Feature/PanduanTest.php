<?php

namespace Tests\Feature;

use App\Langganan;
use App\Modul;
use App\Paket;
use App\Peran;
use App\Support\AksesSekolah;
use App\Support\Panduan;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Menu Panduan: isi per peran, saringan menurut paket, pencarian, dan keutuhan isi (tautan, modul, kelengkapan). */
class PanduanTest extends TestCase
{
    use RefreshDatabase;

    private const PANEL = ['admin', 'guru', 'siswa', 'ortu', 'bendahara', 'kepsek', 'platform'];

    /** peran akun untuk tiap panel */
    private const PERAN = ['admin' => 'admin', 'guru' => 'guru', 'siswa' => 'siswa', 'ortu' => 'orang_tua', 'bendahara' => 'bendahara', 'kepsek' => 'kepsek'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function masuk(string $panel): User
    {
        $user = $panel === 'platform'
            ? SekolahAktif::jalankan(null, function () {
                $u = User::factory()->create(['username' => 'super', 'sekolah_id' => null]);
                $u->setPeran([Peran::SUPER_ADMIN]);

                return $u;
            })
            : User::factory()->create(['role' => self::PERAN[$panel]]);

        return tap($user, fn ($u) => $this->actingAs($u));
    }

    private function paket(string $kode): void
    {
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', $kode)->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();
    }

    // ------------------------------------------------------------ keutuhan isi

    public function test_isi_panduan_lengkap_dan_konsisten(): void
    {
        $kategori = config('panduan.kategori');
        $modulAda = Modul::pluck('kode')->all();
        $slug = [];

        foreach (config('panduan.artikel') as $a) {
            foreach (['slug', 'judul', 'kategori', 'ikon', 'panel', 'ringkas', 'langkah'] as $kunci) {
                $this->assertNotEmpty($a[$kunci] ?? null, "artikel {$a['slug']}: kolom {$kunci} kosong");
            }
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $a['slug']);
            $this->assertArrayNotHasKey($a['slug'], $slug, "slug kembar: {$a['slug']}");
            $slug[$a['slug']] = true;
            $this->assertArrayHasKey($a['kategori'], $kategori, "kategori tidak terdaftar: {$a['kategori']}");

            foreach ($a['panel'] as $p) {
                $this->assertContains($p, [...self::PANEL, 'semua'], "{$a['slug']}: panel {$p} tidak dikenal");
            }
            foreach ((array) ($a['modul'] ?? []) as $m) {
                $this->assertContains($m, $modulAda, "{$a['slug']}: modul {$m} tidak ada");
            }
            $this->assertNotEmpty(array_filter($a['langkah'], 'is_string'), "{$a['slug']}: langkah kosong");
            foreach ($a['langkah'] as $l) {
                $this->assertSame(0, substr_count($l, '**') % 2, "{$a['slug']}: tanda ** tidak berpasangan: {$l}");
            }

            // tautan halaman: rute harus ada untuk setiap panel yang melihat artikel ini
            if (! empty($a['tautan'])) {
                $this->assertCount(2, $a['tautan']);
                foreach (array_diff($a['panel'], ['semua']) as $p) {
                    $rute = str_replace('{panel}', $p, $a['tautan'][0]);
                    $this->assertTrue(Route::has($rute), "{$a['slug']}: rute {$rute} tidak ada");
                }
            }
        }

        $this->assertGreaterThanOrEqual(40, count($slug));
    }

    public function test_setiap_peran_punya_panduan_dan_kotak_mulai(): void
    {
        foreach (self::PANEL as $panel) {
            $daftar = Panduan::untukPanel($panel);
            $this->assertGreaterThanOrEqual(2, $daftar->count(), "panel {$panel} hampir tanpa panduan");
            $this->assertTrue($daftar->contains('mulai', true), "panel {$panel} tidak punya artikel Mulai dari sini");
        }
    }

    public function test_format_menebalkan_dan_mengamankan_teks(): void
    {
        $this->assertSame('Tekan <strong>Simpan</strong> sekarang', Panduan::format('Tekan **Simpan** sekarang'));
        $this->assertSame('<code class="rounded bg-slate-100 px-1.5 py-0.5 text-[0.85em]">{nama}</code>', Panduan::format('`{nama}`'));
        $aman = Panduan::format('**Tebal** <script>alert(1)</script> & "kutip"');
        $this->assertStringNotContainsString('<script>', $aman);
        $this->assertStringContainsString('&lt;script&gt;', $aman);
        $this->assertStringContainsString('<strong>Tebal</strong>', $aman);
    }

    // ------------------------------------------------------------ halaman per peran

    public function test_tiap_peran_membuka_panduan_miliknya_saja(): void
    {
        $harus = [
            'admin' => ['Menyiapkan sekolah untuk pertama kali', 'Memasukkan data guru, siswa, dan orang tua dari Excel', 'Mengelola PPDB dan menjadikan pendaftar sebagai siswa'],
            'guru' => ['Mencatat absensi siswa', 'Memasukkan nilai dan membuka rapor'],
            'siswa' => ['Melihat jadwal pelajaran dan kelas saya', 'Melihat nilai dan kehadiran saya'],
            'ortu' => ['Memilih anak dan membaca dashboard', 'Melihat dan membayar tagihan sekolah'],
            'bendahara' => ['Mengatur jenis biaya', 'Membuat tagihan dan mencatat pembayaran'],
            'kepsek' => ['Membaca dashboard kepala sekolah'],
            'platform' => ['Menambah sekolah baru', 'Mengatur paket, harga, dan invoice'],
        ];

        foreach ($harus as $panel => $judul) {
            $this->masuk($panel);
            $res = $this->get('/panduan')->assertOk()->assertSee('Panduan')->assertSee('Mulai dari sini')->assertSee('Tidak bisa masuk atau lupa password');
            foreach ($judul as $j) {
                $res->assertSee($j);
            }
            // artikel milik peran lain tidak ikut tampil
            foreach ($harus as $lain => $judulLain) {
                if ($lain !== $panel) {
                    foreach (array_diff($judulLain, $judul) as $j) {
                        $res->assertDontSee($j);
                    }
                }
            }
            auth()->logout();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/panduan')->assertRedirect(route('login'));
        $this->get('/panduan/ganti-password')->assertRedirect(route('login'));
    }

    public function test_menu_panduan_ada_di_semua_panel(): void
    {
        foreach (['admin' => '/admin/dashboard', 'guru' => '/guru/dashboard', 'siswa' => '/siswa/dashboard', 'ortu' => '/ortu/dashboard', 'bendahara' => '/bendahara/dashboard', 'kepsek' => '/kepsek/dashboard', 'platform' => '/platform/dashboard'] as $panel => $dashboard) {
            $this->masuk($panel);
            $this->get($dashboard)->assertOk()->assertSee(route('panduan'))->assertSee('Panduan');
            auth()->logout();
        }
    }

    // ------------------------------------------------------------ artikel

    public function test_artikel_menampilkan_langkah_bertingkat_catatan_dan_tombol(): void
    {
        $this->masuk('guru');

        $res = $this->get('/panduan/guru-absensi')->assertOk();
        $res->assertSee('Mencatat absensi siswa')->assertSee('<strong>Rekap Bulanan</strong>', false)->assertSee('Perlu diketahui')->assertSee('Cetak panduan ini')->assertSee('Semua panduan');
        $res->assertSee(route('guru/absensi'));                       // tombol membuka halaman yang dibahas
        $res->assertSee('Panduan lain di');                           // artikel serupa di kategori yang sama
        $this->assertSame(4, substr_count($res->getContent(), 'rounded-full bg-primary'), 'empat langkah bernomor');
    }

    public function test_tautan_profil_mengikuti_panel_aktif_dan_artikel_tanpa_tautan_tidak_punya_tombol(): void
    {
        $this->masuk('siswa');
        $this->get('/panduan/ganti-password')->assertOk()->assertSee(route('siswa/profile'));
        auth()->logout();

        $this->masuk('guru');
        $this->get('/panduan/ganti-password')->assertOk()->assertSee(route('guru/profile'))->assertDontSee(route('siswa/profile'));

        $this->get('/panduan/tidak-bisa-masuk')->assertOk()->assertSee('username')->assertDontSee('Buka Profil Saya')->assertSee('Cetak panduan ini');
    }

    public function test_artikel_peran_lain_atau_tidak_ada_menghasilkan_404(): void
    {
        $this->masuk('guru');

        $this->get('/panduan/keuangan-tagihan')->assertNotFound();      // artikel admin
        $this->get('/panduan/platform-sekolah-baru')->assertNotFound(); // artikel Super Admin
        $this->get('/panduan/tidak-ada-artikel-ini')->assertNotFound();
        $this->get('/panduan/Huruf-Besar!')->assertNotFound();
    }

    // ------------------------------------------------------------ paket

    public function test_artikel_fitur_berbayar_mengikuti_paket_sekolah(): void
    {
        $this->paket('basic');
        $this->masuk('admin');
        $judulBerbayar = ['Membuat tagihan SPP dan mencatat pembayaran', 'Mengelola PPDB dan menjadikan pendaftar sebagai siswa', 'Mengirim pemberitahuan lewat WhatsApp', 'Menghubungkan aplikasi E-Learning', 'Mengaktifkan pembayaran SPP online untuk orang tua', 'Membuat akun orang tua dan menghubungkannya ke anak'];

        $res = $this->get('/panduan')->assertOk()->assertSee('Mengatur tahun ajaran, semester, dan KKM')->assertSee('Melihat dan mencatat absensi')->assertSee('Menu yang saya cari tidak ada');
        foreach ($judulBerbayar as $j) {
            $res->assertDontSee($j);
        }
        $this->get('/panduan/keuangan-tagihan')->assertNotFound();
        $this->get('/panduan/ppdb-penerimaan')->assertNotFound();

        // Pro: keuangan, PPDB, WhatsApp, orang tua; E-Learning masih tidak
        Langganan::query()->update(['paket_id' => Paket::where('kode', 'pro')->value('id')]);
        AksesSekolah::lupakan();
        $res = $this->get('/panduan')->assertOk();
        foreach (array_slice($judulBerbayar, 0, 3) as $j) {
            $res->assertSee($j);
        }
        $res->assertSee('Membuat akun orang tua dan menghubungkannya ke anak')->assertSee('Mengaktifkan pembayaran SPP online untuk orang tua')->assertDontSee('Menghubungkan aplikasi E-Learning');

        // Premium: semuanya
        Langganan::query()->update(['paket_id' => Paket::where('kode', 'premium')->value('id')]);
        AksesSekolah::lupakan();
        $this->get('/panduan')->assertSee('Menghubungkan aplikasi E-Learning');
        $this->get('/panduan/integrasi-elearning')->assertOk();
    }

    public function test_panduan_orang_tua_menyesuaikan_pro_dan_premium(): void
    {
        $this->paket('pro');
        $this->masuk('ortu');

        $this->get('/panduan')->assertOk()->assertSee('Melihat dan membayar tagihan sekolah')->assertDontSee('Melihat kehadiran dan nilai anak');
        $this->get('/panduan/ortu-kehadiran-nilai')->assertNotFound();

        Langganan::query()->update(['paket_id' => Paket::where('kode', 'premium')->value('id')]);
        AksesSekolah::lupakan();
        $this->get('/panduan')->assertSee('Melihat kehadiran dan nilai anak');
    }

    public function test_panduan_platform_tidak_terpengaruh_paket_sekolah(): void
    {
        $this->paket('basic');
        $this->masuk('platform');

        $this->get('/panduan')->assertOk()->assertSee('Menambah sekolah baru')->assertSee('Memantau sekolah, backup, dan log aktivitas');
    }

    // ------------------------------------------------------------ pencarian

    public function test_pencarian_mencari_judul_ringkasan_dan_langkah(): void
    {
        $this->masuk('admin');

        $this->get('/panduan?q=tagihan')->assertOk()->assertSee('Membuat tagihan SPP dan mencatat pembayaran')->assertSee('panduan ditemukan')->assertDontSee('Mulai dari sini');
        $this->get('/panduan?q=TAGIHAN')->assertOk()->assertSee('Membuat tagihan SPP dan mencatat pembayaran');           // tidak peka huruf besar-kecil
        $this->get('/panduan?q=kuitansi')->assertOk()->assertSee('Membuat tagihan SPP dan mencatat pembayaran');            // kata ada di dalam langkah
        $this->get('/panduan?q=excel')->assertOk()->assertSee('Memasukkan data guru, siswa, dan orang tua dari Excel');
        $this->get('/panduan?q=zzzzzz')->assertOk()->assertSee('Tidak ada panduan yang cocok')->assertDontSee('Menyiapkan sekolah untuk pertama kali');
    }

    public function test_pencarian_tidak_membocorkan_artikel_peran_lain(): void
    {
        $this->masuk('guru');

        $this->get('/panduan?q=invoice')->assertOk()->assertSee('Tidak ada panduan yang cocok')->assertDontSee('Mengatur paket, harga, dan invoice');
        $this->get('/panduan?q='.urlencode('<script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);   // kata kunci di-escape
    }
}
