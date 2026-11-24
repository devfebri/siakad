<?php

namespace Tests\Feature;

use App\ChatAgentStatus;
use App\ChatSession;
use App\ChatSetting;
use App\Support\ChatQueue;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Tes antrian/petugas tidak bergantung jam sebenarnya: Rabu 10.00 (jam layanan) dan chatbot dimatikan.
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
        \App\ChatSetting::put('bot_aktif', '0');
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function petugas(string $nama, string $status = 'online', ?\DateTimeInterface $terakhir = null): User
    {
        $user = User::factory()->create(['role' => 'admin', 'name' => $nama]);
        ChatAgentStatus::create(['user_id' => $user->id, 'status' => $status, 'last_seen_at' => $terakhir ?? now()]);

        return $user;
    }

    private function sesi(array $extra = []): ChatSession
    {
        static $n = 0;
        $n++;

        return ChatSession::create($extra + [
            'token' => ChatSession::newToken(), 'nama' => 'Pengunjung '.$n, 'email' => "p{$n}@contoh.test", 'topik' => ChatSession::TOPIK[0],
            'status' => 'menunggu', 'persetujuan' => true,
        ]);
    }

    public function test_chat_baru_dibagi_ke_petugas_online_dengan_beban_paling_sedikit(): void
    {
        $a = $this->petugas('Ani');
        $b = $this->petugas('Budi');
        $this->sesi(['status' => 'dilayani', 'agent_id' => $a->id, 'taken_at' => now()]); // Ani sudah melayani 1

        $baru = $this->sesi();
        $this->assertSame(1, ChatQueue::dispatch());

        $baru->refresh();
        $this->assertSame('dilayani', $baru->status);
        $this->assertSame($b->id, $baru->agent_id);
        $this->assertStringContainsString('Budi', $baru->messages()->latest('id')->first()->body);
    }

    public function test_batas_chat_per_petugas_dihormati(): void
    {
        $this->petugas('Ani');
        ChatSetting::put('maks_chat', 2);

        foreach (range(1, 4) as $i) {
            $this->sesi();
        }

        $this->assertSame(2, ChatQueue::dispatch());
        $this->assertSame(2, ChatSession::where('status', 'menunggu')->count());
        $this->assertSame(0, ChatQueue::dispatch()); // penuh
    }

    public function test_batas_bawaan_adalah_tiga(): void
    {
        $this->petugas('Ani');
        foreach (range(1, 5) as $i) {
            $this->sesi();
        }

        $this->assertSame(3, ChatQueue::dispatch());
    }

    public function test_petugas_tanpa_aktivitas_dua_menit_dianggap_offline(): void
    {
        $this->petugas('Ani', 'online', now()->subMinutes(3));
        $this->petugas('Budi', 'istirahat');
        $this->petugas('Cici', 'offline');
        $segar = $this->petugas('Dewi', 'online', now()->subSeconds(90));

        $this->assertSame([$segar->id], ChatQueue::onlineAgents()->pluck('user_id')->all());

        $sesi = $this->sesi();
        ChatQueue::dispatch();
        $this->assertSame($segar->id, $sesi->fresh()->agent_id);
    }

    public function test_tanpa_petugas_online_chat_tetap_menunggu(): void
    {
        $this->petugas('Ani', 'offline');
        $sesi = $this->sesi();

        $this->assertSame(0, ChatQueue::dispatch());
        $this->assertSame('menunggu', $sesi->fresh()->status);
    }

    public function test_posisi_dan_estimasi_waktu_tunggu(): void
    {
        $s1 = $this->sesi();
        $s2 = $this->sesi();
        $s3 = $this->sesi();

        // tanpa data: 5 menit/chat, tanpa petugas online dianggap 1 -> posisi 3 = 15 menit
        $info = ChatQueue::info($s3);
        $this->assertSame(3, $info['position']);
        $this->assertSame(15, $info['estimate_min']);
        $this->assertSame(0, $info['agents_online']);
        $this->assertSame(1, ChatQueue::info($s1)['position']);
        $this->assertSame(2, ChatQueue::info($s2)['position']);

        // data 7 hari terakhir: dua sesi selesai masing-masing 10 menit -> rata-rata 10; 2 petugas online (beban penuh agar antrian tetap)
        foreach ([1, 2] as $i) {
            $this->sesi(['status' => 'selesai', 'taken_at' => now()->subHours($i), 'closed_at' => now()->subHours($i)->addMinutes(10)]);
        }
        $this->assertEqualsWithDelta(10.0, ChatQueue::rataDurasiMenit(), 0.01);

        $this->petugas('Ani');
        $this->petugas('Budi');
        ChatSetting::put('maks_chat', 1);
        $this->sesi(['status' => 'dilayani', 'agent_id' => User::where('name', 'Ani')->value('id'), 'taken_at' => now()]);
        $this->sesi(['status' => 'dilayani', 'agent_id' => User::where('name', 'Budi')->value('id'), 'taken_at' => now()]);

        // posisi 3 x 10 menit / 2 petugas = 15
        $this->assertSame(15, ChatQueue::info($s3->fresh())['estimate_min']);
    }

    public function test_sesi_lama_tidak_masuk_rata_rata_dan_estimasi_minimal_satu_menit(): void
    {
        $this->sesi(['status' => 'selesai', 'taken_at' => now()->subDays(9), 'closed_at' => now()->subDays(9)->addMinutes(40)]);
        $this->assertSame(5.0, ChatQueue::rataDurasiMenit());
    }

    public function test_tawaran_balas_email_setelah_sepuluh_menit(): void
    {
        $sesi = $this->sesi();
        $this->assertFalse(ChatQueue::info($sesi)['offer_email']);

        $sesi->forceFill(['created_at' => now()->subMinutes(11)])->save();
        $this->assertTrue(ChatQueue::info($sesi->fresh())['offer_email']);

        $res = $this->postJson('/chat/leave', ['token' => $sesi->token])->assertOk();
        $this->assertTrue($res->json('balas_email'));
        $this->assertFalse($res->json('queue.offer_email')); // tawaran tidak muncul lagi
        $this->assertTrue($sesi->fresh()->balas_email);

        // tidak ikut pembagian otomatis
        $this->petugas('Ani');
        $this->assertSame(0, ChatQueue::dispatch());
    }

    public function test_pengunjung_melihat_antrian_dan_status_petugas_di_poll(): void
    {
        $this->sesi(); // pengunjung lain di depan
        $token = $this->postJson('/chat/start', ['nama' => 'Dini', 'email' => 'd@contoh.test', 'topik' => ChatSession::TOPIK[0], 'persetujuan' => 1])->assertCreated();
        $this->assertSame(2, $token->json('queue.position'));

        $poll = $this->getJson('/chat/poll?token='.$token->json('token'))->assertOk();
        $this->assertSame(2, $poll->json('queue.position'));
        $this->assertSame(0, $poll->json('agents_online'));

        $this->petugas('Ani');
        $this->getJson('/chat/status')->assertOk()->assertJsonPath('agents_online', 1);
        $poll = $this->getJson('/chat/poll?token='.$token->json('token'))->assertOk();
        $this->assertNull($poll->json('queue')); // sudah dilayani
        $this->assertSame('dilayani', $poll->json('status'));
        $this->assertSame('Ani', $poll->json('agent'));
    }

    public function test_panel_petugas_status_ambil_alihkan_dan_tutup(): void
    {
        $ani = User::factory()->create(['role' => 'admin', 'name' => 'Ani']);
        $budi = User::factory()->create(['role' => 'admin', 'name' => 'Budi']);
        $sesi = $this->sesi();

        // status offline -> tidak dibagi otomatis; online -> kena bagi
        $this->actingAs($ani)->postJson('/admin/chat/status', ['status' => 'offline'])->assertOk();
        $this->actingAs($ani)->postJson('/admin/chat/status', ['status' => 'rusak'])->assertStatus(422);
        $antrian = $this->actingAs($ani)->getJson('/admin/chat/queue')->assertOk();
        $this->assertCount(1, $antrian->json('waiting'));
        $this->assertSame('offline', $antrian->json('me.status'));

        // ambil manual
        $this->actingAs($ani)->postJson("/admin/chat/{$sesi->id}/take")->assertOk();
        $this->assertSame($ani->id, $sesi->fresh()->agent_id);
        $this->actingAs($budi)->postJson("/admin/chat/{$sesi->id}/take")->assertStatus(409);
        $this->assertSame(1, count($this->actingAs($ani)->getJson('/admin/chat/queue')->json('mine')));

        // petugas lain tidak boleh membalas, mengalihkan, atau menutup
        $this->actingAs($budi)->postJson("/admin/chat/{$sesi->id}/reply", ['body' => 'x'])->assertForbidden();
        $this->actingAs($budi)->postJson("/admin/chat/{$sesi->id}/close")->assertForbidden();
        $this->actingAs($budi)->postJson("/admin/chat/{$sesi->id}/transfer", ['agent_id' => $budi->id])->assertForbidden();

        // alihkan ke Budi
        $this->actingAs($ani)->postJson("/admin/chat/{$sesi->id}/transfer", ['agent_id' => $ani->id])->assertStatus(422);
        $this->actingAs($ani)->postJson("/admin/chat/{$sesi->id}/transfer", ['agent_id' => $budi->id])->assertOk()->assertJsonPath('agent', 'Budi');
        $this->assertSame($budi->id, $sesi->fresh()->agent_id);
        $this->actingAs($ani)->postJson("/admin/chat/{$sesi->id}/reply", ['body' => 'x'])->assertForbidden();
        $this->actingAs($budi)->postJson("/admin/chat/{$sesi->id}/reply", ['body' => 'Halo dari Budi'])->assertCreated();

        // tutup -> slot kosong diisi dari antrian
        $this->actingAs($budi)->postJson('/admin/chat/status', ['status' => 'online'])->assertOk();
        $berikut = $this->sesi();
        $this->actingAs($budi)->postJson("/admin/chat/{$sesi->id}/close")->assertOk();
        $this->assertSame('selesai', $sesi->fresh()->status);
        $this->assertSame($budi->id, $berikut->fresh()->agent_id);
    }

    public function test_membalas_chat_di_antrian_otomatis_mengambilnya(): void
    {
        $ani = User::factory()->create(['role' => 'admin']);
        $sesi = $this->sesi();

        $this->actingAs($ani)->postJson("/admin/chat/{$sesi->id}/reply", ['body' => 'Halo'])->assertCreated();
        $this->assertSame('dilayani', $sesi->fresh()->status);
        $this->assertSame($ani->id, $sesi->fresh()->agent_id);
    }

    public function test_polling_petugas_menjadi_tanda_aktif_dan_balasan_cepat_tersedia(): void
    {
        $ani = $this->petugas('Ani', 'online', now()->subMinutes(5));
        $this->assertCount(0, ChatQueue::onlineAgents());

        $this->actingAs($ani)->getJson('/admin/chat/queue')->assertOk();
        $this->assertCount(1, ChatQueue::onlineAgents());

        $this->actingAs($ani)->get('/admin/chat')->assertOk()->assertSee('Jam layanan')->assertSee('Status saya');
    }
}
