<?php

namespace Tests\Feature;

use App\ChatSession;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
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

    private function mulai(array $extra = []): string
    {
        $res = $this->postJson('/chat/start', $extra + [
            'nama' => 'Budi', 'email' => 'budi@contoh.test', 'topik' => ChatSession::TOPIK[1], 'persetujuan' => 1,
        ])->assertCreated();

        return $res->json('token');
    }

    public function test_widget_tampil_di_halaman_publik_tapi_tidak_di_admin(): void
    {
        $this->get('/')->assertOk()->assertSee('id="chat-widget"', false)->assertSee('Konsultasi');
        $this->actingAs(User::factory()->create(['role' => 'guru']))->get('/guru/dashboard')->assertOk()->assertDontSee('id="chat-widget"', false);
    }

    public function test_memulai_sesi_membuat_pesan_sambutan(): void
    {
        $res = $this->postJson('/chat/start', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'whatsapp' => '0812-3456-7890', 'topik' => ChatSession::TOPIK[0], 'persetujuan' => 1])->assertCreated();

        $this->assertSame(48, strlen($res->json('token')));
        $this->assertSame('menunggu', $res->json('status'));
        $this->assertSame('system', $res->json('messages.0.sender'));
        $this->assertDatabaseHas('chat_sessions', ['email' => 'budi@contoh.test', 'persetujuan' => 1]);
    }

    public function test_validasi_form_mulai(): void
    {
        $this->postJson('/chat/start', [])->assertStatus(422)->assertJsonValidationErrors(['nama', 'email', 'topik', 'persetujuan']);
        $this->postJson('/chat/start', ['nama' => 'B', 'email' => 'x@y.test', 'topik' => 'Topik Palsu', 'persetujuan' => 1])->assertStatus(422)->assertJsonValidationErrors(['nama', 'topik']);
        $this->postJson('/chat/start', ['nama' => 'Budi', 'email' => 'x@y.test', 'topik' => ChatSession::TOPIK[0], 'persetujuan' => 0])->assertStatus(422)->assertJsonValidationErrors('persetujuan');
        $this->postJson('/chat/start', ['nama' => 'Budi', 'email' => 'x@y.test', 'whatsapp' => 'abc', 'topik' => ChatSession::TOPIK[0], 'persetujuan' => 1])->assertStatus(422)->assertJsonValidationErrors('whatsapp');
    }

    public function test_kirim_dan_poll_hanya_mengembalikan_pesan_baru(): void
    {
        $token = $this->mulai();

        $id1 = $this->postJson('/chat/send', ['token' => $token, 'body' => 'Halo, tanya PPDB'])->assertCreated()->json('message.id');
        $this->postJson('/chat/send', ['token' => $token, 'body' => 'Kapan dibuka?'])->assertCreated();

        $semua = $this->getJson("/chat/poll?token=$token&last_id=0")->assertOk();
        $this->assertCount(3, $semua->json('messages')); // sambutan + 2 pesan

        $baru = $this->getJson("/chat/poll?token=$token&last_id=$id1")->assertOk();
        $this->assertCount(1, $baru->json('messages'));
        $this->assertSame('Kapan dibuka?', $baru->json('messages.0.body'));
    }

    public function test_pesan_kosong_terlalu_panjang_dan_token_salah_ditolak(): void
    {
        $token = $this->mulai();

        $this->postJson('/chat/send', ['token' => $token, 'body' => '   '])->assertStatus(422);
        $this->postJson('/chat/send', ['token' => $token, 'body' => str_repeat('a', 1001)])->assertStatus(422);
        $this->postJson('/chat/send', ['token' => str_repeat('x', 48), 'body' => 'hai'])->assertNotFound();
        $this->postJson('/chat/send', ['body' => 'hai'])->assertNotFound();
        $this->getJson('/chat/poll?token=pendek')->assertNotFound();
    }

    public function test_html_disimpan_apa_adanya_dan_tidak_dieksekusi_di_api(): void
    {
        $token = $this->mulai();
        $this->postJson('/chat/send', ['token' => $token, 'body' => '<script>alert(1)</script>'])->assertCreated();

        // API mengembalikan teks mentah; widget menampilkannya dengan .text() (lihat chat-widget.js), halaman admin juga.
        $this->assertSame('<script>alert(1)</script>', $this->getJson("/chat/poll?token=$token")->json('messages.1.body'));
    }

    public function test_balasan_petugas_diterima_pengunjung_dan_ditandai_dibaca(): void
    {
        $token = $this->mulai();
        $kirim = $this->postJson('/chat/send', ['token' => $token, 'body' => 'Tanya jadwal'])->json('message');
        $session = ChatSession::firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Bu Ani']);

        // admin membuka percakapan -> pesan pengunjung terbaca
        $this->actingAs($admin)->getJson("/admin/chat/{$session->id}/poll")->assertOk()->assertJsonCount(2, 'messages');
        $this->actingAs($admin)->postJson("/admin/chat/{$session->id}/reply", ['body' => 'Silakan, ada yang bisa dibantu?'])->assertCreated();

        $this->assertSame('dilayani', $session->fresh()->status);
        $this->assertSame($admin->id, $session->fresh()->agent_id);

        auth()->logout();
        $belumBuka = $this->getJson("/chat/poll?token=$token&last_id={$kirim['id']}&open=0")->assertOk();
        $this->assertSame(1, $belumBuka->json('unread'));
        $this->assertSame('Bu Ani', $belumBuka->json('messages.0.name'));
        $this->assertSame('Bu Ani', $belumBuka->json('agent'));
        $this->assertSame($kirim['id'], $belumBuka->json('read_upto'));

        $dibuka = $this->getJson("/chat/poll?token=$token&last_id={$kirim['id']}&open=1")->assertOk();
        $this->assertSame(0, $this->getJson("/chat/poll?token=$token&last_id=999")->json('unread'));
        $this->assertCount(1, $dibuka->json('messages'));
    }

    public function test_indikator_mengetik_dua_arah(): void
    {
        $token = $this->mulai();
        $session = ChatSession::firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($this->getJson("/chat/poll?token=$token")->json('agent_typing'));
        $this->actingAs($admin)->postJson("/admin/chat/{$session->id}/typing")->assertOk();
        $this->assertTrue($this->getJson("/chat/poll?token=$token")->json('agent_typing'));

        $this->postJson('/chat/typing', ['token' => $token])->assertOk();
        $this->assertTrue($this->actingAs($admin)->getJson("/admin/chat/{$session->id}/poll")->json('visitor_typing'));
    }

    public function test_sesi_selesai_tidak_menerima_pesan(): void
    {
        $token = $this->mulai();
        $this->postJson('/chat/end', ['token' => $token])->assertOk();

        $this->postJson('/chat/send', ['token' => $token, 'body' => 'masih bisa?'])->assertStatus(409);
        $poll = $this->getJson("/chat/poll?token=$token")->assertOk();
        $this->assertTrue($poll->json('closed'));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/admin/chat/'.ChatSession::first()->id.'/reply', ['body' => 'halo'])->assertStatus(409);
    }

    public function test_petugas_menutup_sesi(): void
    {
        $token = $this->mulai();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/admin/chat/'.ChatSession::first()->id.'/close')->assertOk();
        auth()->logout();
        $this->assertTrue($this->getJson("/chat/poll?token=$token")->json('closed'));
    }

    public function test_panel_admin_hanya_untuk_admin(): void
    {
        $this->mulai();
        $session = ChatSession::firstOrFail();

        $this->get('/admin/chat')->assertRedirect();
        $this->getJson("/admin/chat/{$session->id}/poll")->assertUnauthorized();
        foreach (['guru', 'siswa'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/admin/chat/queue')->assertForbidden();
            $this->actingAs($user)->postJson("/admin/chat/{$session->id}/reply", ['body' => 'x'])->assertForbidden();
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/chat')->assertOk()->assertSee('Konsultasi Online');
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('Konsultasi Online');
        $daftar = $this->actingAs($admin)->getJson('/admin/chat/queue')->assertOk();
        $this->assertSame('Budi', $daftar->json('waiting.0.nama'));
        $this->assertSame(0, $daftar->json('waiting.0.belum_dibaca'));
    }
}
