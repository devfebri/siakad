<?php

namespace App\Console\Commands;

use App\Backup\Pencadangan;
use App\BackupSekolah;
use App\PengaturanPlatform;
use App\Sekolah;
use Illuminate\Console\Command;

/**
 * Backup terjadwal semua sekolah aktif (mingguan). Backup dikerjakan di antrian. Sekolah yang sudah punya backup terjadwal
 * dalam 6 hari terakhir dilewati, dan hanya N backup terjadwal terbaru per sekolah yang disimpan (Pengaturan Platform).
 */
class BackupSekolahCommand extends Command
{
    protected $signature = 'backup:sekolah {--sekolah= : ID atau subdomain satu sekolah saja} {--jenis=terjadwal : manual|terjadwal} {--paksa : abaikan jeda 6 hari}';
    protected $description = 'Buat backup (zip) data sekolah dan hapus backup terjadwal yang lewat batas simpan';

    public function handle(): int
    {
        $jenis = in_array($this->option('jenis'), ['manual', 'terjadwal'], true) ? $this->option('jenis') : 'terjadwal';
        $satu = $this->option('sekolah');

        if (! $satu && ! PengaturanPlatform::aktif('backup_terjadwal_aktif')) {
            $this->warn('Backup terjadwal dimatikan di Pengaturan Platform.');

            return self::SUCCESS;
        }

        $sekolah = Sekolah::query()
            ->when($satu, fn ($q) => $q->where(fn ($w) => $w->where('id', is_numeric($satu) ? $satu : 0)->orWhere('subdomain', $satu)), fn ($q) => $q->where('status', 'aktif'))
            ->get();

        $dibuat = 0;
        foreach ($sekolah as $s) {
            $baru = BackupSekolah::where('sekolah_id', $s->id)->where('jenis', 'terjadwal')->whereIn('status', ['berjalan', 'selesai'])->where('created_at', '>=', now()->subDays(6))->exists();
            if ($baru && ! $this->option('paksa') && ! $satu) {
                continue;
            }
            Pencadangan::mulai($s, $jenis);
            $this->line("{$s->nama}: backup {$jenis} diantrikan.");
            $dibuat++;
        }

        $dihapus = Pencadangan::bersihkanTerjadwal((int) PengaturanPlatform::get('backup_simpan_jumlah', 4));
        $this->info("{$dibuat} backup diantrikan, {$dihapus} backup lama dihapus.");

        return self::SUCCESS;
    }
}
