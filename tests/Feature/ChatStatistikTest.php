<?php

namespace Tests\Feature;

use App\ChatAttachment;
use App\ChatMessage;
use App\ChatQuickReply;
use App\ChatSession;
use App\ChatSetting;
use App\Support\ChatQueue;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatStatistikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
        ChatSetting::put('bot_aktif', '0');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(string $nama = 'Ani'): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => $nama]);
    }

    private function sesi(array $extra = [], ?Carbon $dibuat = null): ChatSession
    {
        static $n = 0;
        $n++;
        $s = ChatSession::create($extra + ['token' => ChatSession::newToken(), 'nama' => 'P'.$n, 'email' => "p{$n}@c.test", 'topik' => ChatSession::TOPIK[0], 'status' => 'selesai', 'persetujuan' => true]);
        if ($dibuat) {
            DB::table('chat_sessions')->where('id', $s->id)->update(['created_at' => $dibuat, 'updated_at' => $dibuat]);
        }

        return $s->fresh();
    }

    // ------------------------------------------------------------ statistik

    public function test_statistik_dihitung_dari_data_percakapan(): void
    {
        $ani = $this->admin('Ani');
        $budi = $this->admin('Budi');
        $t = now()->subDay();

        // tunggu 4 mnt, durasi 10 mnt, rating 5
        $this->sesi(['agent_id' => $ani->id, 'taken_at' => $t->copy()->addMinutes(4), 'closed_at' => $t->copy()->addMinutes(14), 'rating' => 5], $t);
        // tunggu 8 mnt, durasi 20 mnt, rating 4
        $this->sesi(['agent_id' => $budi->id, 'taken_at' => $t->copy()->addMinutes(8), 'closed_at' => $t->copy()->addMinutes(28), 'rating' => 4, 'topik' => ChatSession::TOPIK[1]], $t);
        // dialihkan ke email, diambil keesokan harinya: tidak boleh merusak rata-rata waktu tunggu
        $this->sesi(['agent_id' => $ani->id, 'balas_email' => true, 'taken_at' => $t->copy()->addHours(20), 'closed_at' => $t->copy()->addHours(21)], $t);
        // masih menunggu
        $this->sesi(['status' => 'menunggu'], now());

        $res = $this->actingAs($ani)->get('/admin/chat/statistik')->assertOk()->assertSee('Statistik layanan');
        $r = $res->viewData('ringkas');

        $this->assertSame(4, $r['total']);
        $this->assertSame(3, $r['selesai']);
        $this->assertSame(1, $r['diteruskan']);
        $this->assertEquals(6.0, $r['tunggu']);
        $this->assertEqualsWithDelta(30.0, $r['durasi'], 0.1); // (10 + 20 + 60) / 3
        $this->assertEquals(4.5, $r['rating']);
        $this->assertSame(2, $r['jumlah_rating']);

        $this->assertSame(3, $res->viewData('topik')[ChatSession::TOPIK[0]]);
        $this->assertSame(1, $res->viewData('topik')[ChatSession::TOPIK[1]]);
        $this->assertSame(['1' => 0, '2' => 0, '3' => 0, '4' => 1, '5' => 1], $res->viewData('distribusiRating')->all());

        $perHari = $res->viewData('perHari');
        $this->assertSame(30, count($perHari));
        $this->assertSame(3, $perHari[$t->copy()->setTimezone('Asia/Jakarta')->toDateString()]);
        $this->assertSame(1, $perHari['2026-11-04']);

        $petugas = $res->viewData('petugas')->keyBy('nama');
        $this->assertSame(2, $petugas['Ani']['jumlah']);
        $this->assertSame(1, $petugas['Budi']['jumlah']);
        $this->assertEquals(20.0, $petugas['Budi']['durasi']);
        $this->assertEquals(5.0, $petugas['Ani']['rating']);
        $res->assertSee('Kinerja Petugas')->assertSee('Budi');
    }

    public function test_filter_tanggal_dan_batas_rentang(): void
    {
        $this->sesi([], now()->subDays(40));
        $this->sesi([], now()->subDays(2));
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/chat/statistik')->assertOk();
        $this->assertSame(1, $this->get('/admin/chat/statistik')->viewData('ringkas')['total']);

        $dari = now()->subDays(60)->toDateString();
        $this->assertSame(2, $this->get("/admin/chat/statistik?dari=$dari")->viewData('ringkas')['total']);
        $this->get('/admin/chat/statistik?dari=2025-01-01&sampai=2026-11-04')->assertStatus(422);
        $this->get('/admin/chat/statistik?dari=2026-11-04&sampai=2026-11-01')->assertSessionHasErrors('sampai');
    }

    public function test_statistik_kosong_tidak_error(): void
    {
        $this->actingAs($this->admin())->get('/admin/chat/statistik')->assertOk()->assertSee('Belum ada chat yang dilayani');
    }

    public function test_statistik_dan_pengaturan_hanya_untuk_admin(): void
    {
        $this->get('/admin/chat/statistik')->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/admin/chat/statistik')->assertRedirect();
    }

    // ------------------------------------------------------------ pembersihan otomatis

    private function pesan(ChatSession $s, Carbon $waktu, string $body = 'isi'): ChatMessage
    {
        $m = $s->addMessage('visitor', $body);
        DB::table('chat_messages')->where('id', $m->id)->update(['created_at' => $waktu, 'updated_at' => $waktu]);

        return $m->fresh();
    }

    public function test_riwayat_lebih_dari_satu_tahun_dihapus_beserta_lampiran(): void
    {
        $lama = $this->sesi([], now()->subDays(400));
        $m = $this->pesan($lama, now()->subDays(400));
        Storage::disk('local')->put("chat/{$lama->id}/berkas.pdf", 'x');
        ChatAttachment::create(['message_id' => $m->id, 'path' => "chat/{$lama->id}/berkas.pdf", 'nama_asli' => 'berkas.pdf', 'mime' => 'application/pdf', 'ukuran' => 1]);

        $baru = $this->sesi([], now()->subDays(30));
        $this->pesan($baru, now()->subDays(30));
        Storage::disk('local')->put("chat/{$baru->id}/aman.pdf", 'x');

        $this->artisan('chat:bersihkan')->expectsOutputToContain('1 riwayat dihapus')->assertSuccessful();

        $this->assertDatabaseMissing('chat_sessions', ['id' => $lama->id]);
        $this->assertDatabaseMissing('chat_messages', ['session_id' => $lama->id]);
        $this->assertSame(0, ChatAttachment::count());
        Storage::disk('local')->assertMissing("chat/{$lama->id}/berkas.pdf");
        $this->assertDatabaseHas('chat_sessions', ['id' => $baru->id]);
        Storage::disk('local')->assertExists("chat/{$baru->id}/aman.pdf");
    }

    public function test_masa_simpan_bisa_diatur_dan_dimatikan(): void
    {
        $s = $this->sesi([], now()->subDays(100));

        ChatSetting::put('retensi_hari', '90');
        $this->assertSame(90, ChatQueue::retensiHari());
        $this->artisan('chat:bersihkan --kering')->expectsOutputToContain('[simulasi] 0 chat ditutup otomatis, 1 riwayat dihapus')->assertSuccessful();
        $this->assertDatabaseHas('chat_sessions', ['id' => $s->id]); // simulasi tidak menghapus

        ChatSetting::put('retensi_hari', '0');
        $this->artisan('chat:bersihkan')->assertSuccessful();
        $this->assertDatabaseHas('chat_sessions', ['id' => $s->id]);

        ChatSetting::put('retensi_hari', '90');
        $this->artisan('chat:bersihkan')->assertSuccessful();
        $this->assertDatabaseMissing('chat_sessions', ['id' => $s->id]);
    }

    public function test_chat_menggantung_ditutup_otomatis(): void
    {
        $menggantung = $this->sesi(['status' => 'dilayani'], now()->subHours(30));
        $this->pesan($menggantung, now()->subHours(30));
        $aktif = $this->sesi(['status' => 'dilayani'], now()->subHours(30));
        $this->pesan($aktif, now()->subHours(2)); // masih ada pesan baru-baru ini
        DB::table('chat_sessions')->where('id', $aktif->id)->update(['updated_at' => now()->subHours(2)]);

        $this->artisan('chat:bersihkan')->expectsOutputToContain('1 chat ditutup otomatis')->assertSuccessful();

        $this->assertSame('selesai', $menggantung->fresh()->status);
        $this->assertNotNull($menggantung->fresh()->closed_at);
        $this->assertStringContainsString('ditutup otomatis', $menggantung->messages()->latest('id')->first()->body);
        $this->assertSame('dilayani', $aktif->fresh()->status);

        ChatSetting::put('tutup_otomatis_jam', '0');
        $lagi = $this->sesi(['status' => 'dilayani'], now()->subHours(100));
        $this->artisan('chat:bersihkan')->assertSuccessful();
        $this->assertSame('dilayani', $lagi->fresh()->status);
    }

    public function test_perintah_dijadwalkan(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('chat:bersihkan')->assertSuccessful();
    }

    // ------------------------------------------------------------ pengaturan tambahan

    public function test_batas_chat_masa_simpan_dan_tutup_otomatis_disimpan_dan_divalidasi(): void
    {
        $admin = $this->admin();
        $ok = ['zona' => 'Asia/Jakarta', 'jam_buka' => '08:00', 'jam_tutup' => '16:00', 'hari' => [1, 2, 3], 'maks_chat' => 5, 'retensi_hari' => 180, 'tutup_otomatis_jam' => 12];

        $this->actingAs($admin)->putJson('/admin/chat/pengaturan', $ok)->assertOk();
        $this->assertSame([5, 180, 12], [ChatQueue::maksChat(), ChatQueue::retensiHari(), ChatQueue::tutupOtomatisJam()]);
        $this->get('/admin/chat/pengaturan')->assertOk()->assertSee('Maks. chat / petugas')->assertSee('value="180"', false);

        $this->putJson('/admin/chat/pengaturan', ['maks_chat' => 0] + $ok)->assertStatus(422)->assertJsonValidationErrors('maks_chat');
        $this->putJson('/admin/chat/pengaturan', ['maks_chat' => 21] + $ok)->assertStatus(422);
        $this->putJson('/admin/chat/pengaturan', ['retensi_hari' => -1] + $ok)->assertStatus(422)->assertJsonValidationErrors('retensi_hari');
        $this->putJson('/admin/chat/pengaturan', ['tutup_otomatis_jam' => 'abc'] + $ok)->assertStatus(422);
        $this->putJson('/admin/chat/pengaturan', ['retensi_hari' => 0, 'tutup_otomatis_jam' => 0] + $ok)->assertOk();
    }

    public function test_template_balasan_cepat_dikelola_dan_dipakai_panel_petugas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->assertSame(5, ChatQuickReply::count(), 'template bawaan dari migration');

        $this->postJson('/admin/chat/balasan', ['judul' => 'Terima kasih', 'isi' => 'Terima kasih sudah menghubungi kami.', 'urutan' => 9, 'aktif' => 1])->assertCreated();
        $baru = ChatQuickReply::where('judul', 'Terima kasih')->firstOrFail();

        $this->putJson("/admin/chat/balasan/{$baru->id}", ['judul' => 'Terima kasih!', 'isi' => 'Sama-sama.'])->assertOk(); // aktif tidak dikirim = nonaktif
        $this->assertFalse($baru->fresh()->aktif);

        $halaman = $this->get('/admin/chat')->assertOk();
        $judul = collect($halaman->viewData('balasanCepat'))->pluck('judul');
        $this->assertCount(5, $judul);
        $this->assertNotContains('Terima kasih!', $judul->all());

        $this->postJson('/admin/chat/balasan', ['judul' => '', 'isi' => 'x'])->assertStatus(422)->assertJsonValidationErrors('judul');
        $this->deleteJson("/admin/chat/balasan/{$baru->id}")->assertOk();
        $this->assertDatabaseMissing('chat_quick_replies', ['id' => $baru->id]);

        $data = $this->getJson('/admin/chat/balasan/data?draw=1&start=0&length=10&search[value]=&columns[0][data]=&order[0][column]=0&order[0][dir]=asc')->assertOk();
        $this->assertSame(5, $data->json('recordsTotal'));
        $this->get('/admin/chat/pengaturan')->assertOk()->assertSee('Template Balasan Cepat');
    }
}
