<?php

namespace App\Http\Controllers;

use App\Support\SekolahAktif;
use App\ChatAgentStatus;
use App\ChatAttachment;
use App\ChatMessage;
use App\ChatSession;
use App\Support\ChatNotifier;
use App\Support\ChatPdf;
use App\Support\ChatQueue;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Panel petugas konsultasi online (admin): antrian, ambil chat, balas, alihkan, tutup, dan status petugas. */
class ChatAdminController extends Controller
{
    public function index(): View
    {
        return view('chat.admin', ['balasanCepat' => ChatQueue::balasanCepat()]);
    }

    /** Antrian + chat saya + petugas lain. Dipanggil tiap 5 detik; sekaligus menjadi tanda petugas masih aktif. */
    public function queue(): JsonResponse
    {
        $saya = ChatQueue::heartbeat(auth()->id());
        ChatQueue::dispatch();

        $hitung = ['messages as belum_dibaca' => fn ($q) => $q->where('sender', 'visitor')->whereNull('read_at')];
        $bentuk = fn (ChatSession $s) => [
            'id' => $s->id,
            'nama' => $s->nama,
            'topik' => $s->topik,
            'status' => $s->status,
            'belum_dibaca' => $s->belum_dibaca,
            'menunggu' => $s->created_at?->diffForHumans(null, true),
            'detik' => (int) $s->created_at?->diffInSeconds(now()),
            'balas_email' => $s->balas_email,
        ];

        $menunggu = ChatSession::withCount($hitung)->where('status', 'menunggu')->orderBy('id')->limit(100)->get()->map($bentuk)->values();
        $milik = ChatSession::withCount($hitung)->where('status', 'dilayani')->where('agent_id', auth()->id())->orderBy('taken_at')->get()->map($bentuk)->values();

        $online = ChatQueue::onlineAgents()->keyBy('user_id');
        $beban = ChatSession::where('status', 'dilayani')->selectRaw('agent_id, count(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id');
        $petugas = User::denganPeran('admin')->orderBy('name')->get(['id', 'name'])->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'online' => $online->has($u->id),
            'beban' => (int) ($beban[$u->id] ?? 0),
        ])->values();

        return response()->json([
            'waiting' => $menunggu,
            'mine' => $milik,
            'agents' => $petugas,
            'me' => ['id' => auth()->id(), 'status' => $saya->status],
            'maks_chat' => ChatQueue::maksChat(),
            'avg_minutes' => ChatQueue::rataDurasiMenit(),
        ]);
    }

    /** Ubah status petugas: online | istirahat | offline. */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:online,istirahat,offline']]);
        $status = ChatQueue::heartbeat(auth()->id());
        $status->update(['status' => $data['status']]);

        $dibagi = $data['status'] === 'online' ? ChatQueue::dispatch() : 0;

        return response()->json(['status' => $status->status, 'dibagi' => $dibagi]);
    }

    /** Ambil chat dari antrian. */
    public function take(ChatSession $session): JsonResponse
    {
        abort_unless($session->status === 'menunggu', 409, 'Chat ini sudah diambil atau selesai.');

        ChatQueue::tugaskan($session, auth()->id(), 'Anda terhubung dengan '.auth()->user()->name.'. Silakan sampaikan pertanyaan Anda.');

        return response()->json(['ok' => true]);
    }

    public function poll(Request $request, ChatSession $session): JsonResponse
    {
        ChatQueue::heartbeat(auth()->id());
        $baru = $session->messages()->with(['user:id,name', 'attachments'])->where('id', '>', (int) $request->query('last_id', 0))->orderBy('id')->limit(100)->get();
        $session->messages()->where('sender', 'visitor')->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'session' => [
                'id' => $session->id, 'nama' => $session->nama, 'email' => $session->email, 'whatsapp' => $session->whatsapp, 'topik' => $session->topik,
                'status' => $session->status, 'agent_id' => $session->agent_id, 'agent' => $session->agent?->name, 'balas_email' => $session->balas_email,
            ],
            'messages' => $baru->map(fn (ChatMessage $m) => ChatController::bentuk($m, true))->values(),
            'visitor_typing' => (bool) ($session->visitor_typing_at && $session->visitor_typing_at->gt(now()->subSeconds(4))),
        ]);
    }

    public function reply(Request $request, ChatSession $session): JsonResponse
    {
        abort_if($session->isClosed(), 409, 'Sesi ini sudah selesai.');
        abort_if($session->agent_id && $session->agent_id !== auth()->id(), 403, 'Chat ini sedang dilayani petugas lain.');

        $data = $request->validate(['body' => ['required', 'string', 'max:'.ChatController::MAKS_PESAN]], [], ['body' => 'balasan']);
        $body = trim(preg_replace('/[^\P{C}\n]+/u', '', $data['body']));
        abort_if($body === '', 422, 'Balasan tidak boleh kosong.');

        if ($session->status === 'menunggu') { // membalas chat di antrian = mengambilnya
            $session->update(['status' => 'dilayani', 'agent_id' => auth()->id(), 'taken_at' => now()]);
        }

        $pesan = $session->addMessage('agent', $body, auth()->id());
        $session->forceFill(['agent_typing_at' => null])->saveQuietly();
        ChatNotifier::balasan($session, $body, auth()->user()->name);

        return response()->json(['message' => ChatController::bentuk($pesan->load(['user:id,name', 'attachments']), true)], 201);
    }

    // ------------------------------------------------------------ riwayat semua percakapan

    /** Daftar riwayat dengan filter tanggal, topik, petugas, status, dan kata kunci. */
    public function riwayat(Request $request): View
    {
        $daftar = $this->saring($request)->with('agent:id,name')->withCount('messages')->latest('id')->paginate(20)->withQueryString();

        return view('chat.riwayat-admin', [
            'daftar' => $daftar,
            'topik' => ChatSession::TOPIK,
            'petugas' => User::denganPeran('admin')->orderBy('name')->get(['id', 'name']),
            'filter' => $request->only(['dari', 'sampai', 'topik', 'agent_id', 'status', 'q']),
        ]);
    }

    public function riwayatShow(ChatSession $session): View
    {
        return view('chat.riwayat-detail', [
            'sesi' => $session->load('agent:id,name'),
            'pesan' => $session->messages()->with(['user:id,name', 'attachments'])->orderBy('id')->get(),
        ]);
    }

    /** Unduh daftar riwayat (sesuai filter) sebagai berkas Excel. */
    public function riwayatExport(Request $request)
    {
        $path = tempnam(sys_get_temp_dir(), 'chat').'.xlsx';
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
            'ID', 'Tanggal', 'Nama', 'Email', 'WhatsApp', 'Topik', 'Petugas', 'Status', 'Menunggu (menit)', 'Durasi layanan (menit)', 'Jumlah pesan', 'Rating', 'Komentar',
        ]));

        $this->saring($request)->with('agent:id,name')->withCount('messages')->orderBy('id')->limit(10000)->get()->each(function (ChatSession $s) use ($writer) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                $s->id,
                $s->created_at?->format('Y-m-d H:i'),
                $s->nama,
                $s->email,
                $s->whatsapp ?? '',
                $s->topik,
                $s->agent?->name ?? '',
                $s->status,
                $s->taken_at ? round($s->created_at->diffInSeconds($s->taken_at) / 60, 1) : '',
                $s->taken_at && $s->closed_at ? round($s->taken_at->diffInSeconds($s->closed_at) / 60, 1) : '',
                $s->messages_count,
                $s->rating ?? '',
                $s->rating_komentar ?? '',
            ]));
        });
        $writer->close();

        return response()->download($path, 'riwayat-konsultasi-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    /** PDF satu percakapan (untuk petugas). */
    public function pdf(ChatSession $session)
    {
        return ChatPdf::buat($session)->download(ChatPdf::namaBerkas($session));
    }

    private function saring(Request $request)
    {
        $f = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'topik' => ['nullable', 'string', 'max:60'],
            'agent_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:menunggu,dilayani,selesai'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return ChatSession::query()
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['sampai'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($f['topik'] ?? null, fn ($q, $v) => $q->where('topik', $v))
            ->when($f['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $like)->orWhere('email', 'like', $like));
            });
    }

    /** Petugas mengirim gambar/PDF. */
    public function upload(Request $request, ChatSession $session): JsonResponse
    {
        abort_if($session->isClosed(), 409, 'Sesi ini sudah selesai.');
        abort_if($session->agent_id && $session->agent_id !== auth()->id(), 403, 'Chat ini sedang dilayani petugas lain.');

        if ($session->status === 'menunggu') {
            $session->update(['status' => 'dilayani', 'agent_id' => auth()->id(), 'taken_at' => now()]);
        }
        $pesan = ChatController::simpanLampiran($request, $session, 'agent', auth()->id());

        return response()->json(['message' => ChatController::bentuk($pesan, true)], 201);
    }

    public function file(ChatAttachment $attachment)
    {
        return ChatController::kirimBerkas($attachment);
    }

    public function typing(ChatSession $session): JsonResponse
    {
        $session->forceFill(['agent_typing_at' => now()])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    /** Alihkan chat ke petugas lain. */
    public function transfer(Request $request, ChatSession $session): JsonResponse
    {
        abort_if($session->isClosed(), 409, 'Sesi ini sudah selesai.');
        abort_if($session->agent_id && $session->agent_id !== auth()->id(), 403, 'Chat ini sedang dilayani petugas lain.');

        $data = $request->validate(['agent_id' => ['required', 'integer', SekolahAktif::ada('users')]]);
        $tujuan = User::denganPeran('admin')->findOrFail($data['agent_id']);
        abort_if($tujuan->id === auth()->id(), 422, 'Pilih petugas lain.');

        $session->update(['status' => 'dilayani', 'agent_id' => $tujuan->id, 'taken_at' => $session->taken_at ?? now()]);
        $session->addMessage('system', 'Percakapan dialihkan ke '.$tujuan->name.'.');

        return response()->json(['ok' => true, 'agent' => $tujuan->name]);
    }

    public function close(ChatSession $session): JsonResponse
    {
        abort_if($session->agent_id && $session->agent_id !== auth()->id(), 403, 'Chat ini sedang dilayani petugas lain.');

        if (! $session->isClosed()) {
            $session->update(['status' => 'selesai', 'closed_at' => now()]);
            $session->addMessage('system', 'Percakapan diakhiri oleh petugas. Terima kasih.');
            ChatNotifier::selesai($session);
            ChatQueue::dispatch(); // slot kosong -> isi dari antrian
        }

        return response()->json(['ok' => true]);
    }
}
