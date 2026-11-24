<?php

namespace App\Http\Controllers;

use App\ChatFaq;
use App\ChatQuickReply;
use App\ChatSetting;
use App\Support\ChatBot;
use App\Support\ChatHours;
use App\Support\ChatQueue;
use App\Support\DataTableResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Pengaturan konsultasi online: jam layanan, hari libur, chatbot, dan kelola FAQ chatbot (admin). */
class ChatPengaturanController extends Controller
{
    public function index(): View
    {
        $p = ChatHours::pengaturan();

        return view('chat.pengaturan', [
            'jam' => $p,
            'namaHari' => ChatHours::NAMA_HARI,
            'zonaPilihan' => ChatHours::ZONA,
            'batas' => ['maks_chat' => ChatQueue::maksChat(), 'retensi_hari' => ChatQueue::retensiHari(), 'tutup_otomatis_jam' => ChatQueue::tutupOtomatisJam()],
            'zonaDipilih' => ChatHours::zona(),
            'liburTeks' => collect($p['libur'])->map(fn ($l) => trim($l['tanggal'].' '.$l['keterangan']))->implode("\n"),
            'bot' => [
                'aktif' => (bool) ChatSetting::get('bot_aktif', '1'),
                'sapaan' => ChatSetting::get('bot_sapaan', 'Halo'),
                'kata_alihkan' => ChatSetting::get('bot_kata_alihkan', ChatBot::KATA_ALIHKAN),
                'pesan_alihkan' => ChatSetting::get('bot_pesan_alihkan', ChatBot::pesanAlihkan()),
            ],
            'statusSekarang' => ChatHours::buka(),
            'labelJam' => ChatHours::label(),
        ]);
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'zona' => ['required', 'in:'.implode(',', array_keys(ChatHours::ZONA))],
            'jam_buka' => ['required', 'date_format:H:i'],
            'jam_tutup' => ['required', 'date_format:H:i', 'after:jam_buka'],
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => ['integer', 'between:1,7'],
            'hari_libur' => ['nullable', 'string', 'max:3000'],
            'maks_chat' => ['nullable', 'integer', 'between:1,20'],
            'retensi_hari' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'tutup_otomatis_jam' => ['nullable', 'integer', 'min:0', 'max:720'],
            'bot_aktif' => ['nullable', 'boolean'],
            'bot_sapaan' => ['nullable', 'string', 'max:60'],
            'bot_kata_alihkan' => ['nullable', 'string', 'max:600'],
            'bot_pesan_alihkan' => ['nullable', 'string', 'max:500'],
        ], [
            'jam_tutup.after' => 'Jam tutup harus setelah jam buka.',
        ], ['jam_buka' => 'jam buka', 'jam_tutup' => 'jam tutup', 'hari_libur' => 'hari libur', 'hari' => 'hari layanan', 'maks_chat' => 'batas chat per petugas', 'retensi_hari' => 'masa simpan riwayat', 'tutup_otomatis_jam' => 'tutup otomatis']);

        $libur = $this->parseLibur((string) ($data['hari_libur'] ?? ''));

        // Batas opsional: yang tidak dikirim tetap memakai nilai sekarang.
        foreach (['maks_chat', 'retensi_hari', 'tutup_otomatis_jam'] as $kunci) {
            if (isset($data[$kunci])) {
                ChatSetting::put($kunci, (string) $data[$kunci]);
            }
        }
        ChatSetting::put('zona', $data['zona']);
        ChatSetting::put('jam_buka', $data['jam_buka']);
        ChatSetting::put('jam_tutup', $data['jam_tutup']);
        ChatSetting::put('hari_kerja', implode(',', collect($data['hari'] ?? [])->map(fn ($h) => (int) $h)->unique()->sort()->all()));
        ChatSetting::put('hari_libur', json_encode($libur, JSON_UNESCAPED_UNICODE));
        ChatSetting::put('bot_aktif', $request->boolean('bot_aktif') ? '1' : '0');
        ChatSetting::put('bot_sapaan', trim((string) ($data['bot_sapaan'] ?? '')) ?: 'Halo');
        ChatSetting::put('bot_kata_alihkan', trim((string) ($data['bot_kata_alihkan'] ?? '')));
        ChatSetting::put('bot_pesan_alihkan', trim((string) ($data['bot_pesan_alihkan'] ?? '')));

        return response()->json(['message' => 'Pengaturan konsultasi online disimpan.', 'status' => ChatHours::buka() ? 'buka' : 'tutup', 'label' => ChatHours::label()]);
    }

    /** Satu hari libur per baris: "2026-12-25 Hari Natal". @return array<int, array{tanggal: string, keterangan: string}> */
    private function parseLibur(string $teks): array
    {
        $hasil = [];
        foreach (preg_split('/\R/u', $teks) as $no => $baris) {
            $baris = trim($baris);
            if ($baris === '') {
                continue;
            }

            if (! preg_match('/^(\d{4}-\d{2}-\d{2})\s*(.*)$/u', $baris, $m) || ! checkdate((int) substr($m[1], 5, 2), (int) substr($m[1], 8, 2), (int) substr($m[1], 0, 4))) {
                throw ValidationException::withMessages(['hari_libur' => 'Baris '.($no + 1).' tidak valid. Tulis "TAHUN-BULAN-TANGGAL keterangan", mis. 2026-12-25 Hari Natal.']);
            }
            $hasil[$m[1]] = ['tanggal' => $m[1], 'keterangan' => mb_substr(trim(strip_tags($m[2])), 0, 80)];
        }
        ksort($hasil);

        return array_values($hasil);
    }

    // ------------------------------------------------------------ FAQ chatbot (CRUD AJAX)

    public function faqData(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            ChatFaq::query(),
            searchable: ['pertanyaan', 'kata_kunci', 'jawaban'],
            orderable: ['pertanyaan', 'urutan'],
            map: fn (ChatFaq $f) => [
                'id' => $f->id,
                'pertanyaan' => $f->pertanyaan,
                'kata_kunci' => $f->kata_kunci,
                'jawaban' => $f->jawaban,
                'ringkas' => \Illuminate\Support\Str::limit($f->jawaban, 80),
                'urutan' => $f->urutan,
                'aktif' => $f->aktif ? 1 : 0,
                'status' => $f->aktif ? 'Aktif|success' : 'Nonaktif|muted',
            ],
            defaultOrder: 'urutan',
            defaultDirection: 'asc',
        );
    }

    public function faqStore(Request $request): JsonResponse
    {
        ChatFaq::create($this->faqValid($request));

        return response()->json(['message' => 'FAQ berhasil ditambahkan.'], 201);
    }

    public function faqUpdate(Request $request, ChatFaq $faq): JsonResponse
    {
        $faq->update($this->faqValid($request));

        return response()->json(['message' => 'FAQ berhasil diperbarui.']);
    }

    public function faqDestroy(ChatFaq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json(['message' => 'FAQ berhasil dihapus.']);
    }

    // ------------------------------------------------------------ template balasan cepat (CRUD AJAX)

    public function balasanData(Request $request): JsonResponse
    {
        return DataTableResponse::make(
            $request,
            ChatQuickReply::query(),
            searchable: ['judul', 'isi'],
            orderable: ['judul', 'urutan'],
            map: fn (ChatQuickReply $r) => [
                'id' => $r->id,
                'judul' => $r->judul,
                'isi' => $r->isi,
                'ringkas' => \Illuminate\Support\Str::limit($r->isi, 90),
                'urutan' => $r->urutan,
                'aktif' => $r->aktif ? 1 : 0,
                'status' => $r->aktif ? 'Aktif|success' : 'Nonaktif|muted',
            ],
            defaultOrder: 'urutan',
            defaultDirection: 'asc',
        );
    }

    public function balasanStore(Request $request): JsonResponse
    {
        ChatQuickReply::create($this->balasanValid($request));

        return response()->json(['message' => 'Template balasan ditambahkan.'], 201);
    }

    public function balasanUpdate(Request $request, ChatQuickReply $balasan): JsonResponse
    {
        $balasan->update($this->balasanValid($request));

        return response()->json(['message' => 'Template balasan diperbarui.']);
    }

    public function balasanDestroy(ChatQuickReply $balasan): JsonResponse
    {
        $balasan->delete();

        return response()->json(['message' => 'Template balasan dihapus.']);
    }

    /** @return array<string, mixed> */
    private function balasanValid(Request $request): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:60'],
            'isi' => ['required', 'string', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ], [], ['judul' => 'judul', 'isi' => 'isi balasan']);

        return [
            'judul' => trim(strip_tags($data['judul'])),
            'isi' => trim($data['isi']),
            'urutan' => $data['urutan'] ?? 0,
            'aktif' => $request->boolean('aktif'),
        ];
    }

    /** @return array<string, mixed> */
    private function faqValid(Request $request): array
    {
        $data = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:200'],
            'kata_kunci' => ['required', 'string', 'max:300'],
            'jawaban' => ['required', 'string', 'max:2000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ], [], ['pertanyaan' => 'pertanyaan', 'kata_kunci' => 'kata kunci', 'jawaban' => 'jawaban']);

        $kunci = collect(explode(',', $data['kata_kunci']))->map(fn ($k) => trim($k))->filter()->unique()->values();
        if ($kunci->isEmpty()) {
            throw ValidationException::withMessages(['kata_kunci' => 'Isi minimal satu kata kunci, pisahkan dengan koma.']);
        }

        return [
            'pertanyaan' => trim(strip_tags($data['pertanyaan'])),
            'kata_kunci' => $kunci->implode(', '),
            'jawaban' => trim($data['jawaban']),
            'urutan' => $data['urutan'] ?? 0,
            'aktif' => $request->boolean('aktif'),
        ];
    }
}
