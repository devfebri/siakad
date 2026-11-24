<?php

namespace Tests\Feature;

use App\ChatAgentStatus;
use App\ChatSession;
use App\Mail\ChatMail;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Tes antrian/petugas tidak bergantung jam sebenarnya: Rabu 10.00 (jam layanan) dan chatbot dimatikan.
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
        \App\ChatSetting::put('bot_aktif', '0');
        Mail::fake();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function mulai(): string
    {
        return $this->postJson('/chat/start', ['nama' => 'Budi', 'email' => 'budi@contoh.test', 'topik' => ChatSession::TOPIK[0], 'persetujuan' => 1])->assertCreated()->json('token');
    }

    private function admin(string $nama = 'Ani', bool $online = false): User
    {
        $u = User::factory()->create(['role' => 'admin', 'name' => $nama]);
        if ($online) {
            ChatAgentStatus::create(['user_id' => $u->id, 'status' => 'online', 'last_seen_at' => now()]);
        }

        return $u;
    }

    private function antri(string $jenis, ?string $ke = null)
    {
        return Mail::queued(ChatMail::class, fn (ChatMail $m) => $m->jenis === $jenis && ($ke === null || $m->hasTo($ke)));
    }

    // ------------------------------------------------------------ email

    public function test_email_konfirmasi_dikirim_saat_chat_dimulai(): void
    {
        $this->mulai();

        $this->assertCount(1, $this->antri('dimulai', 'budi@contoh.test'));
    }

    public function test_balasan_petugas_dikirim_ke_email_hanya_saat_pengunjung_offline(): void
    {
        $token = $this->mulai();
        $session = ChatSession::firstOrFail();
        $ani = $this->admin();

        // pengunjung baru saja aktif: tidak perlu email
        $this->actingAs($ani)->postJson("/admin/chat/{$session->id}/reply", ['body' => 'Halo'])->assertCreated();
        $this->assertCount(0, $this->antri('balasan'));

        // pengunjung sudah lebih dari 2 menit tidak memolling: kirim email
        $session->forceFill(['last_visitor_at' => now()->subMinutes(3)])->save();
        $this->actingAs($ani)->postJson("/admin/chat/{$session->id}/reply", ['body' => 'Apakah masih di sana?'])->assertCreated();
        $this->assertCount(1, $this->antri('balasan', 'budi@contoh.test'));

        // balasan beruntun dalam 5 menit tidak dikirim lagi
        $this->actingAs($ani)->postJson("/admin/chat/{$session->id}/reply", ['body' => 'Satu lagi'])->assertCreated();
        $this->assertCount(1, $this->antri('balasan'));

        // lewat 5 menit: kirim lagi
        $session->forceFill(['email_balasan_at' => now()->subMinutes(6)])->save();
        $this->actingAs($ani)->postJson("/admin/chat/{$session->id}/reply", ['body' => 'Dan ini'])->assertCreated();
        $this->assertCount(2, $this->antri('balasan'));
    }

    public function test_pengunjung_yang_memilih_balas_email_selalu_diemail(): void
    {
        $this->mulai();
        $session = ChatSession::firstOrFail();
        $session->update(['balas_email' => true]); // last_visitor_at masih baru, tetapi pilihannya email

        $this->actingAs($this->admin())->postJson("/admin/chat/{$session->id}/reply", ['body' => 'Balasan lewat email'])->assertCreated();

        $this->assertCount(1, $this->antri('balasan'));
    }

    public function test_petugas_diemail_saat_pesan_masuk_tanpa_petugas_online_sekali_per_sesi(): void
    {
        $this->admin('Ani');
        $this->admin('Budi');
        $token = $this->mulai();

        $this->postJson('/chat/send', ['token' => $token, 'body' => 'Ada orang?'])->assertCreated();
        $this->postJson('/chat/send', ['token' => $token, 'body' => 'Halo?'])->assertCreated();

        $this->assertCount(2, $this->antri('masuk')); // 2 admin x 1 kali
    }

    public function test_petugas_tidak_diemail_bila_ada_petugas_online(): void
    {
        $this->admin('Ani', online: true);
        $token = $this->mulai();

        $this->postJson('/chat/send', ['token' => $token, 'body' => 'Halo'])->assertCreated();

        $this->assertCount(0, $this->antri('masuk'));
    }

    public function test_transkrip_dikirim_setelah_sesi_selesai_bila_pengunjung_menulis(): void
    {
        $token = $this->mulai();
        $this->postJson('/chat/end', ['token' => $token])->assertOk();
        $this->assertCount(0, $this->antri('transkrip'), 'tanpa pesan pengunjung tidak ada transkrip');

        $token2 = $this->mulai();
        $this->postJson('/chat/send', ['token' => $token2, 'body' => 'Tanya PPDB'])->assertCreated();
        $this->postJson('/chat/end', ['token' => $token2])->assertOk();
        $this->assertCount(1, $this->antri('transkrip', 'budi@contoh.test'));

        // ditutup petugas juga mengirim transkrip
        $token3 = $this->mulai();
        $this->postJson('/chat/send', ['token' => $token3, 'body' => 'Satu pertanyaan'])->assertCreated();
        $this->actingAs($this->admin())->postJson('/admin/chat/'.ChatSession::where('token', $token3)->value('id').'/close')->assertOk();
        $this->assertCount(2, $this->antri('transkrip'));
    }

    public function test_semua_jenis_email_dirender_dengan_tema_sekolah(): void
    {
        $token = $this->mulai();
        $this->postJson('/chat/send', ['token' => $token, 'body' => 'Pertanyaan <b>uji</b>'])->assertCreated();
        $session = ChatSession::firstOrFail();

        foreach (ChatMail::JENIS as $jenis) {
            $html = (new ChatMail($session, $jenis, ['isi' => 'Isi <script>x</script>', 'petugas' => 'Bu Ani']))->render();
            $this->assertStringContainsString(config('school.name'), $html, $jenis);
            $this->assertStringContainsString('#f59e0b', $html, $jenis);
            $this->assertStringNotContainsString('<script>x</script>', $html, $jenis); // di-escape
        }

        $this->assertStringContainsString('Pertanyaan &lt;b&gt;uji&lt;/b&gt;', (new ChatMail($session, 'transkrip'))->render());
    }

    // ------------------------------------------------------------ lampiran

    public function test_pengunjung_mengirim_gambar_dan_mengunduhnya_lewat_tautan_bertanda_tangan(): void
    {
        $token = $this->mulai();
        $res = $this->postJson('/chat/upload', ['token' => $token, 'file' => UploadedFile::fake()->image('foto.png', 300, 200)])->assertCreated();

        $lampiran = $res->json('message.attachments.0');
        $this->assertSame('foto.png', $lampiran['name']);
        $this->assertTrue($lampiran['is_image']);
        $this->assertSame('', $res->json('message.body'));
        $this->assertStringContainsString('signature=', $lampiran['url']);
        $this->assertCount(1, Storage::disk('local')->allFiles('chat'));

        $this->get($lampiran['url'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(strtok($lampiran['url'], '?'))->assertForbidden(); // tanpa tanda tangan
        // lampiran muncul di poll
        $this->assertSame('foto.png', $this->getJson("/chat/poll?token=$token")->json('messages.1.attachments.0.name'));
    }

    public function test_upload_menolak_berkas_besar_dan_tipe_terlarang(): void
    {
        $token = $this->mulai();

        $this->postJson('/chat/upload', ['token' => $token, 'file' => UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('file');
        $this->postJson('/chat/upload', ['token' => $token, 'file' => UploadedFile::fake()->create('virus.exe', 10)])->assertStatus(422);
        // berkas PHP yang diberi ekstensi .jpg: tipe dibaca dari ISI berkas, bukan nama
        $tmp = tempnam(sys_get_temp_dir(), 'chat');
        file_put_contents($tmp, '<?php echo 1;');
        $this->postJson('/chat/upload', ['token' => $token, 'file' => new UploadedFile($tmp, 'menyamar.jpg', 'image/jpeg', null, true)])->assertStatus(422);
        @unlink($tmp);
        $this->postJson('/chat/upload', ['token' => $token])->assertStatus(422);
        $this->postJson('/chat/upload', ['token' => $token, 'file' => UploadedFile::fake()->create('batas.pdf', 5120, 'application/pdf')])->assertCreated();
        $this->assertCount(1, Storage::disk('local')->allFiles('chat'));
    }

    public function test_upload_ke_sesi_selesai_atau_token_salah_ditolak(): void
    {
        $token = $this->mulai();
        $file = fn () => UploadedFile::fake()->image('a.jpg');

        $this->postJson('/chat/upload', ['token' => str_repeat('z', 48), 'file' => $file()])->assertNotFound();
        $this->postJson('/chat/end', ['token' => $token])->assertOk();
        $this->postJson('/chat/upload', ['token' => $token, 'file' => $file()])->assertStatus(409);
    }

    public function test_petugas_mengirim_pdf_dan_hanya_admin_login_yang_bisa_mengunduh(): void
    {
        $token = $this->mulai();
        $session = ChatSession::firstOrFail();
        $ani = $this->admin();

        $res = $this->actingAs($ani)->postJson("/admin/chat/{$session->id}/upload", ['file' => UploadedFile::fake()->create('brosur.pdf', 100, 'application/pdf')])->assertCreated();
        $this->assertSame('agent', $res->json('message.sender'));
        $this->assertFalse($res->json('message.attachments.0.is_image'));
        $this->assertSame($ani->id, $session->fresh()->agent_id);

        $urlAdmin = $res->json('message.attachments.0.url');
        $this->get($urlAdmin)->assertOk()->assertHeader('Content-Disposition');

        auth()->logout();
        $this->get($urlAdmin)->assertRedirect(); // belum login
        $guru = User::factory()->create(['role' => 'guru']);
        $this->actingAs($guru)->get($urlAdmin)->assertRedirect(); // bukan admin: dialihkan

        // pengunjung melihatnya lewat tautan bertanda tangan
        auth()->logout();
        $this->assertStringContainsString('signature=', $this->getJson("/chat/poll?token=$token")->json('messages.1.attachments.0.url'));
    }
}
