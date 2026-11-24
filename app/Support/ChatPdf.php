<?php

namespace App\Support;

use App\ChatSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDokumen;

/** Transkrip konsultasi online dalam PDF dengan kop sekolah. */
class ChatPdf
{
    public static function buat(ChatSession $sesi): PdfDokumen
    {
        $logo = config('school.logo') ? public_path(config('school.logo')) : null;

        return Pdf::loadView('chat.pdf', [
            'sesi' => $sesi,
            'pesan' => $sesi->messages()->with(['user:id,name', 'attachments'])->whereIn('sender', ['visitor', 'agent', 'bot', 'system'])->orderBy('id')->get(),
            'sekolah' => config('school.name'),
            'alamat' => config('school.alamat'),
            'kontak' => collect([config('school.telepon'), config('school.email')])->filter()->implode(' · '),
            'warna' => \App\Support\Tema::warna(),
            'logo' => $logo && is_file($logo) ? $logo : null,
        ])->setPaper('a4');
    }

    public static function namaBerkas(ChatSession $sesi): string
    {
        return 'konsultasi-'.$sesi->id.'-'.$sesi->created_at?->format('Ymd').'.pdf';
    }
}
