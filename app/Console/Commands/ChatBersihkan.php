<?php

namespace App\Console\Commands;

use App\ChatAttachment;
use App\ChatMessage;
use App\ChatSession;
use App\Support\ChatQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Perawatan harian konsultasi online:
 *  1. menutup otomatis chat yang tidak ada aktivitas selama N jam (agar tidak membebani batas chat petugas),
 *  2. menghapus percakapan (beserta pesan dan lampiran) yang lebih lama dari masa simpan (bawaan 365 hari).
 * Dijadwalkan harian lewat routes/console.php (cron `schedule:run`).
 */
class ChatBersihkan extends Command
{
    protected $signature = 'chat:bersihkan {--kering : Hanya tampilkan jumlahnya, tanpa mengubah data}';
    protected $description = 'Tutup chat yang menggantung dan hapus riwayat konsultasi online yang melewati masa simpan';

    public function handle(): int
    {
        $kering = (bool) $this->option('kering');

        // 1. tutup otomatis
        $jam = ChatQueue::tutupOtomatisJam();
        $menggantung = $jam > 0
            ? ChatSession::whereIn('status', ['menunggu', 'dilayani'])->where('updated_at', '<', now()->subHours($jam))->whereDoesntHave('messages', fn ($q) => $q->where('created_at', '>=', now()->subHours($jam)))->get()
            : collect();

        if (! $kering) {
            foreach ($menggantung as $s) {
                $s->update(['status' => 'selesai', 'closed_at' => now()]);
                $s->addMessage('system', 'Percakapan ditutup otomatis karena tidak ada aktivitas.');
            }
        }

        // 2. hapus riwayat lama
        $hari = ChatQueue::retensiHari();
        $kedaluwarsa = $hari > 0
            ? ChatSession::where('updated_at', '<', now()->subDays($hari))->pluck('id')
            : collect();

        if (! $kering && $kedaluwarsa->isNotEmpty()) {
            foreach ($kedaluwarsa->chunk(200) as $ids) {
                $pesan = ChatMessage::whereIn('session_id', $ids)->pluck('id');
                $berkas = ChatAttachment::whereIn('message_id', $pesan)->pluck('path');
                Storage::disk('local')->delete($berkas->all());

                DB::transaction(function () use ($ids, $pesan) {
                    ChatAttachment::whereIn('message_id', $pesan)->delete();
                    ChatMessage::whereIn('session_id', $ids)->delete();
                    ChatSession::whereIn('id', $ids)->delete();
                });
            }
            foreach ($kedaluwarsa->unique() as $id) {
                Storage::disk('local')->deleteDirectory('chat/'.$id);
            }
        }

        $this->info(($kering ? '[simulasi] ' : '').$menggantung->count().' chat ditutup otomatis, '.$kedaluwarsa->count()." riwayat dihapus (masa simpan {$hari} hari).");

        return self::SUCCESS;
    }
}
