<?php

namespace Tests\Feature;

use App\ChatSession;
use App\Mail\ChatMail;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChatRiwayatTest extends TestCase
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

    private function sesi(array $extra = []): ChatSession
    {
        static $n = 0;
        $n++;
        $s = ChatSession::create($extra + [
            'token' => ChatSession::newToken(), 'nama' => 'Pengunjung '.$n, 'email' => "p{$n}@contoh.test", 'topik' => ChatSession::TOPIK[0],
            'status' => 'selesai', 'persetujuan' => true, 'closed_at' => now(),
        ]);
        $s->addMessage('visitor', 'Pertanyaan '.$n.' <i>miring</i>');

        return $s;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Ani']);
    }

    public function test_sesi_baru_punya_token_riwayat_berbeda_dari_token_sesi(): void
    {
        $s = $this->sesi();
        $this->assertSame(40, strlen($s->riwayat_token));
        $this->assertNotSame($s->token, $s->riwayat_token);
        $this->assertNotSame($this->sesi()->riwayat_token, $s->riwayat_token);
    }

    public function test_halaman_riwayat_lewat_tautan_unik_tanpa_login(): void
    {
        $s = $this->sesi();
        $s->addMessage('agent', 'Jawaban petugas', $this->admin()->id);

        $this->get(route('chat.riwayat', $s->riwayat_token))->assertOk()
            ->assertSee('Pertanyaan')->assertSee('Jawaban petugas')->assertSee('Unduh PDF')->assertSee('Penilaian layanan')
            ->assertSee('&lt;i&gt;miring&lt;/i&gt;', false)->assertDontSee('<i>miring</i>', false);

        $this->get('/chat/riwayat/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/chat/riwayat/pendek')->assertNotFound();
        $this->get('/chat/riwayat/'.$s->token)->assertNotFound(); // token sesi tidak berlaku sebagai token riwayat
    }

    public function test_pdf_percakapan_diunduh_dengan_kop_sekolah(): void
    {
        $s = $this->sesi(['rating' => 5, 'rating_komentar' => 'Mantap']);

        $res = $this->get(route('chat.export', $s->riwayat_token))->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        $this->assertStringContainsString('konsultasi-'.$s->id, $res->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', (string) $res->getContent(), 'isi: '.substr((string) $res->getContent(), 0, 120));

        $this->get(route('chat.export', str_repeat('b', 40)))->assertNotFound();
    }

    public function test_rating_hanya_setelah_selesai_satu_kali_dan_tervalidasi(): void
    {
        $aktif = $this->sesi(['status' => 'dilayani', 'closed_at' => null]);
        $this->postJson('/chat/rate', ['token' => $aktif->token, 'rating' => 5])->assertStatus(409);

        $s = $this->sesi();
        $this->postJson('/chat/rate', ['token' => $s->token, 'rating' => 6])->assertStatus(422)->assertJsonValidationErrors('rating');
        $this->postJson('/chat/rate', ['token' => $s->token, 'rating' => 0])->assertStatus(422);
        $this->postJson('/chat/rate', ['token' => $s->token])->assertStatus(422);
        $this->postJson('/chat/rate', ['rating' => 3])->assertNotFound();
        $this->postJson('/chat/rate', ['token' => str_repeat('x', 48), 'rating' => 3])->assertNotFound();

        $this->postJson('/chat/rate', ['token' => $s->token, 'rating' => 4, 'komentar' => '<b>Bagus</b> sekali'])->assertOk()->assertJsonPath('rating', 4);
        $this->assertSame(4, $s->fresh()->rating);
        $this->assertSame('Bagus sekali', $s->fresh()->rating_komentar);

        $this->postJson('/chat/rate', ['token' => $s->token, 'rating' => 1])->assertStatus(409);
        $this->assertSame(4, $s->fresh()->rating);
    }

    public function test_rating_lewat_tautan_riwayat_di_email(): void
    {
        $s = $this->sesi();

        $this->postJson('/chat/rate', ['riwayat' => $s->riwayat_token, 'rating' => 5])->assertOk();
        $this->assertSame(5, $s->fresh()->rating);
        $this->get(route('chat.riwayat', $s->riwayat_token))->assertOk()->assertSee('Terima kasih atas penilaian Anda', false);
    }

    public function test_poll_memberi_tautan_unduh_dan_status_rating(): void
    {
        $s = $this->sesi();

        $res = $this->getJson('/chat/poll?token='.$s->token)->assertOk();
        $this->assertSame(route('chat.export', $s->riwayat_token), $res->json('export_url'));
        $this->assertSame(route('chat.riwayat', $s->riwayat_token), $res->json('history_url'));
        $this->assertFalse($res->json('rated'));

        $s->update(['rating' => 3]);
        $this->assertTrue($this->getJson('/chat/poll?token='.$s->token)->json('rated'));
    }

    public function test_email_memuat_tautan_riwayat(): void
    {
        $s = $this->sesi();

        foreach (['dimulai', 'balasan', 'transkrip'] as $jenis) {
            $html = (new ChatMail($s, $jenis, ['isi' => 'x']))->render();
            $this->assertStringContainsString(route('chat.riwayat', $s->riwayat_token), $html, $jenis);
        }
    }

    public function test_admin_melihat_riwayat_dengan_filter(): void
    {
        $ani = $this->admin();
        $a = $this->sesi(['nama' => 'Budi Santoso', 'topik' => ChatSession::TOPIK[1], 'agent_id' => $ani->id]);
        $a->forceFill(['created_at' => now()->subDays(10)])->save();
        $b = $this->sesi(['nama' => 'Citra Lestari', 'topik' => ChatSession::TOPIK[2], 'status' => 'menunggu', 'closed_at' => null]);

        $url = '/admin/chat/riwayat';
        $this->actingAs($ani)->get($url)->assertOk()->assertSee('Budi Santoso')->assertSee('Citra Lestari')->assertSee('Export Excel');
        $this->get("$url?q=citra")->assertOk()->assertSee('Citra Lestari')->assertDontSee('Budi Santoso');
        $this->get("$url?topik=".urlencode(ChatSession::TOPIK[1]))->assertSee('Budi Santoso')->assertDontSee('Citra Lestari');
        $this->get("$url?agent_id={$ani->id}")->assertSee('Budi Santoso')->assertDontSee('Citra Lestari');
        $this->get("$url?status=menunggu")->assertSee('Citra Lestari')->assertDontSee('Budi Santoso');
        $this->get("$url?dari=".now()->subDays(3)->toDateString())->assertSee('Citra Lestari')->assertDontSee('Budi Santoso');
        $this->get("$url?sampai=".now()->subDays(5)->toDateString())->assertSee('Budi Santoso')->assertDontSee('Citra Lestari');
        $this->get("$url?status=ngawur")->assertSessionHasErrors('status');

        $this->get("$url/{$a->id}")->assertOk()->assertSee('Budi Santoso')->assertSee('Pertanyaan');
    }

    public function test_admin_mengunduh_pdf_dan_excel(): void
    {
        $ani = $this->admin();
        $s = $this->sesi(['agent_id' => $ani->id, 'rating' => 5, 'taken_at' => now()->subMinutes(8), 'closed_at' => now()]);

        $pdf = $this->actingAs($ani)->get("/admin/chat/{$s->id}/pdf")->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('content-type'));

        $xlsx = $this->actingAs($ani)->get('/admin/chat/riwayat/export?topik='.urlencode(ChatSession::TOPIK[0]))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));
        $this->assertStringContainsString('riwayat-konsultasi-', $xlsx->headers->get('content-disposition'));
        $file = $xlsx->baseResponse->getFile()->getPathname();
        $this->assertStringStartsWith('PK', file_get_contents($file, false, null, 0, 2)); // xlsx = zip

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file));
        $isi = $zip->getFromName('xl/sharedStrings.xml').$zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Pengunjung', $isi);
        $this->assertStringContainsString('Durasi layanan', $isi);
        $zip->close();
    }

    public function test_riwayat_admin_tertutup_untuk_selain_admin(): void
    {
        $s = $this->sesi();
        $this->get('/admin/chat/riwayat')->assertRedirect();

        foreach (['guru', 'siswa'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            foreach (['/admin/chat/riwayat', "/admin/chat/riwayat/{$s->id}", "/admin/chat/{$s->id}/pdf", '/admin/chat/riwayat/export'] as $url) {
                $this->actingAs($u)->get($url)->assertRedirect();
            }
        }
    }
}
