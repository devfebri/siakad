<?php

namespace App\Console\Commands;

use App\Backup\Pencadangan;
use App\BackupSekolah;
use App\Sekolah;
use Illuminate\Console\Command;

/**
 * Arsip sekolah yang nonaktif 90 hari: membuat satu backup lengkap berjenis "arsip" (tidak dihapus otomatis).
 * Perintah ini TIDAK menghapus data sekolah apa pun; penghapusan, bila diperlukan, dilakukan Super Admin secara sadar sesudah arsip aman.
 */
class SekolahArsipkan extends Command
{
    public const HARI = 90;

    protected $signature = 'sekolah:arsipkan';
    protected $description = 'Backup arsip untuk sekolah yang sudah nonaktif 90 hari';

    public function handle(): int
    {
        $jumlah = 0;
        foreach (Sekolah::where('status', 'nonaktif')->whereNull('diarsipkan_pada')->where('dinonaktifkan_pada', '<=', now()->subDays(self::HARI))->get() as $s) {
            if (BackupSekolah::where('sekolah_id', $s->id)->where('jenis', 'arsip')->whereIn('status', ['berjalan', 'selesai'])->exists()) {
                continue;
            }
            Pencadangan::mulai($s, 'arsip');
            $this->line("{$s->nama}: arsip diantrikan.");
            $jumlah++;
        }

        $this->info("{$jumlah} sekolah diarsipkan.");

        return self::SUCCESS;
    }
}
