<?php

namespace Tests\Feature;

use App\ChatAgentStatus;
use App\ChatFaq;
use App\ChatSession;
use App\ChatSetting;
use App\Mail\ChatMail;
use App\Support\ChatBot;
use App\Support\ChatHours;
use App\Support\ChatQueue;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChatBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta')); // Rabu, jam layanan
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mulai(): array
    {
        $res = $this->postJson('/chat/start', ['nama' => 'Dewi', 'email' => 'dewi@contoh.test', 'topik' => ChatSession::TOPIK[0], 'persetujuan' => 1])->assertCreated();

        return [$res->json('token'), $res];
    }

    private function petugasOnline(): User
    {
        $u = User::factory()->create(['role' => 'admin', 'name' => 'Ani']);
        ChatAgentStatus::create(['user_id' => $u->id, 'status' => 'online', 'last_seen_at' => now()]);

        return $u;
    }

    private function kirim(string $token, string $teks)
    {
        return $this->postJson('/chat/send', ['token' => $token, 'body' => $teks])->assertCreated();
    }

    // ------------------------------------------------------------ jam layanan

    public function test_jam_layanan_bawaan_senin_jumat_0800_1600(): void
    {
        $this->assertStringStartsWith('Senin–Jumat, 08.00–16.00', ChatHours::label());

        $buka = fn (string $waktu) => ChatHours::buka(Carbon::parse($waktu, 'Asia/Jakarta'));
        $this->assertTrue($buka('2026-11-04 08:00'));
        $this->assertTrue($buka('2026-11-04 15:59'));
        $this->assertFalse($buka('2026-11-04 07:59'));
        $this->assertFalse($buka('2026-11-04 16:00'));
        $this->assertFalse($buka('2026-11-07 10:00'), 'Sabtu');
        $this->assertFalse($buka('2026-11-08 10:00'), 'Minggu');
    }

    public function test_hari_libur_dan_pengaturan_jam_diterapkan(): void
    {
        ChatSetting::put('hari_libur', json_encode([['tanggal' => '2026-11-04', 'keterangan' => 'Libur sekolah']]));
        $this->assertFalse(ChatHours::buka());
        $this->assertSame('Libur sekolah', ChatHours::libur(now()));

        ChatSetting::put('hari_libur', '[]');
        ChatSetting::put('jam_buka', '10:30');
        $this->assertFalse(ChatHours::buka());
        ChatSetting::put('jam_buka', '09:00');
        ChatSetting::put('hari_kerja', '1,2,3,4,5,6');
        $this->assertTrue(ChatHours::buka(Carbon::parse('2026-11-07 10:00', 'Asia/Jakarta')), 'Sabtu ditambahkan');
        $this->assertStringStartsWith('Senin–Sabtu, 09.00–16.00', ChatHours::label());

        ChatSetting::put('hari_kerja', '1,3,5');
        $this->assertStringStartsWith('Senin, Rabu, Jumat,', ChatHours::label());
    }

    public function test_dispatch_tidak_membagi_chat_di_luar_jam_layanan(): void
    {
        $this->petugasOnline();
        $s = ChatSession::create(['token' => ChatSession::newToken(), 'nama' => 'X', 'email' => 'x@c.test', 'topik' => 'Lainnya', 'status' => 'menunggu', 'persetujuan' => true]);

        Carbon::setTestNow(Carbon::parse('2026-11-04 18:00:00', 'Asia/Jakarta'));
        ChatAgentStatus::query()->update(['last_seen_at' => now()]);
        $this->assertSame(0, ChatQueue::dispatch());
        $this->assertSame('menunggu', $s->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
        ChatAgentStatus::query()->update(['last_seen_at' => now()]);
        $this->assertSame(1, ChatQueue::dispatch());
    }

    // ------------------------------------------------------------ kapan bot aktif

    public function test_bot_menyapa_dengan_tombol_saat_tidak_ada_petugas_online(): void
    {
        [, $res] = $this->mulai();

        $bot = collect($res->json('messages'))->firstWhere('sender', 'bot');
        $this->assertNotNull($bot);
        $this->assertStringContainsString('Halo, Dewi!', $bot['body']);
        $this->assertStringContainsString('Jam layanan: Senin–Jumat, 08.00–16.00', $bot['body']);
        $this->assertCount(5, $bot['buttons']);
        $this->assertSame('Jam & alamat sekolah', $bot['buttons'][0]['label']);
        $this->assertTrue($res->json('bot_mode'));
        $this->assertTrue($res->json('jam_buka'));
    }

    public function test_bot_tidak_aktif_bila_petugas_online_pada_jam_layanan(): void
    {
        $this->petugasOnline();
        [$token, $res] = $this->mulai();

        $this->assertNull(collect($res->json('messages'))->firstWhere('sender', 'bot'));
        $this->assertSame('dilayani', $res->json('status'));
        $this->assertFalse($res->json('bot_mode'));

        $kirim = $this->kirim($token, 'Tanya jam buka');
        $this->assertSame([], $kirim->json('extra'));
    }

    public function test_bot_melayani_di_luar_jam_layanan_walau_petugas_online(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 18:00:00', 'Asia/Jakarta'));
        $this->petugasOnline();
        [$token, $res] = $this->mulai();

        $this->assertNotNull(collect($res->json('messages'))->firstWhere('sender', 'bot'));
        $this->assertSame('menunggu', $res->json('status'));
        $this->assertFalse($res->json('jam_buka'));
        $this->assertStringContainsString('di luar jam layanan', collect($res->json('messages'))->firstWhere('sender', 'bot')['body']);
        $this->getJson('/chat/status')->assertJsonPath('jam_buka', false);
    }

    public function test_bot_bisa_dimatikan_admin(): void
    {
        ChatSetting::put('bot_aktif', '0');
        [$token, $res] = $this->mulai();

        $this->assertNull(collect($res->json('messages'))->firstWhere('sender', 'bot'));
        $this->assertSame([], $this->kirim($token, 'halo')->json('extra'));
    }

    public function test_bot_berhenti_setelah_petugas_mengambil_chat(): void
    {
        [$token] = $this->mulai();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson('/admin/chat/'.ChatSession::first()->id.'/take')->assertOk();
        auth()->logout();

        $this->assertSame([], $this->kirim($token, 'jam buka berapa?')->json('extra'));
    }

    // ------------------------------------------------------------ jawaban FAQ

    public function test_pertanyaan_dijawab_berdasarkan_kata_kunci(): void
    {
        [$token] = $this->mulai();

        $jam = $this->kirim($token, 'Jam buka sekolah berapa ya?')->json('extra.0');
        $this->assertSame('bot', $jam['sender']);
        $this->assertStringContainsString(config('school.name'), $jam['body']);
        $this->assertStringContainsString('Jam layanan: Senin–Jumat', $jam['body']);
        $this->assertStringNotContainsString('{', $jam['body'], 'semua penanda harus terisi');
        $this->assertCount(5, $jam['buttons']);

        $ppdb = $this->kirim($token, 'bagaimana cara mendaftar siswa baru?')->json('extra.0');
        $this->assertStringContainsString(route('ppdb'), $ppdb['body']);

        $this->assertFalse(ChatSession::first()->balas_email);
        Mail::assertNotQueued(ChatMail::class, fn (ChatMail $m) => in_array($m->jenis, ['diteruskan', 'masuk'], true));
    }

    public function test_faq_nonaktif_tidak_dipakai_dan_kata_kunci_terbanyak_menang(): void
    {
        ChatFaq::query()->delete();
        ChatFaq::create(['pertanyaan' => 'A', 'kata_kunci' => 'beasiswa', 'jawaban' => 'Jawaban A', 'urutan' => 1, 'aktif' => false]);
        ChatFaq::create(['pertanyaan' => 'B', 'kata_kunci' => 'beasiswa', 'jawaban' => 'Jawaban B', 'urutan' => 2, 'aktif' => true]);
        ChatFaq::create(['pertanyaan' => 'C', 'kata_kunci' => 'beasiswa, prestasi', 'jawaban' => 'Jawaban C', 'urutan' => 3, 'aktif' => true]);

        $this->assertSame('Jawaban B', ChatBot::cocok('info beasiswa')->jawaban);
        $this->assertSame('Jawaban C', ChatBot::cocok('beasiswa prestasi apa?')->jawaban);
        $this->assertNull(ChatBot::cocok('nilai raport'));
        $this->assertSame('Jawaban B', ChatBot::cocok('BEASISWA!!!')->jawaban, 'tanpa pengaruh huruf besar/tanda baca');
    }

    public function test_pertanyaan_tanpa_jawaban_diteruskan_ke_petugas_dengan_email(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'admin@contoh.test']);
        [$token] = $this->mulai();

        $res = $this->kirim($token, 'Apakah ada program pertukaran pelajar ke Jepang?');
        $this->assertStringContainsString('akan kami teruskan ke petugas', $res->json('extra.0.body'));
        $this->assertTrue(ChatSession::first()->balas_email);

        Mail::assertQueued(ChatMail::class, fn (ChatMail $m) => $m->jenis === 'diteruskan' && $m->hasTo('dewi@contoh.test'));
        Mail::assertQueued(ChatMail::class, fn (ChatMail $m) => $m->jenis === 'masuk' && $m->hasTo('admin@contoh.test'));

        // pertanyaan tidak terjawab berikutnya: dicatat, tidak ada email ulang
        $lagi = $this->kirim($token, 'Dan program magang apa ada?');
        $this->assertStringContainsString('sudah kami catat', $lagi->json('extra.0.body'));
        Mail::assertQueued(ChatMail::class, 3); // dimulai + diteruskan + masuk

        // chat tetap tampil di antrian admin sebagai "balas via email"
        $admin = User::where('email', 'admin@contoh.test')->first();
        $antrian = $this->actingAs($admin)->getJson('/admin/chat/queue')->json('waiting');
        $this->assertTrue($antrian[0]['balas_email']);
    }

    public function test_sapaan_saja_tidak_diteruskan(): void
    {
        [$token] = $this->mulai();

        $res = $this->kirim($token, 'Halo');
        $this->assertStringContainsString('pilih topik', mb_strtolower($res->json('extra.0.body')));
        $this->assertFalse(ChatSession::first()->balas_email);
        Mail::assertNotQueued(ChatMail::class, fn (ChatMail $m) => $m->jenis === 'diteruskan');
    }

    public function test_pertanyaan_keagamaan_tidak_dijawab_dan_diarahkan_ke_kontak(): void
    {
        ChatFaq::create(['pertanyaan' => 'Pancing', 'kata_kunci' => 'hukum, boleh', 'jawaban' => 'JAWABAN SALAH', 'urutan' => 0, 'aktif' => true]);
        [$token] = $this->mulai();

        foreach (['Apa fatwa tentang jual beli online?', 'Bagaimana hukum islam soal ini, boleh?', 'Ini halal atau haram?'] as $tanya) {
            $res = $this->kirim($token, $tanya);
            $this->assertStringContainsString('pertanyaan keagamaan', $res->json('extra.0.body'), $tanya);
            $this->assertStringContainsString(route('kontak'), $res->json('extra.0.body'));
            $this->assertStringNotContainsString('JAWABAN SALAH', $res->json('extra.0.body'));
        }

        $this->assertFalse(ChatSession::first()->balas_email, 'tidak diteruskan sebagai pertanyaan tak terjawab');
    }

    public function test_tombol_faq_dicatat_sebagai_pesan_pengunjung_lalu_dijawab_bot(): void
    {
        [$token] = $this->mulai();
        $faq = ChatFaq::where('pertanyaan', 'Cara mendaftar PPDB')->first();

        $res = $this->postJson('/chat/faq', ['token' => $token, 'faq_id' => $faq->id])->assertCreated();
        $this->assertSame('visitor', $res->json('message.sender'));
        $this->assertSame('Cara mendaftar PPDB', $res->json('message.body'));
        $this->assertStringContainsString(route('ppdb'), $res->json('extra.0.body'));
        $this->assertCount(5, $res->json('extra.0.buttons'));

        $faq->update(['aktif' => false]);
        $this->postJson('/chat/faq', ['token' => $token, 'faq_id' => $faq->id])->assertNotFound();
        $this->getJson("/chat/faq?token=$token")->assertOk()->assertJsonCount(4, 'buttons');
        $this->getJson('/chat/faq?token=salah')->assertNotFound();
    }

    public function test_tombol_faq_ditolak_bila_bot_tidak_aktif_atau_sesi_selesai(): void
    {
        [$token] = $this->mulai();
        $faq = ChatFaq::first();

        $this->postJson('/chat/end', ['token' => $token])->assertOk();
        $this->postJson('/chat/faq', ['token' => $token, 'faq_id' => $faq->id])->assertStatus(409);

        ChatSetting::put('bot_aktif', '0');
        [$token2] = $this->mulai();
        $this->postJson('/chat/faq', ['token' => $token2, 'faq_id' => $faq->id])->assertStatus(409);
    }

    // ------------------------------------------------------------ pengaturan admin

    public function test_admin_menyimpan_jam_layanan_dan_hari_libur(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = ['zona' => 'Asia/Makassar', 'jam_buka' => '07:30', 'jam_tutup' => '15:30', 'hari' => [1, 2, 3, 4, 5, 6], 'hari_libur' => "2026-12-25 Hari Natal\n2026-11-10 Hari Pahlawan\n\n", 'bot_aktif' => 1, 'bot_sapaan' => 'Selamat datang'];

        $this->actingAs($admin)->get('/admin/chat/pengaturan')->assertOk()->assertSee('Jam Layanan')->assertSee('FAQ Chatbot');
        $this->putJson('/admin/chat/pengaturan', $payload)->assertOk();

        $p = ChatHours::pengaturan();
        $this->assertSame(['07:30', '15:30'], [$p['buka'], $p['tutup']]);
        $this->assertSame([1, 2, 3, 4, 5, 6], $p['hari']);
        $this->assertSame(['2026-11-10', '2026-12-25'], array_column($p['libur'], 'tanggal'));
        $this->assertSame('Hari Pahlawan', $p['libur'][0]['keterangan']);
        $this->assertStringContainsString('Selamat datang', ChatSetting::get('bot_sapaan'));

        $this->putJson('/admin/chat/pengaturan', $payload + ['bot_aktif' => 0, 'jam_buka' => '07:30'])->assertOk();
        unset($payload['bot_aktif']);
        $this->putJson('/admin/chat/pengaturan', $payload)->assertOk();
        $this->assertSame('0', ChatSetting::get('bot_aktif'));
    }

    public function test_pengaturan_jam_divalidasi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ok = ['zona' => 'Asia/Jakarta', 'jam_buka' => '08:00', 'jam_tutup' => '16:00', 'hari' => [1]];

        $this->actingAs($admin)->putJson('/admin/chat/pengaturan', ['zona' => 'Asia/Jakarta', 'jam_buka' => '16:00', 'jam_tutup' => '08:00', 'hari' => [1]])->assertStatus(422)->assertJsonValidationErrors('jam_tutup');
        $this->putJson('/admin/chat/pengaturan', ['zona' => 'Asia/Jakarta', 'jam_buka' => '8 pagi', 'jam_tutup' => '16:00', 'hari' => [1]])->assertStatus(422)->assertJsonValidationErrors('jam_buka');
        $this->putJson('/admin/chat/pengaturan', ['zona' => 'Asia/Jakarta', 'jam_buka' => '08:00', 'jam_tutup' => '16:00'])->assertStatus(422)->assertJsonValidationErrors('hari');
        $this->putJson('/admin/chat/pengaturan', $ok + ['hari_libur' => "2026-13-45 salah"])->assertStatus(422)->assertJsonValidationErrors('hari_libur');
        $this->putJson('/admin/chat/pengaturan', $ok + ['hari_libur' => "besok libur"])->assertStatus(422)->assertJsonValidationErrors('hari_libur');
        $this->putJson('/admin/chat/pengaturan', $ok)->assertOk();
    }

    public function test_admin_mengelola_faq(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = $this->getJson('/admin/chat/faq/data?draw=1&start=0&length=10&search[value]=&columns[0][data]=&order[0][column]=0&order[0][dir]=asc')->assertOk();
        $this->assertSame(5, $data->json('recordsTotal'));

        $this->postJson('/admin/chat/faq', ['pertanyaan' => 'Beasiswa', 'kata_kunci' => ' beasiswa ,  bantuan, beasiswa ,', 'jawaban' => 'Info {nama_sekolah}', 'urutan' => 9, 'aktif' => 1])->assertCreated();
        $faq = ChatFaq::where('pertanyaan', 'Beasiswa')->firstOrFail();
        $this->assertSame('beasiswa, bantuan', $faq->kata_kunci);
        $this->assertTrue($faq->aktif);

        $this->putJson("/admin/chat/faq/{$faq->id}", ['pertanyaan' => 'Beasiswa 2', 'kata_kunci' => 'kip', 'jawaban' => 'Baru'])->assertOk();
        $this->assertSame(['Beasiswa 2', false], [$faq->fresh()->pertanyaan, $faq->fresh()->aktif]);

        $this->postJson('/admin/chat/faq', ['pertanyaan' => '', 'kata_kunci' => 'x', 'jawaban' => 'y'])->assertStatus(422)->assertJsonValidationErrors('pertanyaan');
        $this->postJson('/admin/chat/faq', ['pertanyaan' => 'T', 'kata_kunci' => ' , ,', 'jawaban' => 'y'])->assertStatus(422)->assertJsonValidationErrors('kata_kunci');

        $this->deleteJson("/admin/chat/faq/{$faq->id}")->assertOk();
        $this->assertDatabaseMissing('chat_faqs', ['id' => $faq->id]);
    }

    public function test_pengaturan_konsultasi_hanya_untuk_admin(): void
    {
        $this->get('/admin/chat/pengaturan')->assertRedirect();
        foreach (['guru', 'siswa'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/admin/chat/pengaturan')->assertRedirect();
            $this->putJson('/admin/chat/pengaturan', [])->assertForbidden();
            $this->postJson('/admin/chat/faq', [])->assertForbidden();
        }
    }
}
