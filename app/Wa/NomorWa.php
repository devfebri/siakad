<?php

namespace App\Wa;

/** Penyeragam nomor telepon Indonesia ke format WhatsApp 62xxxxxxxxxx. */
class NomorWa
{
    /** @return string|null null bila bukan nomor ponsel Indonesia yang sah */
    public static function normalkan(?string $mentah): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $mentah);
        if ($d === '') {
            return null;
        }

        $d = match (true) {
            str_starts_with($d, '62') => $d,
            str_starts_with($d, '0') => '62'.substr($d, 1),
            str_starts_with($d, '8') => '62'.$d,
            default => $d,
        };

        return preg_match('/^628[0-9]{7,11}$/', $d) ? $d : null;
    }
}
