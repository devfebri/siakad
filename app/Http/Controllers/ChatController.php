<?php

namespace App\Http\Controllers;

use App\ChatAttachment;
use App\ChatMessage;
use App\ChatSession;
use App\ChatFaq;
use App\Support\ChatBot;
use App\Support\ChatHours;
use App\Support\ChatNotifier;
use App\Support\ChatQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Sisi pengunjung konsultasi online. Semua endpoint mengembalikan JSON dan dipanggil lewat jQuery AJAX
 * (resources/js/chat-widget.js). Pengunjung dikenali dari token acak sesi yang disimpan di localStorage.
 */
class ChatController extends Controller
{
    public const MAKS_PESAN = 1000;
    public const MAKS_LAMPIRAN_KB = 5120;

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'regex:/^[0-9+\s\-]{8,25}$/'],
            'topik' => ['required', 'in:'.implode(',', ChatSession::TOPIK)],
            'persetujuan' => ['accepted'],
        ], [
            'persetujuan.accepted' => 'Centang persetujuan penyimpanan data untuk melanjutkan.',
            'whatsapp.regex' => 'Nomor WhatsApp tidak valid.',
        ], ['nama' => 'nama', 'email' => 'email', 'whatsapp' => 'nomor WhatsApp', 'topik' => 'topik']);

        $session = ChatSession::create([
            'token' => ChatSession::newToken(),
            'nama' => trim(strip_tags($data['nama'])),
            'email' => $data['email'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'topik' => $data['topik'],
            'status' => 'menunggu',
            'persetujuan' => true,
            'last_visitor_at' => now(),
            'ip' => $request->ip(),
        ]);

        $session->addMessage('system', 'Halo '.$session->nama.', terima kasih telah menghubungi '.config('school.short_name').'. Silakan tulis pertanyaan Anda, petugas akan segera membalas.');
        ChatNotifier::dimulai($session);
        ChatQueue::dispatch();
        $session->refresh();
        if (ChatBot::aktif($session)) {
            ChatBot::sapa($session);
        }

        return response()->json(['token' => $session->token] + $this->keadaan($session, 0, true), 201);
    }

    public function send(Request $request): JsonResponse
    {
        $session = $this->sesi($request);
        abort_if($session->isClosed(), 409, 'Sesi ini sudah selesai.');

        $data = $request->validate(['body' => ['required', 'string', 'max:'.self::MAKS_PESAN]], ['body.required' => 'Pesan tidak boleh kosong.'], ['body' => 'pesan']);
        $body = trim(preg_replace('/[^\P{C}\n]+/u', '', $data['body']));
        abort_if($body === '', 422, 'Pesan tidak boleh kosong.');

        $pesan = $session->addMessage('visitor', $body);
        $session->update(['last_visitor_at' => now(), 'visitor_typing_at' => null]);

        return response()->json(['message' => $this->bentuk($pesan)] + $this->tanggapi($session, $body), 201);
    }

    /** Daftar tombol FAQ untuk chatbot (pilihan cepat). */
    public function faq(Request $request): JsonResponse
    {
        $this->sesi($request);

        return response()->json(['buttons' => ChatBot::tombol()]);
    }

    /** Pengunjung menekan tombol FAQ: pertanyaannya dicatat sebagai pesan pengunjung lalu dijawab bot. */
    public function pilihFaq(Request $request): JsonResponse
    {
        $session = $this->sesi($request);
        abort_if($session->isClosed(), 409, 'Sesi ini sudah selesai.');
        $faq = ChatFaq::where('aktif', true)->findOrFail((int) $request->input('faq_id'));
        abort_unless(ChatBot::aktif($session), 409, 'Asisten otomatis tidak aktif.');

        $pesan = $session->addMessage('visitor', $faq->pertanyaan);
        $session->update(['last_visitor_at' => now()]);
        $hasil = ChatBot::tangani($session, $faq->pertanyaan, $faq);

        return response()->json(['message' => $this->bentuk($pesan), 'extra' => array_map(fn ($m) => $this->bentuk($m), $hasil['pesan'])], 201);
    }

    /**
     * Setelah pesan pengunjung tersimpan: bila chat sedang dilayani bot, bot menjawab (atau meneruskan ke petugas);
     * bila tidak, pemberitahuan email ke admin dikirim seperti biasa.
     *
     * @return array{extra: array<int, array<string, mixed>>}
     */
    private function tanggapi(ChatSession $session, string $isi): array
    {
        if (! ChatBot::aktif($session)) {
            ChatNotifier::pesanMasuk($session, $isi);

            return ['extra' => []];
        }

        $hasil = ChatBot::tangani($session, $isi);
        if ($hasil['diteruskan']) {
            ChatNotifier::diteruskan($session, $isi);
        }

        return ['extra' => array_map(fn ($m) => $this->bentuk($m), $hasil['pesan'])];
    }

    /** Pesan baru sejak last_id + keadaan sesi. open=1 berarti panel sedang dilihat (pesan petugas ditandai terbaca). */
    public function poll(Request $request): JsonResponse
    {
        $session = $this->sesi($request);
        $session->forceFill(['last_visitor_at' => now()])->saveQuietly();

        if ($session->status === 'menunggu') {
            ChatQueue::dispatch();
            $session->refresh();
        }

        return response()->json($this->keadaan($session, (int) $request->query('last_id', 0), $request->boolean('open')));
    }

    /** Apakah ada petugas online (untuk status di header widget sebelum chat dimulai). */
    public function status(): JsonResponse
    {
        return response()->json([
            'agents_online' => ChatQueue::onlineAgents()->count(),
            'jam_buka' => ChatHours::buka(),
            'jam_label' => ChatHours::label(),
        ]);
    }

    /** Pengunjung memilih meninggalkan pesan dan dibalas lewat email, bukan menunggu di antrian. */
    public function leave(Request $request): JsonResponse
    {
        $session = $this->sesi($request);
        abort_if($session->isClosed() || $session->status !== 'menunggu', 409);

        $session->update(['balas_email' => true]);
        $session->addMessage('system', 'Pesan Anda kami simpan. Petugas akan membalas lewat email '.$session->email.'. Anda boleh menutup jendela ini.');

        return response()->json($this->keadaan($session, (int) $request->input('last_id', 0), true));
    }

    /** Kirim gambar/PDF (jpg, png, pdf; maks. 5 MB). Disimpan di storage privat dan diunduh lewat tautan bertanda tangan. */
    public function upload(Request $request): JsonResponse
    {
        $session = $this->sesi($request);
        abort_if($session->isClosed(), 409, 'Sesi ini sudah selesai.');

        $pesan = self::simpanLampiran($request, $session, 'visitor', null);
        $session->update(['last_visitor_at' => now(), 'visitor_typing_at' => null]);
        ChatNotifier::pesanMasuk($session, '[lampiran] '.$pesan->attachments->first()->nama_asli);

        return response()->json(['message' => $this->bentuk($pesan)], 201);
    }

    /** Validasi, simpan berkas, dan buat pesan berisi lampiran. */
    public static function simpanLampiran(Request $request, ChatSession $session, string $sender, ?int $userId): ChatMessage
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'mimetypes:image/jpeg,image/png,application/pdf', 'max:'.self::MAKS_LAMPIRAN_KB],
        ], [
            'file.max' => 'Ukuran berkas maksimal 5 MB.',
            'file.mimes' => 'Hanya berkas JPG, PNG, atau PDF yang diperbolehkan.',
            'file.mimetypes' => 'Hanya berkas JPG, PNG, atau PDF yang diperbolehkan.',
            'file.uploaded' => 'Berkas gagal diunggah. Periksa ukuran (maks. 5 MB).',
        ], ['file' => 'berkas']);

        $file = $request->file('file');
        $ext = strtolower($file->guessExtension() ?: $file->extension());
        $path = Storage::disk('local')->putFileAs('chat/'.$session->id, $file, Str::random(40).'.'.$ext);

        $pesan = $session->addMessage($sender, '', $userId);
        $pesan->attachments()->create([
            'sekolah_id' => $session->sekolah_id,
            'path' => $path,
            'nama_asli' => Str::limit(preg_replace('/\p{C}+/u', '', $file->getClientOriginalName()), 120, ''),
            'mime' => $file->getMimeType(),
            'ukuran' => $file->getSize(),
        ]);

        return $pesan->load(['user:id,name', 'attachments']);
    }

    /** Unduh/tampilkan lampiran (tautan bertanda tangan untuk pengunjung). */
    public function file(ChatAttachment $attachment)
    {
        return self::kirimBerkas($attachment);
    }

    public static function kirimBerkas(ChatAttachment $attachment)
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
        $gambar = str_starts_with($attachment->mime, 'image/');

        return Storage::disk('local')->response($attachment->path, $attachment->nama_asli, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], $gambar ? 'inline' : 'attachment');
    }

    public function typing(Request $request): JsonResponse
    {
        $session = $this->sesi($request);
        abort_if($session->isClosed(), 409);
        $session->forceFill(['visitor_typing_at' => now()])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    public function end(Request $request): JsonResponse
    {
        $session = $this->sesi($request);

        if (! $session->isClosed()) {
            $session->update(['status' => 'selesai', 'closed_at' => now()]);
            $session->addMessage('system', 'Percakapan diakhiri oleh pengunjung.');
            ChatNotifier::selesai($session);
            ChatQueue::dispatch();
        }

        return response()->json(['ok' => true]);
    }

    private function sesi(Request $request): ChatSession
    {
        $token = (string) ($request->input('token') ?? $request->query('token'));
        abort_unless(strlen($token) === 48, 404, 'Sesi tidak ditemukan.');

        return ChatSession::where('token', $token)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function keadaan(ChatSession $session, int $lastId, bool $dibuka): array
    {
        $pesan = $session->messages()->with(['user:id,name', 'attachments'])->where('id', '>', $lastId)->orderBy('id')->limit(100)->get();

        if ($dibuka) {
            $session->messages()->whereIn('sender', ['agent', 'bot', 'system'])->whereNull('read_at')->update(['read_at' => now()]);
        }

        return [
            'messages' => $pesan->map(fn (ChatMessage $m) => $this->bentuk($m))->values(),
            'status' => $session->status,
            'agent' => $session->agent?->name,
            'agent_typing' => (bool) ($session->agent_typing_at && $session->agent_typing_at->gt(now()->subSeconds(4))),
            'read_upto' => (int) $session->messages()->where('sender', 'visitor')->whereNotNull('read_at')->max('id'),
            'unread' => $session->messages()->whereIn('sender', ['agent', 'bot'])->whereNull('read_at')->count(),
            'closed' => $session->isClosed(),
            'queue' => ChatQueue::info($session),
            'agents_online' => ChatQueue::onlineAgents()->count(),
            'balas_email' => $session->balas_email,
            'export_url' => route('chat.export', $session->riwayat_token),
            'history_url' => route('chat.riwayat', $session->riwayat_token),
            'rated' => $session->rating !== null,
            'jam_buka' => ChatHours::buka(),
            'bot_mode' => ChatBot::aktif($session),
        ];
    }

    /**
     * Bentuk JSON satu pesan. $untukAdmin: tautan lampiran lewat route admin (login), selain itu tautan bertanda tangan 2 jam.
     *
     * @return array<string, mixed>
     */
    public static function bentuk(ChatMessage $m, bool $untukAdmin = false): array
    {
        return [
            'id' => $m->id,
            'sender' => $m->sender,
            'name' => $m->sender === 'agent' ? ($m->user?->name ?? 'Petugas') : null,
            'body' => $m->body,
            'time' => $m->created_at?->format('H:i'),
            'read' => $m->read_at !== null,
            'buttons' => $m->meta['buttons'] ?? [],
            'attachments' => $m->attachments->map(fn (ChatAttachment $a) => [
                'id' => $a->id,
                'name' => $a->nama_asli,
                'size' => $a->ukuran,
                'is_image' => str_starts_with($a->mime, 'image/'),
                'url' => $untukAdmin ? route('admin/chat.file', $a->id) : URL::temporarySignedRoute('chat.file', now()->addHours(2), ['attachment' => $a->id]),
            ])->values(),
        ];
    }
}
