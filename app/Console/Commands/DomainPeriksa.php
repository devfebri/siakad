<?php

namespace App\Console\Commands;

use App\Sekolah;
use App\Support\DomainKustom;
use Illuminate\Console\Command;

/**
 * Cek otomatis domain kustom yang belum terverifikasi (maksimal 7 hari sejak didaftarkan), supaya sekolah tidak perlu menekan
 * tombol berulang kali menunggu DNS menyebar. Dijadwalkan tiap jam.
 */
class DomainPeriksa extends Command
{
    protected $signature = 'domain:periksa';
    protected $description = 'Cek DNS domain kustom yang menunggu verifikasi';

    public function handle(): int
    {
        $diverifikasi = 0;
        $menunggu = Sekolah::whereNotNull('domain_kustom')->whereNull('domain_terverifikasi_pada')->where('status', 'aktif')->where('updated_at', '>=', now()->subDays(7))->get();

        foreach ($menunggu as $sekolah) {
            if (DomainKustom::periksa($sekolah)['terverifikasi']) {
                $this->line("{$sekolah->nama}: {$sekolah->domain_kustom} terverifikasi.");
                $diverifikasi++;
            }
        }

        $this->info("{$diverifikasi} dari {$menunggu->count()} domain terverifikasi.");

        return self::SUCCESS;
    }
}
