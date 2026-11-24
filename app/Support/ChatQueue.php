<?php

namespace App\Support;

use App\ChatAgentStatus;
use App\ChatSession;
use App\ChatSetting;
use Illuminate\Support\Collection;

/**
 * Antrian konsultasi online: siapa petugas yang online, pembagian otomatis (beban paling sedikit),
 * posisi antrian, dan estimasi waktu tunggu.
 */
class ChatQueue
{
    /** Petugas tanpa aktivitas polling selama ini dianggap offline. */
    public const BATAS_AKTIF_MENIT = 2;
    public const DEFAULT_MAKS_CHAT = 3;
    public const DEFAULT_MENIT_PER_CHAT = 5;
    public const TAWARKAN_EMAIL_DETIK = 600;

    public const DEFAULT_RETENSI_HARI = 365;
    public const DEFAULT_TUTUP_OTOMATIS_JAM = 24;

    /** Petugas yang statusnya online dan masih aktif memolling. @return Collection<int, ChatAgentStatus> */
    public static function onlineAgents(): Collection
    {
        return ChatAgentStatus::where('status', 'online')
            ->where('last_seen_at', '>=', now()->subMinutes(self::BATAS_AKTIF_MENIT))
            ->with('user:id,name')->get()
            ->filter(fn (ChatAgentStatus $a) => $a->user !== null)->values();
    }

    public static function maksChat(): int
    {
        return max(1, (int) ChatSetting::get('maks_chat', self::DEFAULT_MAKS_CHAT));
    }

    /** Template balasan cepat aktif (dikelola di Konsultasi Online > Pengaturan). @return array<int, array{judul: string, isi: string}> */
    public static function balasanCepat(): array
    {
        return \App\ChatQuickReply::where('aktif', true)->orderBy('urutan')->orderBy('id')->get(['judul', 'isi'])
            ->map(fn ($r) => ['judul' => $r->judul, 'isi' => $r->isi])->all();
    }

    /** Lama riwayat disimpan (hari) sebelum dihapus otomatis; 0 = tidak pernah dihapus. */
    public static function retensiHari(): int
    {
        return max(0, (int) ChatSetting::get('retensi_hari', self::DEFAULT_RETENSI_HARI));
    }

    /** Chat tanpa aktivitas selama ini (jam) ditutup otomatis; 0 = nonaktif. */
    public static function tutupOtomatisJam(): int
    {
        return max(0, (int) ChatSetting::get('tutup_otomatis_jam', self::DEFAULT_TUTUP_OTOMATIS_JAM));
    }

    /** Catat bahwa petugas sedang aktif (dipanggil setiap polling panel petugas). */
    public static function heartbeat(int $userId): ChatAgentStatus
    {
        $status = ChatAgentStatus::firstOrCreate(['user_id' => $userId], ['status' => 'offline']);
        $status->forceFill(['last_seen_at' => now()])->save();

        return $status;
    }

    /** Bagikan antrian ke petugas online dengan beban paling sedikit (di bawah batas). Mengembalikan jumlah chat yang dibagikan. */
    public static function dispatch(): int
    {
        $agents = self::onlineAgents();
        // Di luar jam layanan chat dilayani chatbot; petugas tetap bisa mengambil chat secara manual.
        if ($agents->isEmpty() || ! ChatHours::buka()) {
            return 0;
        }

        $maks = self::maksChat();
        $beban = ChatSession::where('status', 'dilayani')->whereIn('agent_id', $agents->pluck('user_id'))
            ->selectRaw('agent_id, count(*) as total')->groupBy('agent_id')->pluck('total', 'agent_id');
        $beban = $agents->mapWithKeys(fn (ChatAgentStatus $a) => [$a->user_id => (int) ($beban[$a->user_id] ?? 0)])->all();

        $dibagi = 0;
        foreach (ChatSession::where('status', 'menunggu')->where('balas_email', false)->orderBy('id')->get() as $sesi) {
            asort($beban);
            $id = array_key_first($beban);
            if ($beban[$id] >= $maks) {
                break; // semua petugas penuh
            }

            self::tugaskan($sesi, $id);
            $beban[$id]++;
            $dibagi++;
        }

        return $dibagi;
    }

    /** Jadikan petugas sebagai penanggung jawab chat. */
    public static function tugaskan(ChatSession $sesi, int $agentId, ?string $pesan = null): void
    {
        $nama = ChatAgentStatus::where('user_id', $agentId)->first()?->user?->name ?? \App\User::find($agentId)?->name ?? 'Petugas';
        $sesi->update(['status' => 'dilayani', 'agent_id' => $agentId, 'taken_at' => $sesi->taken_at ?? now()]);
        $sesi->addMessage('system', $pesan ?? "Anda terhubung dengan {$nama}. Silakan sampaikan pertanyaan Anda.");
    }

    /** Rata-rata durasi layanan (menit) dari sesi selesai 7 hari terakhir; bawaan 5 menit. */
    public static function rataDurasiMenit(): float
    {
        $sesi = ChatSession::where('status', 'selesai')->whereNotNull('taken_at')->whereNotNull('closed_at')
            ->where('closed_at', '>=', now()->subDays(7))->get(['taken_at', 'closed_at']);

        if ($sesi->isEmpty()) {
            return (float) self::DEFAULT_MENIT_PER_CHAT;
        }

        $menit = $sesi->avg(fn ($s) => max(0, $s->taken_at->diffInSeconds($s->closed_at)) / 60);

        return $menit > 0 ? round($menit, 1) : (float) self::DEFAULT_MENIT_PER_CHAT;
    }

    /**
     * Info antrian untuk pengunjung: posisi, estimasi (posisi × rata-rata durasi ÷ petugas online), dan lama menunggu.
     *
     * @return array{position: int, estimate_min: int, agents_online: int, waited_sec: int, offer_email: bool}|null
     */
    public static function info(ChatSession $sesi): ?array
    {
        if ($sesi->status !== 'menunggu') {
            return null;
        }

        $posisi = ChatSession::where('status', 'menunggu')->where('balas_email', false)->where('id', '<=', $sesi->id)->count();
        $online = self::onlineAgents()->count();
        $waited = (int) $sesi->created_at->diffInSeconds(now());

        return [
            'position' => max(1, $posisi),
            'estimate_min' => (int) max(1, ceil(max(1, $posisi) * self::rataDurasiMenit() / max(1, $online))),
            'agents_online' => $online,
            'waited_sec' => $waited,
            'offer_email' => ! $sesi->balas_email && $waited >= self::TAWARKAN_EMAIL_DETIK,
        ];
    }
}
