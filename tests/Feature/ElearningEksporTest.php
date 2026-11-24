<?php

namespace Tests\Feature;

use App\Console\Commands\ElearningEkspor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Penghapusan modul e-learning: ekspor JSON dulu, migrasi DROP menolak berjalan bila data belum diekspor. */
class ElearningEksporTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir().'/smpn-ekspor-'.uniqid();
        ElearningEkspor::$folderUji = $this->folder;
    }

    protected function tearDown(): void
    {
        ElearningEkspor::$folderUji = null;
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    private function migrasi(): object
    {
        return require base_path('database/migrations/2026_11_10_000001_drop_tabel_elearning.php');
    }

    private function buatTabelDanIsi(): void
    {
        $this->migrasi()->down(); // struktur lama (kosong)
        DB::table('materi')->insert(['materi' => 'Bab 1 é', 'mapel_id' => 1, 'kelas_id' => 1, 'file_materi' => 'bab1.pdf', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tugas')->insert(['judul' => 'PR', 'mapel_id' => 1, 'jenis_tugas' => 'pilgan', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('soal_pilgan')->insert(['tugas_id' => 1, 'soal' => '1+1?', 'a' => '1', 'b' => '2', 'c' => '3', 'd' => '4', 'kunci' => 'b', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('kelas_tugas')->insert(['kelas_id' => 1, 'tugas_id' => 1]);
    }

    public function test_tabel_elearning_sudah_tidak_ada_setelah_migrasi(): void
    {
        foreach (ElearningEkspor::TABEL as $tabel) {
            $this->assertFalse(Schema::hasTable($tabel), $tabel);
        }
    }

    public function test_migrasi_drop_menolak_berjalan_bila_data_belum_diekspor(): void
    {
        $this->buatTabelDanIsi();

        try {
            $this->migrasi()->up();
            $this->fail('Migrasi seharusnya menolak karena data belum diekspor.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('php artisan elearning:ekspor', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('materi'));
        $this->assertSame(1, DB::table('materi')->count(), 'data tidak boleh hilang');
    }

    public function test_ekspor_menulis_json_dan_manifest_lalu_drop_diizinkan(): void
    {
        $this->buatTabelDanIsi();

        $this->artisan('elearning:ekspor')->assertSuccessful();

        $materi = json_decode(File::get($this->folder.'/materi.json'), true);
        $this->assertSame('Bab 1 é', $materi[0]['materi']);
        $this->assertSame('bab1.pdf', $materi[0]['file_materi']);
        $this->assertCount(1, json_decode(File::get($this->folder.'/soal_pilgan.json'), true));
        $this->assertSame([], json_decode(File::get($this->folder.'/tugas_siswa.json'), true), 'tabel kosong tetap diekspor');

        $manifest = json_decode(File::get($this->folder.'/manifest.json'), true);
        $this->assertSame(1, $manifest['jumlah_baris']['materi']);
        $this->assertSame(1, $manifest['jumlah_baris']['kelas_tugas']);
        $this->assertSame(['bab1.pdf'], $manifest['berkas_materi']);

        $this->migrasi()->up();
        foreach (ElearningEkspor::TABEL as $tabel) {
            $this->assertFalse(Schema::hasTable($tabel), $tabel);
        }
    }

    public function test_ekspor_lama_yang_tertinggal_tidak_cukup_bila_data_bertambah(): void
    {
        $this->buatTabelDanIsi();
        $this->artisan('elearning:ekspor')->assertSuccessful();

        DB::table('materi')->insert(['materi' => 'Bab 2', 'mapel_id' => 1, 'kelas_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(\RuntimeException::class);
        $this->migrasi()->up();
    }

    public function test_migrasi_boleh_berjalan_pada_database_tanpa_data(): void
    {
        $this->migrasi()->down();

        $this->migrasi()->up(); // semua tabel kosong: tanpa ekspor pun aman
        $this->assertFalse(Schema::hasTable('tugas'));
    }
}
