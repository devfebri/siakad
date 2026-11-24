<?php

namespace Tests\Feature;

use App\Mapel;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Contoh pola CRUD AJAX + paginasi server-side (dipakai ulang untuk menu lain). */
class MapelCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedMapel(int $count): void
    {
        $now = now();
        foreach (array_chunk(range(1, $count), 500) as $chunk) {
            DB::table('mapel')->insert(array_map(
                fn ($i) => ['sekolah_id' => $this->sekolah->id, 'mapel_nama' => sprintf('Mapel %05d', $i), 'created_at' => $now, 'updated_at' => $now],
                $chunk,
            ));
        }
    }

    private function dt(array $params = []): array
    {
        return array_merge([
            'draw' => 3, 'start' => 0, 'length' => 10,
            'columns' => [['data' => null], ['data' => 'mapel_nama'], ['data' => 'created_at']],
            'search' => ['value' => ''],
        ], $params);
    }

    public function test_hanya_satu_halaman_yang_dikirim_dari_ribuan_data(): void
    {
        $this->seedMapel(5000);

        $queries = 0;
        DB::listen(function ($q) use (&$queries) {
            if (str_contains($q->sql, 'mapel')) {
                $queries++;
            }
        });

        $json = $this->actingAs($this->admin())
            ->getJson('/admin/mapel/data?'.http_build_query($this->dt(['start' => 20, 'length' => 10])))
            ->assertOk()
            ->json();

        $this->assertSame(3, $json['draw']);
        $this->assertSame(5000, $json['recordsTotal']);
        $this->assertSame(5000, $json['recordsFiltered']);
        $this->assertCount(10, $json['data']);
        $this->assertLessThanOrEqual(2, $queries, 'Hanya count + satu query halaman.');
        // Urutan bawaan: terbaru dulu (id desc) -> halaman ke-3 berisi Mapel 04980..04971
        $this->assertSame('Mapel 04980', $json['data'][0]['mapel_nama']);
    }

    public function test_ukuran_halaman_dibatasi_100(): void
    {
        $this->seedMapel(300);

        $json = $this->actingAs($this->admin())
            ->getJson('/admin/mapel/data?'.http_build_query($this->dt(['length' => 5000])))
            ->json();

        $this->assertCount(100, $json['data']);
    }

    public function test_pencarian_dan_pengurutan(): void
    {
        $this->seedMapel(50);
        $params = $this->dt([
            'search' => ['value' => 'Mapel 0004'],
            'order' => [['column' => 1, 'dir' => 'asc']],
        ]);

        $json = $this->actingAs($this->admin())->getJson('/admin/mapel/data?'.http_build_query($params))->json();

        $this->assertSame(50, $json['recordsTotal']);
        $this->assertSame(10, $json['recordsFiltered']); // Mapel 00040..00049
        $this->assertSame('Mapel 00040', $json['data'][0]['mapel_nama']);
    }

    public function test_kolom_urutan_di_luar_whitelist_diabaikan(): void
    {
        $this->seedMapel(3);
        $params = $this->dt(['columns' => [['data' => 'password']], 'order' => [['column' => 0, 'dir' => 'asc']]]);

        $this->actingAs($this->admin())->getJson('/admin/mapel/data?'.http_build_query($params))->assertOk();
    }

    public function test_pencarian_dengan_karakter_wildcard_tidak_cocok_semua(): void
    {
        $this->seedMapel(5);
        $params = $this->dt(['search' => ['value' => '%']]);

        $json = $this->actingAs($this->admin())->getJson('/admin/mapel/data?'.http_build_query($params))->json();

        $this->assertSame(0, $json['recordsFiltered']);
    }

    public function test_tambah_ubah_hapus_lewat_ajax(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/admin/mapel', ['mapel_nama' => 'Fisika'])->assertCreated()->assertJsonStructure(['message']);
        $mapel = Mapel::where('mapel_nama', 'Fisika')->firstOrFail();

        $this->putJson("/admin/mapel/{$mapel->id}", ['mapel_nama' => 'Fisika Dasar'])->assertOk();
        $this->assertDatabaseHas('mapel', ['id' => $mapel->id, 'mapel_nama' => 'Fisika Dasar']);

        $this->deleteJson("/admin/mapel/{$mapel->id}")->assertOk();
        $this->assertDatabaseMissing('mapel', ['id' => $mapel->id]);
    }

    public function test_validasi_mengembalikan_422_json(): void
    {
        $this->actingAs($this->admin());
        Mapel::create(['mapel_nama' => 'Kimia']);

        $this->postJson('/admin/mapel', ['mapel_nama' => ''])->assertStatus(422)->assertJsonValidationErrors('mapel_nama');
        $this->postJson('/admin/mapel', ['mapel_nama' => 'Kimia'])->assertStatus(422)->assertJsonValidationErrors('mapel_nama');
    }

    public function test_mapel_yang_masih_dipakai_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->admin());
        $mapel = Mapel::create(['mapel_nama' => 'Biologi']);
        $kelas = \App\Kelas::create(['nama_kelas' => 'X']);
        DB::table('jadwal')->insert([
            'sekolah_id' => $this->sekolah->id,
            'kelas_id' => $kelas->id, 'mapel_id' => $mapel->id, 'hari' => 1, 'jam_mulai' => '07:00:00', 'jam_selesai' => '08:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->deleteJson("/admin/mapel/{$mapel->id}")->assertStatus(409);
        $this->assertDatabaseHas('mapel', ['id' => $mapel->id]);
    }

    public function test_guru_hanya_bisa_melihat_dan_tidak_bisa_mengubah(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $this->seedMapel(3);

        $this->actingAs($guru)->getJson('/guru/mapel/data?'.http_build_query($this->dt()))->assertOk();
        $this->postJson('/admin/mapel', ['mapel_nama' => 'X'])->assertForbidden();
        $this->assertDatabaseMissing('mapel', ['mapel_nama' => 'X']);
    }

    public function test_tamu_tidak_bisa_mengakses_data(): void
    {
        $this->getJson('/admin/mapel/data')->assertUnauthorized();
    }
}
