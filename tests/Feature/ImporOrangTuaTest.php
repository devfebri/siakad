<?php

namespace Tests\Feature;

use App\Kelas;
use App\Langganan;
use App\OrangTua;
use App\Paket;
use App\Sekolah;
use App\Siswa;
use App\Support\Impor;
use App\Support\AksesSekolah;
use App\Support\SekolahAktif;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Impor Excel orang tua (dihubungkan ke anak lewat NISN), template-nya, dan tombol impor di halaman data. */
class ImporOrangTuaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Siswa $andi;
    private Siswa $budi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);

        $kelas = Kelas::create(['nama_kelas' => 'VII A', 'tingkat' => 7]);
        $this->andi = $this->siswa('1001', 'Andi Pratama', $kelas);
        $this->budi = $this->siswa('1002', 'Budi Pratama', $kelas);
    }

    private function siswa(string $nisn, string $nama, ?Kelas $kelas = null, ?Sekolah $sekolah = null): Siswa
    {
        return SekolahAktif::jalankan($sekolah ?? $this->sekolah, fn () => Siswa::create([
            'user_id' => User::factory()->create(['role' => 'siswa'])->id, 'nisn' => $nisn, 'nama_siswa' => $nama, 'agama' => 'Islam',
            'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'kelas_id' => $kelas?->id,
        ]));
    }

    private function kolom(): array
    {
        return ['nama', 'no_hp', 'nisn_anak', 'hubungan', 'pekerjaan', 'alamat', 'username', 'email'];
    }

    private function xlsx(array $baris): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ot').'.xlsx';
        $w = new Writer();
        $w->openToFile($path);
        foreach ($baris as $b) {
            $w->addRow(Row::fromValues($b));
        }
        $w->close();

        return new UploadedFile($path, 'ortu.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function periksa(array $baris)
    {
        return $this->postJson('/admin/impor/orang_tua/periksa', ['berkas' => $this->xlsx(array_merge([$this->kolom()], $baris))]);
    }

    private function paket(string $kode): void
    {
        $this->sekolah->update(['tanpa_batas' => false]);
        Langganan::create(['sekolah_id' => $this->sekolah->id, 'paket_id' => Paket::where('kode', $kode)->value('id'), 'durasi_bulan' => 1, 'mulai' => today()->subDay(), 'berakhir' => today()->addMonth(), 'status' => 'aktif']);
        AksesSekolah::lupakan();
    }

    // ------------------------------------------------------------ template

    public function test_template_orang_tua_berisi_kolom_petunjuk_dan_daftar_siswa(): void
    {
        $this->actingAs($this->admin);

        $res = $this->get('/admin/impor/orang_tua/template')->assertOk();
        $this->assertStringContainsString('template-impor-orang_tua.xlsx', $res->headers->get('content-disposition'));

        $r = new Reader();
        $r->open($res->baseResponse->getFile()->getPathname());
        $sheets = [];
        foreach ($r->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = array_map(fn ($row) => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
        }
        $r->close();

        $this->assertSame($this->kolom(), $sheets['Data'][0]);
        $this->assertStringStartsWith('CONTOH', (string) $sheets['Data'][1][0]);   // baris contoh, dilewati saat impor
        $this->assertSame([], Impor::baca($res->baseResponse->getFile()->getPathname(), 'xlsx'));
        $this->assertTrue(file_exists(base_path('docs/templat-impor/template-impor-orang_tua.xlsx')));
        $this->assertArrayHasKey('Petunjuk', $sheets);
        $petunjuk = json_encode($sheets['Petunjuk']);
        $this->assertStringContainsString('nisn_anak', $petunjuk);
        $this->assertStringContainsString('Bambang Pratama', $petunjuk);   // baris contoh

        // lembar contekan NISN anak
        $this->assertSame(['nisn', 'nama', 'rombel'], $sheets['Daftar Siswa'][0]);
        $daftar = collect($sheets['Daftar Siswa'])->slice(1)->map(fn ($b) => [(string) $b[0], $b[1], $b[2]])->sortBy(0)->values()->all();
        $this->assertSame([['1001', 'Andi Pratama', 'VII A'], ['1002', 'Budi Pratama', 'VII A']], $daftar);
    }

    public function test_daftar_siswa_di_template_hanya_siswa_aktif_sekolah_ini(): void
    {
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        $this->siswa('9001', 'Orang Lain', null, $lain);
        Siswa::where('nisn', '1002')->update(['status' => 'lulus']);
        $this->actingAs($this->admin);

        $res = $this->get('/admin/impor/orang_tua/template')->assertOk();
        $r = new Reader();
        $r->open($res->baseResponse->getFile()->getPathname());
        $teks = '';
        foreach ($r->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Daftar Siswa') {
                foreach ($sheet->getRowIterator() as $row) {
                    $teks .= json_encode($row->toArray());
                }
            }
        }
        $r->close();

        $this->assertStringContainsString('Andi Pratama', $teks);
        $this->assertStringNotContainsString('Budi Pratama', $teks);   // sudah lulus
        $this->assertStringNotContainsString('Orang Lain', $teks);     // sekolah lain
    }

    // ------------------------------------------------------------ pemeriksaan

    public function test_pemeriksaan_baris_orang_tua(): void
    {
        $this->actingAs($this->admin);
        OrangTua::create(['user_id' => User::factory()->create(['role' => 'orang_tua'])->id, 'nama' => 'Ibu Sudah Ada'])->hubungkan($this->andi, 'ibu');
        User::factory()->create(['username' => 'dipakai', 'email' => 'dipakai@contoh.test']);

        $res = $this->periksa([
            ['Bambang Pratama', '0812 3456 7890', '1001; 1002', 'Ayah', 'Wiraswasta', 'Jl. A', '', ''],        // 2: valid, dua anak
            ['', '0812', '1001', '', '', '', '', ''],                                                          // 3: nama kosong
            ['Tanpa Anak', '', '', '', '', '', '', ''],                                                        // 4: nisn_anak kosong
            ['Anak Hantu', '', '7777', '', '', '', '', ''],                                                    // 5: NISN tidak ada
            ['Ibu Sudah Ada', '', '1001', 'Ibu', '', '', '', ''],                                              // 6: sudah terhubung (case-insensitive juga)
            ['Bambang Pratama', '', '1001', 'Ayah', '', '', '', ''],                                           // 7: kembar baris 2
            ['Hub Salah', '', '1002', 'Paman', '', '', '', ''],                                                // 8: hubungan salah
            ['User Salah', '', '1002', '', '', '', 'A B', ''],                                                 // 9: username tidak sah
            ['Pakai Username', '', '1002', '', '', '', 'dipakai', 'dipakai@contoh.test'],                      // 10: username dan email sudah ada
            ['Siti Ibu', '', '1002', 'ibu', '', '', 'siti.ibu', 'siti@contoh.test'],                           // 11: valid
        ])->assertOk();

        $this->assertSame(2, $res->json('jumlah_valid'));
        $galat = collect($res->json('galat'))->pluck('pesan', 'baris');
        $this->assertStringContainsString('kolom nama kosong', $galat[3]);
        $this->assertStringContainsString('kolom nisn_anak kosong', $galat[4]);
        $this->assertStringContainsString('NISN anak 7777 belum terdaftar', $galat[5]);
        $this->assertStringContainsString('sudah terhubung ke Andi Pratama', $galat[6]);
        $this->assertStringContainsString('kembar dengan baris 2', $galat[7]);
        $this->assertStringContainsString('hubungan harus Ayah, Ibu, atau Wali', $galat[8]);
        $this->assertStringContainsString('username hanya huruf kecil', $galat[9]);
        $this->assertStringContainsString('username dipakai sudah dipakai', $galat[10]);
        $this->assertStringContainsString('email dipakai@contoh.test sudah dipakai', $galat[10]);

        $contoh = collect($res->json('contoh'))->keyBy('baris');
        $this->assertSame('Andi Pratama, Budi Pratama', $contoh[2]['info']);
        $this->assertSame('0812 3456 7890', $contoh[2]['id']);
        $this->assertSame('-', $contoh[11]['id']);                    // tanpa no. HP
    }

    public function test_kolom_wajib_hilang_ditolak_dengan_pesan_jelas(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/admin/impor/orang_tua/periksa', ['berkas' => $this->xlsx([['nama', 'no_hp'], ['Bambang', '0812']])])
            ->assertStatus(422)->assertJsonPath('message', 'Kolom wajib tidak ditemukan: nisn_anak. Gunakan template dari halaman ini.');
    }

    public function test_nisn_siswa_sekolah_lain_tidak_dikenali(): void
    {
        $lain = Sekolah::create(['nama' => 'Lain', 'subdomain' => 'lain', 'jenjang' => 'SMP', 'status' => 'aktif', 'tanpa_batas' => true]);
        $this->siswa('9001', 'Anak Sekolah Lain', null, $lain);
        $this->actingAs($this->admin);

        $res = $this->periksa([['Bapak Lain', '', '9001', '', '', '', '', '']])->assertOk();

        $this->assertSame(0, $res->json('jumlah_valid'));
        $this->assertStringContainsString('NISN anak 9001 belum terdaftar', $res->json('galat.0.pesan'));
    }

    // ------------------------------------------------------------ baris contoh dan format template

    public function test_baris_contoh_dilewati_saat_impor_dan_data_asli_tetap_terbaca(): void
    {
        $this->actingAs($this->admin);

        $res = $this->periksa([
            ['CONTOH - Bambang Pratama', '081234567890', '1001', 'Ayah', '', '', '', ''],   // baris contoh dari template
            ['  contoh bukan data', '', '1001', '', '', '', '', ''],                         // huruf kecil dan spasi tetap dianggap contoh
            ['Siti Ibu', '', '1001', 'Ibu', '', '', '', ''],
        ])->assertOk();

        $this->assertSame(1, $res->json('jumlah_valid'));
        $this->assertSame(0, $res->json('jumlah_galat'));
        $this->assertSame('Siti Ibu', $res->json('contoh.0.nama'));
    }

    public function test_nama_berawalan_contoh_di_baris_pertama_data_tidak_hilang_sebagai_judul(): void
    {
        $this->actingAs($this->admin);

        // baris pertama berkas selalu judul kolom, bukan contoh
        $res = $this->postJson('/admin/impor/orang_tua/periksa', ['berkas' => $this->xlsx([$this->kolom(), ['Siti Ibu', '', '1001', '', '', '', '', '']])])->assertOk();

        $this->assertSame(1, $res->json('jumlah_valid'));
    }

    public function test_berkas_template_umum_di_docs_dapat_dibuka_dan_berisi_judul_yang_benar(): void
    {
        foreach (array_keys(Impor::JENIS) as $jenis) {
            $path = base_path("docs/templat-impor/template-impor-$jenis.xlsx");
            $this->assertFileExists($path);
            $this->assertSame([], Impor::baca($path, 'xlsx'));

            $r = new Reader();
            $r->open($path);
            foreach ($r->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $this->assertSame(Impor::JENIS[$jenis]['kolom'], $row->toArray());
                    break 2;
                }
            }
            $r->close();
        }
    }

    // ------------------------------------------------------------ penyimpanan

    public function test_simpan_membuat_akun_orang_tua_dan_menghubungkan_anak(): void
    {
        $this->actingAs($this->admin);
        $res = $this->periksa([
            ['Bambang Pratama', '0812 3456 7890', '1001; 1002', 'Ayah', 'Wiraswasta', 'Jl. Merdeka 1', '', ''],
            ['Siti Ibu', '', '1001', 'Ibu', '', '', 'siti.ibu', 'siti@contoh.test'],
            ['Wali Budi', '0811', '1002,', '', '', '', '', ''],
            ['Rudi', '', '1002', '', '', '', '', ''],
        ])->assertOk();
        $this->assertSame(4, $res->json('jumlah_valid'));

        $simpan = $this->postJson('/admin/impor/orang_tua/simpan', ['token' => $res->json('token')])->assertOk();
        $this->assertSame(4, $simpan->json('tersimpan'));
        $this->assertStringContainsString('4 Orang Tua diimpor (password awal: ortu123)', $simpan->json('message'));

        $bambang = OrangTua::where('nama', 'Bambang Pratama')->first();
        $this->assertSame('0812 3456 7890', $bambang->no_hp);
        $this->assertSame('Wiraswasta', $bambang->pekerjaan);
        $this->assertSame('Jl. Merdeka 1', $bambang->alamat);
        $this->assertSame(['1001', '1002'], $bambang->anak->pluck('nisn')->sort()->values()->all());
        $this->assertSame(['ayah', 'ayah'], $bambang->anak->pluck('pivot.hubungan')->all());
        $this->assertTrue($bambang->user->punyaPeran('orang_tua'));
        $this->assertTrue(\Hash::check('ortu123', $bambang->user->password));

        // username otomatis: ortu + NISN anak pertama, dibuat unik bila ada yang sama
        $this->assertSame('ortu1001', $bambang->user->username);
        $this->assertSame('siti.ibu', OrangTua::where('nama', 'Siti Ibu')->first()->user->username);
        $this->assertSame('wali', OrangTua::where('nama', 'Wali Budi')->first()->anak->first()->pivot->hubungan);   // hubungan kosong = wali
        $usernames = OrangTua::with('user')->get()->pluck('user.username')->all();
        $this->assertCount(4, array_unique($usernames));
        $this->assertContains('ortu1002', $usernames);
        $this->assertContains('ortu10022', $usernames);

        // anak punya dua orang tua (ayah dan ibu)
        $this->assertSame(['Bambang Pratama', 'Siti Ibu'], $this->andi->fresh()->load('orangTua')->orangTua->pluck('nama')->sort()->values()->all());

        // token sekali pakai, dan impor ulang berkas yang sama ditolak sebagai duplikat
        $this->postJson('/admin/impor/orang_tua/simpan', ['token' => $res->json('token')])->assertStatus(410);
        $lagi = $this->periksa([['Bambang Pratama', '', '1001', 'Ayah', '', '', '', '']])->assertOk();
        $this->assertSame(0, $lagi->json('jumlah_valid'));
        $this->assertStringContainsString('sudah terhubung', $lagi->json('galat.0.pesan'));
    }

    public function test_orang_tua_hasil_impor_bisa_login_dan_melihat_anaknya(): void
    {
        $this->actingAs($this->admin);
        $res = $this->periksa([['Bambang Pratama', '', '1001', 'Ayah', '', '', '', '']]);
        $this->postJson('/admin/impor/orang_tua/simpan', ['token' => $res->json('token')])->assertOk();
        auth()->logout();

        $this->post('/login', ['username' => 'ortu1001', 'password' => 'ortu123'])->assertRedirect(route('ortu/dashboard'));
        $this->get('/ortu/dashboard')->assertOk()->assertSee('Andi Pratama');
    }

    public function test_baris_bermasalah_dilewati_dan_sisanya_tersimpan(): void
    {
        $this->actingAs($this->admin);
        $res = $this->periksa([['Valid', '', '1001', '', '', '', '', ''], ['Rusak', '', '0000', '', '', '', '', '']])->assertOk();

        $simpan = $this->postJson('/admin/impor/orang_tua/simpan', ['token' => $res->json('token')])->assertOk();

        $this->assertSame(1, $simpan->json('tersimpan'));
        $this->assertSame(1, $simpan->json('dilewati'));
        $this->assertStringContainsString('1 baris dilewati', $simpan->json('message'));
        $this->assertSame(1, OrangTua::count());
    }

    // ------------------------------------------------------------ halaman, paket, dan hak akses

    public function test_halaman_impor_menampilkan_tiga_jenis_dan_tombol_ada_di_halaman_data(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/impor')->assertOk()->assertSee('Impor Guru')->assertSee('Impor Siswa')->assertSee('Impor Orang Tua')->assertSee('lembar')->assertSee('NISN');
        foreach (['/admin/guru', '/admin/siswa', '/admin/orang-tua'] as $halaman) {
            $this->get($halaman)->assertOk()->assertSee('Impor Excel')->assertSee(route('admin/impor'));
        }
    }

    public function test_impor_orang_tua_hanya_bila_paket_punya_portal_atau_keuangan(): void
    {
        $this->actingAs($this->admin);
        $this->paket('basic');

        $this->get('/admin/impor')->assertOk()->assertSee('Impor Siswa')->assertDontSee('Impor Orang Tua');
        $this->get('/admin/impor/orang_tua/template')->assertNotFound();
        $this->postJson('/admin/impor/orang_tua/periksa', ['berkas' => $this->xlsx([$this->kolom()])])->assertNotFound();
        $this->get('/admin/impor/siswa/template')->assertOk();                  // jenis lain tidak terpengaruh

        Langganan::query()->update(['paket_id' => Paket::where('kode', 'pro')->value('id')]);
        AksesSekolah::lupakan();
        $this->get('/admin/impor')->assertSee('Impor Orang Tua');
        $this->get('/admin/impor/orang_tua/template')->assertOk();
    }

    public function test_impor_orang_tua_hanya_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));

        $this->get('/admin/impor/orang_tua/template')->assertRedirect();
        $this->postJson('/admin/impor/orang_tua/periksa', ['berkas' => $this->xlsx([$this->kolom()])])->assertForbidden();
        $this->assertSame(0, OrangTua::count());
    }
}
