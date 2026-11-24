<?php

namespace Tests\Feature;

use App\Guru;
use App\Kelas;
use App\Mapel;
use App\Sekolah;
use App\Semester;
use App\Setting;
use App\Siswa;
use App\Support\Impor;
use App\Support\SekolahAktif;
use App\TahunAjaran;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Impor Excel guru/siswa dan wizard pengaturan awal. */
class ImporSetupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Storage::fake('local');
    }

    /** @param array<int, array<int, mixed>> $baris baris pertama = kepala kolom */
    private function xlsx(array $baris, string $nama = 'data.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tes').'.xlsx';
        $w = new Writer();
        $w->openToFile($path);
        foreach ($baris as $b) {
            $w->addRow(Row::fromValues($b));
        }
        $w->close();

        return new UploadedFile($path, $nama, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function kolomGuru(): array
    {
        return ['nip', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'mata_pelajaran', 'no_hp', 'username', 'email'];
    }

    private function kolomSiswa(): array
    {
        return ['nisn', 'nama', 'jenis_kelamin', 'agama', 'tanggal_lahir', 'rombel', 'username', 'email'];
    }

    // ------------------------------------------------------------ template

    public function test_template_guru_dan_siswa_dapat_diunduh_dan_dibaca_kembali(): void
    {
        $this->actingAs($this->admin);

        foreach (['guru' => $this->kolomGuru(), 'siswa' => $this->kolomSiswa()] as $jenis => $kolom) {
            $res = $this->get("/admin/impor/$jenis/template")->assertOk();
            $this->assertStringContainsString("template-impor-$jenis.xlsx", $res->headers->get('content-disposition'));

            $path = $res->baseResponse->getFile()->getPathname();
            $r = new Reader();
            $r->open($path);
            $sheets = [];
            foreach ($r->getSheetIterator() as $sheet) {
                $sheets[$sheet->getName()] = array_map(fn ($row) => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
            }
            $r->close();

            $this->assertSame($kolom, $sheets['Data'][0]);
            $this->assertStringStartsWith('CONTOH', (string) $sheets['Data'][1][0], 'baris ke-2 adalah contoh yang dilewati saat impor');
            foreach (array_slice($sheets['Data'], 2) as $kosong) {
                $this->assertSame([], array_filter($kosong, fn ($v) => $v !== '' && $v !== null), 'baris berikutnya kosong, siap diisi');
            }
            $this->assertArrayHasKey('Petunjuk', $sheets);
            $this->assertStringContainsString('Wajib?', json_encode($sheets['Petunjuk']));

            // template yang belum diisi dibaca sebagai berkas tanpa data (contoh tidak ikut terimpor)
            $this->assertSame([], Impor::baca($path, 'xlsx'));
        }

        $this->get('/admin/impor/lain/template')->assertNotFound();
    }

    public function test_hanya_admin_yang_boleh_impor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->get('/admin/impor')->assertRedirect();
        $this->postJson('/admin/impor/guru/periksa', ['berkas' => $this->xlsx([$this->kolomGuru()])])->assertForbidden();
    }

    // ------------------------------------------------------------ impor guru

    public function test_impor_guru_periksa_lalu_simpan(): void
    {
        $this->actingAs($this->admin);
        Mapel::create(['mapel_nama' => 'Matematika']);
        Guru::create(['nip_guru' => '111', 'nama_guru' => 'Sudah Ada', 'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '1', 'tanggal_lahir' => '1980-01-01', 'mapel_id' => Mapel::first()->id, 'user_id' => User::factory()->create(['role' => 'guru'])->id]);

        $berkas = $this->xlsx([
            $this->kolomGuru(),
            ['198001', 'Budi Santoso', 'L', '1980-01-31', 'Matematika', '0812', '', ''],
            ['198002', 'Sari Dewi', 'Perempuan', '15/03/1985', 'Bahasa Inggris', '', 'sari.dewi', 'sari@contoh.id'],
            ['111', 'NIP Kembar DB', 'L', '1980-01-01', 'Matematika', '', '', ''],
            ['198001', 'NIP Kembar File', 'L', '1980-01-01', 'Matematika', '', '', ''],
            ['198003', 'Salah JK', 'X', '1980-01-01', 'Matematika', '', '', ''],
            ['198004', 'Tanggal Rusak', 'L', 'kemarin', 'Matematika', '', '', ''],
            ['198005', 'Tanpa Mapel', 'L', '1980-01-01', '', '', '', ''],
            ['198006', 'Username Jelek', 'L', '1980-01-01', 'Matematika', '', 'Ada Spasi', ''],
        ]);

        $res = $this->postJson('/admin/impor/guru/periksa', ['berkas' => $berkas])->assertOk();
        $this->assertSame([2, 6], [$res->json('jumlah_valid'), $res->json('jumlah_galat')]);
        $this->assertSame(['198001', '198002'], array_column($res->json('contoh'), 'id'));
        $galat = collect($res->json('galat'))->pluck('pesan', 'baris');
        $this->assertStringContainsString('sudah terdaftar', $galat[4]);
        $this->assertStringContainsString('kembar dengan baris 2', $galat[5]);
        $this->assertStringContainsString('jenis kelamin', $galat[6]);
        $this->assertStringContainsString('tanggal lahir', $galat[7]);
        $this->assertStringContainsString('mata_pelajaran kosong', $galat[8]);
        $this->assertStringContainsString('username', $galat[9]);
        $this->assertSame(1, Guru::count(), 'pemeriksaan tidak mengubah data');

        $token = $res->json('token');
        $simpan = $this->postJson('/admin/impor/guru/simpan', ['token' => $token])->assertOk();
        $this->assertSame([2, 6], [$simpan->json('tersimpan'), $simpan->json('dilewati')]);
        $this->assertSame(['Bahasa Inggris'], $simpan->json('mapel_baru'));

        $budi = Guru::where('nip_guru', '198001')->firstOrFail();
        $this->assertSame(['Laki-Laki', '1980-01-31', 'Matematika', '0812'], [$budi->jenis_kelamin, $budi->tanggal_lahir->toDateString(), $budi->mapel->mapel_nama, $budi->no_hp]);
        $this->assertSame('198001', $budi->user->username);
        $this->assertSame('198001@impor.invalid', $budi->user->email);
        $this->assertTrue($budi->user->punyaPeran('guru'));
        $this->assertTrue(\Hash::check('guru123', $budi->user->password));

        $sari = Guru::where('nip_guru', '198002')->firstOrFail();
        $this->assertSame(['sari.dewi', 'sari@contoh.id', '1985-03-15'], [$sari->user->username, $sari->user->email, $sari->tanggal_lahir->toDateString()]);

        // token hanya berlaku sekali
        $this->postJson('/admin/impor/guru/simpan', ['token' => $token])->assertStatus(410);
        $this->assertSame(3, Guru::count());
    }

    public function test_guru_hasil_impor_bisa_login_dengan_username_nip(): void
    {
        $this->actingAs($this->admin);
        $res = $this->postJson('/admin/impor/guru/periksa', ['berkas' => $this->xlsx([$this->kolomGuru(), ['777', 'Guru Impor', 'P', '1990-05-05', 'IPA', '', '', '']])])->assertOk();
        $this->postJson('/admin/impor/guru/simpan', ['token' => $res->json('token')])->assertOk();

        auth()->logout();
        $this->post('/login', ['username' => '777', 'password' => 'guru123'])->assertRedirect(route('guru/dashboard'));
    }

    // ------------------------------------------------------------ impor siswa

    public function test_impor_siswa_menempatkan_ke_rombel_dan_memeriksa_agama_dan_rombel(): void
    {
        $this->actingAs($this->admin);
        $rombel = Kelas::create(['nama_kelas' => 'VII A']);

        $berkas = $this->xlsx([
            $this->kolomSiswa(),
            ['0011', 'Ani', 'Perempuan', 'islam', '2010-05-17', 'vii a', '', ''],
            ['0012', 'Budi', 'L', 'Buddha', '17-05-2010', '', 'budi.kecil', ''],
            ['0013', 'Cici', 'P', 'Pagan', '2010-01-01', '', '', ''],
            ['0014', 'Dedi', 'L', 'Hindu', '2010-01-01', 'VII Z', '', ''],
            ['0011', 'Ani Kembar', 'P', 'Islam', '2010-01-01', '', '', ''],
        ]);

        $res = $this->postJson('/admin/impor/siswa/periksa', ['berkas' => $berkas])->assertOk();
        $this->assertSame([2, 3], [$res->json('jumlah_valid'), $res->json('jumlah_galat')]);
        $galat = collect($res->json('galat'))->pluck('pesan', 'baris');
        $this->assertStringContainsString('agama', $galat[4]);
        $this->assertStringContainsString('rombel VII Z tidak ada', $galat[5]);
        $this->assertStringContainsString('kembar', $galat[6]);

        $this->postJson('/admin/impor/siswa/simpan', ['token' => $res->json('token')])->assertOk();

        $ani = Siswa::where('nisn', '0011')->firstOrFail();
        $this->assertSame([$rombel->id, 'Islam'], [$ani->kelas_id, $ani->agama]);
        $this->assertSame(1, $rombel->anggota()->count(), 'tercatat di rombel_siswa');
        $this->assertSame('0011', $ani->user->username);
        $this->assertTrue(\Hash::check('siswa123', $ani->user->password));

        $budi = Siswa::where('nisn', '0012')->firstOrFail();
        $this->assertSame(['Budha', '2010-05-17', null, 'budi.kecil'], [$budi->agama, $budi->tanggal_lahir->toDateString(), $budi->kelas_id, $budi->user->username]);
    }

    public function test_berkas_csv_dan_sel_tanggal_excel_didukung(): void
    {
        $this->actingAs($this->admin);
        $csv = UploadedFile::fake()->createWithContent('siswa.csv', "nisn,nama,jenis_kelamin,agama,tanggal_lahir\n0021,Eka,P,Kristen,2011-02-03\n");
        $res = $this->postJson('/admin/impor/siswa/periksa', ['berkas' => $csv])->assertOk();
        $this->assertSame(1, $res->json('jumlah_valid'));

        // sel bertipe tanggal pada Excel
        $path = tempnam(sys_get_temp_dir(), 'tes').'.xlsx';
        $w = new Writer();
        $w->openToFile($path);
        $w->addRow(Row::fromValues($this->kolomSiswa()));
        $w->addRow(Row::fromValues(['0022', 'Fani', 'P', 'Katolik', new \DateTimeImmutable('2011-04-05'), '', '', '']));
        $w->close();
        $res = $this->postJson('/admin/impor/siswa/periksa', ['berkas' => new UploadedFile($path, 'd.xlsx', null, null, true)])->assertOk();
        $this->assertSame(1, $res->json('jumlah_valid'));
        $this->postJson('/admin/impor/siswa/simpan', ['token' => $res->json('token')])->assertOk();
        $this->assertSame('2011-04-05', Siswa::where('nisn', '0022')->first()->tanggal_lahir->toDateString());
    }

    // ------------------------------------------------------------ penolakan berkas

    public function test_berkas_tidak_sesuai_ditolak_dengan_pesan_jelas(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/admin/impor/guru/periksa', [])->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->postJson('/admin/impor/guru/periksa', ['berkas' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->postJson('/admin/impor/guru/periksa', ['berkas' => UploadedFile::fake()->create('besar.xlsx', 3000)])->assertStatus(422);

        $this->postJson('/admin/impor/guru/periksa', ['berkas' => $this->xlsx([['nip', 'nama'], ['1', 'A']])])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Kolom wajib tidak ditemukan') && str_contains($m, 'jenis_kelamin'));
        $this->postJson('/admin/impor/guru/periksa', ['berkas' => UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan zip')])->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'tidak dapat dibaca'));
        $this->assertEmpty(Storage::disk('local')->allFiles(), 'berkas sementara dibersihkan saat gagal');

        $this->postJson('/admin/impor/guru/simpan', ['token' => 'pendek'])->assertStatus(422);
        $this->postJson('/admin/impor/guru/simpan', ['token' => str_repeat('a', 32)])->assertStatus(410);
    }

    public function test_batas_jumlah_baris(): void
    {
        $this->actingAs($this->admin);
        $baris = [$this->kolomGuru()];
        for ($i = 0; $i <= Impor::MAKS_BARIS; $i++) {
            $baris[] = ["n$i", "Guru $i", 'L', '1980-01-01', 'IPA', '', '', ''];
        }

        $this->postJson('/admin/impor/guru/periksa', ['berkas' => $this->xlsx($baris)])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'melebihi'));
    }

    public function test_token_sekolah_lain_tidak_bisa_dipakai(): void
    {
        $this->actingAs($this->admin);
        $token = $this->postJson('/admin/impor/guru/periksa', ['berkas' => $this->xlsx([$this->kolomGuru(), ['1', 'A', 'L', '1980-01-01', 'IPA', '', '', '']])])->json('token');

        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMA', 'status' => 'aktif', 'tanpa_batas' => true]);
        SekolahAktif::jalankan($lain, function () use ($token) {
            $admin = User::factory()->create(['role' => 'admin']);
            $this->actingAs($admin)->postJson('/admin/impor/guru/simpan?sekolah=lain', ['token' => $token])->assertStatus(410);
        });
        $this->assertSame(0, Guru::withoutGlobalScopes()->count());
    }

    // ------------------------------------------------------------ wizard pengaturan awal

    public function test_wizard_menampilkan_langkah_dan_kemajuannya(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/setup')->assertOk()->assertSee('Identitas sekolah')->assertSee('Tahun ajaran &amp; semester', false)->assertSee('Data guru')->assertSee('Data siswa');

        $this->get('/admin/dashboard')->assertSee('Selesaikan pengaturan awal sekolah');
        $this->postJson('/admin/setup/selesai')->assertOk()->assertJsonPath('redirect', route('admin/dashboard'));
        $this->assertSame('1', Setting::get('setup_selesai'));
        $this->get('/admin/dashboard')->assertDontSee('Selesaikan pengaturan awal sekolah');
    }

    public function test_wizard_identitas_tersimpan_dan_dipakai_situs(): void
    {
        $this->actingAs($this->admin);

        $this->putJson('/admin/setup/identitas', [])->assertStatus(422)->assertJsonValidationErrors(['nama_sekolah', 'nama_singkat']);
        $this->putJson('/admin/setup/identitas', ['nama_sekolah' => 'SMP Negeri 9 Contoh', 'nama_singkat' => 'SMPN 9', 'alamat' => 'Jl. Mawar 1'])->assertOk();

        $this->assertSame('SMP Negeri 9 Contoh', Setting::get('nama_sekolah'));
        $this->get('/')->assertOk()->assertSee('SMP Negeri 9 Contoh');
    }

    public function test_wizard_akademik_membuat_tahun_ajaran_dan_mengaktifkan_semester(): void
    {
        $this->actingAs($this->admin);

        $this->putJson('/admin/setup/akademik', ['tahun_ajaran' => '2030-2031', 'semester' => 'Ganjil', 'kkm' => 70])->assertStatus(422)->assertJsonValidationErrors('tahun_ajaran');
        $this->putJson('/admin/setup/akademik', ['tahun_ajaran' => '2030/2032', 'semester' => 'Ganjil', 'kkm' => 70])->assertStatus(422);
        $this->putJson('/admin/setup/akademik', ['tahun_ajaran' => '2030/2031', 'semester' => 'Tiga', 'kkm' => 70])->assertStatus(422);
        $this->putJson('/admin/setup/akademik', ['tahun_ajaran' => '2030/2031', 'semester' => 'Genap', 'kkm' => 75])->assertOk();

        $aktif = Semester::where('aktif', true)->with('tahunAjaran')->get();
        $this->assertCount(1, $aktif);
        $this->assertSame(['2030/2031', 'Genap'], [$aktif[0]->tahunAjaran->nama, $aktif[0]->jenis]);
        $this->assertSame([75, 75], Semester::whereHas('tahunAjaran', fn ($q) => $q->where('nama', '2030/2031'))->pluck('kkm')->all());

        // ulangi dengan tahun ajaran yang sama tidak menggandakan
        $this->putJson('/admin/setup/akademik', ['tahun_ajaran' => '2030/2031', 'semester' => 'Ganjil', 'kkm' => 80])->assertOk();
        $this->assertSame(1, TahunAjaran::where('nama', '2030/2031')->count());
        $this->assertSame('Ganjil', Semester::where('aktif', true)->value('jenis'));
    }

    public function test_wizard_struktur_menambah_mapel_dan_rombel_tanpa_duplikat(): void
    {
        $this->actingAs($this->admin);
        Mapel::create(['mapel_nama' => 'IPA']);

        $this->postJson('/admin/setup/struktur', [])->assertStatus(422);
        $res = $this->postJson('/admin/setup/struktur', ['mapel' => "IPA\nMatematika\n\n  Seni Budaya  \nMatematika", 'rombel' => "VII A\nVII B"])->assertOk();

        $this->assertStringContainsString('2 mata pelajaran dan 2 rombel', $res->json('message'));
        $this->assertEqualsCanonicalizing(['IPA', 'Matematika', 'Seni Budaya'], Mapel::pluck('mapel_nama')->all());
        $this->assertSame(['VII A', 'VII B'], Kelas::tahunAktif()->orderBy('nama_kelas')->pluck('nama_kelas')->all());

        $this->postJson('/admin/setup/struktur', ['rombel' => 'VII A'])->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, '0 mata pelajaran dan 0 rombel'));
        $this->assertSame(2, Kelas::count());

        $this->get('/admin/setup')->assertOk()->assertSee('3 mapel · 2 rombel');
    }

    public function test_pengguna_non_admin_tidak_bisa_membuka_wizard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));
        $this->get('/admin/setup')->assertRedirect();
        $this->putJson('/admin/setup/identitas', ['nama_sekolah' => 'X', 'nama_singkat' => 'X'])->assertForbidden();
    }

    public function test_migrasi_menandai_sekolah_lama_yang_sudah_berisi_data_sebagai_selesai(): void
    {
        $this->actingAs($this->admin);
        $this->assertNull(Setting::get('setup_selesai'));
        Guru::create(['nip_guru' => '1', 'nama_guru' => 'G', 'jenis_kelamin' => 'Laki-Laki', 'no_hp' => '1', 'tanggal_lahir' => '1980-01-01', 'mapel_id' => Mapel::create(['mapel_nama' => 'X'])->id, 'user_id' => User::factory()->create(['role' => 'guru'])->id]);

        (require base_path('database/migrations/2026_11_18_000001_tandai_setup_selesai_sekolah_lama.php'))->up();
        Setting::flush();

        $this->assertSame('1', Setting::get('setup_selesai'));
        $this->assertSame(1, DB::table('site_settings')->where('key', 'setup_selesai')->count());
    }
}
