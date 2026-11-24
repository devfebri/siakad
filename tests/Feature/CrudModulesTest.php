<?php

namespace Tests\Feature;

use App\Agenda;
use App\Kategori;
use App\Kelas;
use App\Pengumuman;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** CRUD AJAX untuk kategori, kelas, pengumuman, dan agenda (pola sama dengan MapelCrudTest). */
class CrudModulesTest extends TestCase
{
    use RefreshDatabase;

    private function dt(array $extra = []): string
    {
        return http_build_query($extra + [
            'draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => ''],
            'columns' => [['data' => null]],
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public static function modules(): array
    {
        return [['kategori'], ['kelas'], ['pengumuman'], ['agenda']];
    }

    #[DataProvider('modules')]
    public function test_halaman_dan_data_dapat_dibuka(string $name): void
    {
        $this->actingAs($this->admin());

        $this->get("/admin/$name")->assertOk();
        $this->getJson("/admin/$name/data?".$this->dt())
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    #[DataProvider('modules')]
    public function test_tamu_ditolak(string $name): void
    {
        $this->getJson("/admin/$name/data")->assertUnauthorized();
    }

    #[DataProvider('modules')]
    public function test_validasi_mengembalikan_422(string $name): void
    {
        $this->actingAs($this->admin())->postJson("/admin/$name", [])->assertStatus(422);
    }

    public function test_kategori_crud_dan_tidak_bisa_dihapus_bila_ada_berita(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/kategori', ['kategori_nama' => 'Olahraga'])->assertCreated();
        $kategori = Kategori::where('kategori_nama', 'Olahraga')->firstOrFail();
        $this->postJson('/admin/kategori', ['kategori_nama' => 'Olahraga'])->assertStatus(422);

        $this->putJson("/admin/kategori/{$kategori->id}", ['kategori_nama' => 'Sport'])->assertOk();
        $this->assertDatabaseHas('kategori', ['id' => $kategori->id, 'kategori_nama' => 'Sport']);

        DB::table('berita')->insert([
            'sekolah_id' => $this->sekolah->id,
            'berita_judul' => 'B', 'berita_isi' => 'I', 'kategori_id' => $kategori->id, 'author' => 'A',
            'berita_slug' => 'b', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->deleteJson("/admin/kategori/{$kategori->id}")->assertStatus(409);
        $this->getJson('/admin/kategori/data?'.$this->dt())->assertJsonPath('data.0.berita_count', 1);

        DB::table('berita')->delete();
        $this->deleteJson("/admin/kategori/{$kategori->id}")->assertOk();
        $this->assertDatabaseMissing('kategori', ['id' => $kategori->id]);
    }

    public function test_guru_boleh_mengelola_kategori(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));

        $this->postJson('/guru/kategori', ['kategori_nama' => 'Opini'])->assertCreated();
        $this->assertDatabaseHas('kategori', ['kategori_nama' => 'Opini']);
    }

    public function test_kelas_crud_dan_dilindungi_bila_berisi_siswa(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/kelas', ['nama_kelas' => 'VII Z'])->assertCreated();
        $kelas = Kelas::where('nama_kelas', 'VII Z')->firstOrFail();
        $this->putJson("/admin/kelas/{$kelas->id}", ['nama_kelas' => 'VII Y'])->assertOk();

        $user = User::factory()->create(['role' => 'siswa']);
        \App\Siswa::create(['nisn' => '1', 'nama_siswa' => 'S', 'agama' => 'Islam', 'jenis_kelamin' => 'Laki-Laki', 'tanggal_lahir' => '2010-01-01', 'user_id' => $user->id, 'kelas_id' => $kelas->id]);
        $this->getJson('/admin/kelas/data?'.$this->dt())->assertJsonPath('data.0.siswa_count', 1);
        $this->deleteJson("/admin/kelas/{$kelas->id}")->assertStatus(409);

        DB::table('siswa')->delete();
        $this->deleteJson("/admin/kelas/{$kelas->id}")->assertOk();
    }

    public function test_guru_hanya_melihat_kelas(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'guru']));

        $this->getJson('/guru/kelas/data?'.$this->dt())->assertOk();
        $this->postJson('/guru/kelas', ['nama_kelas' => 'X'])->assertStatus(405);
    }

    public function test_pengumuman_crud_memakai_primary_key_pengumuman_id(): void
    {
        $this->actingAs($admin = $this->admin());

        $this->postJson('/admin/pengumuman', ['pengumuman_judul' => 'Libur', 'pengumuman_deskripsi' => 'Besok libur'])->assertCreated();
        $p = Pengumuman::firstOrFail();
        $this->assertSame($admin->name, $p->pengumuman_author);

        $this->getJson('/admin/pengumuman/data?'.$this->dt())
            ->assertJsonPath('data.0.id', $p->pengumuman_id)
            ->assertJsonPath('data.0.pengumuman_judul', 'Libur');

        $this->putJson("/admin/pengumuman/{$p->pengumuman_id}", ['pengumuman_judul' => 'Libur Panjang', 'pengumuman_deskripsi' => 'x'])->assertOk();
        $this->assertDatabaseHas('pengumuman', ['pengumuman_id' => $p->pengumuman_id, 'pengumuman_judul' => 'Libur Panjang']);

        $this->deleteJson("/admin/pengumuman/{$p->pengumuman_id}")->assertOk();
        $this->assertDatabaseCount('pengumuman', 0);
    }

    public function test_agenda_crud_tanggal_dan_keterangan_default(): void
    {
        $this->actingAs($this->admin());
        $payload = [
            'agenda_nama' => 'Rapat', 'agenda_deskripsi' => 'Rapat guru', 'agenda_mulai' => '2026-03-01',
            'agenda_selesai' => '2026-03-02', 'agenda_tempat' => 'Aula', 'agenda_waktu' => '08.00',
        ];

        $this->postJson('/admin/agenda', ['agenda_selesai' => '2026-02-01'] + $payload)->assertStatus(422)
            ->assertJsonValidationErrors('agenda_selesai'); // selesai tidak boleh sebelum mulai

        $this->postJson('/admin/agenda', $payload)->assertCreated();
        $agenda = Agenda::firstOrFail();
        $this->assertSame('-', $agenda->agenda_keterangan);

        $this->getJson('/admin/agenda/data?'.$this->dt())
            ->assertJsonPath('data.0.agenda_mulai', '2026-03-01')
            ->assertJsonPath('data.0.id', $agenda->agenda_id);

        $this->putJson("/admin/agenda/{$agenda->agenda_id}", ['agenda_tempat' => 'Lapangan'] + $payload)->assertOk();
        $this->assertDatabaseHas('agenda', ['agenda_id' => $agenda->agenda_id, 'agenda_tempat' => 'Lapangan']);

        $this->deleteJson("/admin/agenda/{$agenda->agenda_id}")->assertOk();
        $this->assertDatabaseCount('agenda', 0);
    }

    public function test_agenda_data_lama_dengan_format_tanggal_teks_tetap_terbaca(): void
    {
        DB::table('agenda')->insert([
            'sekolah_id' => $this->sekolah->id,
            'agenda_nama' => 'Lama', 'agenda_deskripsi' => 'd', 'agenda_mulai' => '05/20/2021', 'agenda_selesai' => '05/21/2021',
            'agenda_tempat' => 't', 'agenda_waktu' => 'w', 'agenda_keterangan' => '-', 'agenda_author' => 'a',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())->getJson('/admin/agenda/data?'.$this->dt())
            ->assertJsonPath('data.0.agenda_mulai', '2021-05-20')
            ->assertJsonPath('data.0.agenda_selesai', '2021-05-21');
    }
}
