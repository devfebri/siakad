<?php

namespace App\Wa\Driver;

use App\Wa\HasilKirim;
use App\Wa\WhatsAppSender;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Cloud API (Meta): access token dan Phone Number ID.
 * Pesan teks bebas hanya diterima WhatsApp bila penerima menghubungi nomor bisnis dalam 24 jam terakhir;
 * di luar itu WhatsApp mewajibkan templat pesan yang disetujui Meta (belum didukung di sini).
 */
class CloudSender implements WhatsAppSender
{
    public function __construct(private string $token, private string $nomorId)
    {
    }

    public function kode(): string
    {
        return 'cloud';
    }

    public function kirim(string $nomor, string $pesan): HasilKirim
    {
        try {
            $res = Http::withToken($this->token)->acceptJson()->timeout(15)->post("https://graph.facebook.com/v20.0/{$this->nomorId}/messages", [
                'messaging_product' => 'whatsapp', 'to' => $nomor, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $pesan],
            ]);
        } catch (\Throwable) {
            return HasilKirim::gagal('WhatsApp Cloud API tidak bisa dihubungi.', true);
        }

        if ($res->serverError() || $res->status() === 429) {
            return HasilKirim::gagal('WhatsApp Cloud API sedang sibuk (kode '.$res->status().').', true);
        }
        if ($res->successful() && $res->json('messages.0.id')) {
            return HasilKirim::berhasil((string) $res->json('messages.0.id'));
        }

        return HasilKirim::gagal('WhatsApp menolak: '.($res->json('error.message') ?: 'kode '.$res->status()));
    }
}
