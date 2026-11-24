<?php

namespace App\Console\Commands;

use App\Sekolah;
use App\Wa\Kabar;
use Illuminate\Console\Command;

/**
 * Pengingat tagihan lewat WhatsApp (harian): 3 hari sebelum jatuh tempo, dan tunggakan pada hari ke-1, 8, 15, 22, 29 setelah jatuh tempo.
 * Hanya sekolah yang menyalakan WhatsApp. Pesan yang sama tidak pernah terkirim dua kali, jadi aman dijalankan ulang.
 */
class WaTagihanPengingat extends Command
{
    protected $signature = 'wa:tagihan-pengingat';
    protected $description = 'Kirim pengingat jatuh tempo dan tunggakan tagihan lewat WhatsApp';

    public function handle(): int
    {
        $total = 0;
        foreach (Sekolah::where('status', 'aktif')->get() as $sekolah) {
            $n = Kabar::pengingatTagihan($sekolah);
            if ($n > 0) {
                $this->line("{$sekolah->nama}: {$n} pesan diantrikan.");
            }
            $total += $n;
        }

        $this->info("{$total} pesan pengingat diantrikan.");

        return self::SUCCESS;
    }
}
