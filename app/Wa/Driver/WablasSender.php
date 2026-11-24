<?php

namespace App\Wa\Driver;

use App\Wa\HasilKirim;
use App\Wa\WhatsAppSender;
use Illuminate\Support\Facades\Http;

/** Wablas: token (+ secret bila ada) dan alamat server milik akun. */
class WablasSender implements WhatsAppSender
{
    public function __construct(private string $token, private string $server, private ?string $secret = null)
    {
    }

    public function kode(): string
    {
        return 'wablas';
    }

    public function kirim(string $nomor, string $pesan): HasilKirim
    {
        $otorisasi = $this->secret ? $this->token.'.'.$this->secret : $this->token;

        try {
            $res = Http::withHeaders(['Authorization' => $otorisasi])->timeout(15)->asForm()->post(rtrim($this->server, '/').'/api/send-message', ['phone' => $nomor, 'message' => $pesan]);
        } catch (\Throwable) {
            return HasilKirim::gagal('Wablas tidak bisa dihubungi.', true);
        }

        if ($res->serverError()) {
            return HasilKirim::gagal('Wablas sedang bermasalah (kode '.$res->status().').', true);
        }
        if ($res->json('status') === true) {
            return HasilKirim::berhasil((string) ($res->json('data.messages.0.id') ?? ''));
        }

        return HasilKirim::gagal('Wablas menolak: '.($res->json('message') ?: 'kode '.$res->status()));
    }
}
