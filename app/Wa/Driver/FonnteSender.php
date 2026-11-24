<?php

namespace App\Wa\Driver;

use App\Wa\HasilKirim;
use App\Wa\WhatsAppSender;
use Illuminate\Support\Facades\Http;

/** Fonnte (api.fonnte.com): cukup token perangkat. */
class FonnteSender implements WhatsAppSender
{
    public function __construct(private string $token)
    {
    }

    public function kode(): string
    {
        return 'fonnte';
    }

    public function kirim(string $nomor, string $pesan): HasilKirim
    {
        try {
            $res = Http::withHeaders(['Authorization' => $this->token])->timeout(15)->asForm()->post('https://api.fonnte.com/send', ['target' => $nomor, 'message' => $pesan, 'countryCode' => '62']);
        } catch (\Throwable) {
            return HasilKirim::gagal('Fonnte tidak bisa dihubungi.', true);
        }

        if ($res->serverError()) {
            return HasilKirim::gagal('Fonnte sedang bermasalah (kode '.$res->status().').', true);
        }
        if ($res->json('status') === true) {
            return HasilKirim::berhasil((string) (is_array($res->json('id')) ? ($res->json('id')[0] ?? '') : $res->json('id')));
        }

        return HasilKirim::gagal('Fonnte menolak: '.($res->json('reason') ?: 'kode '.$res->status()));
    }
}
