<?php

namespace App\Http\Controllers;

use App\ChatSession;
use App\Support\ChatPdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Riwayat percakapan untuk pengunjung tanpa akun: tautan unik (riwayat_token) dari email, unduh PDF, dan rating.
 * Token riwayat berbeda dari token sesi chat, jadi tautan di email tidak membuka akses ke sesi yang masih berjalan.
 */
class ChatRiwayatController extends Controller
{
    public function show(string $token): View
    {
        $sesi = $this->cari($token);

        return view('chat.riwayat', [
            'sesi' => $sesi,
            'pesan' => $sesi->messages()->with(['user:id,name', 'attachments'])->orderBy('id')->get(),
            'urlLampiran' => fn ($a) => URL::temporarySignedRoute('chat.file', now()->addHours(2), ['attachment' => $a->id]),
        ]);
    }

    public function export(string $token)
    {
        $sesi = $this->cari($token);

        return ChatPdf::buat($sesi)->download(ChatPdf::namaBerkas($sesi));
    }

    /** Rating 1–5 + komentar singkat setelah sesi selesai (sekali). Identitas: token sesi (widget) atau token riwayat (email). */
    public function rate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'komentar' => ['nullable', 'string', 'max:300'],
            'token' => ['nullable', 'string', 'size:48'],
            'riwayat' => ['nullable', 'string', 'size:40'],
        ], ['rating.required' => 'Pilih jumlah bintang.', 'rating.between' => 'Penilaian harus 1 sampai 5 bintang.'], ['komentar' => 'komentar']);

        $sesi = match (true) {
            ! empty($data['token']) => ChatSession::where('token', $data['token'])->first(),
            ! empty($data['riwayat']) => ChatSession::where('riwayat_token', $data['riwayat'])->first(),
            default => null,
        };
        abort_unless($sesi, 404, 'Sesi tidak ditemukan.');
        abort_unless($sesi->isClosed(), 409, 'Penilaian dapat diberikan setelah percakapan selesai.');
        abort_if($sesi->rating !== null, 409, 'Terima kasih, Anda sudah memberi penilaian.');

        $komentar = isset($data['komentar']) ? trim(strip_tags($data['komentar'])) : null;
        $sesi->update(['rating' => $data['rating'], 'rating_komentar' => $komentar !== '' ? $komentar : null]);

        return response()->json(['message' => 'Terima kasih atas penilaian Anda!', 'rating' => $sesi->rating]);
    }

    private function cari(string $token): ChatSession
    {
        abort_unless(strlen($token) === 40, 404);

        return ChatSession::where('riwayat_token', $token)->firstOrFail();
    }
}
