<?php

namespace App\Jobs;

use App\Backup\Pencadangan;
use App\BackupSekolah;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Mengerjakan satu backup sekolah di antrian (bisa memakan waktu bila berkasnya banyak). Tidak dicoba ulang otomatis: gagal = ditandai gagal. */
class BuatBackupSekolah implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(public int $backupId)
    {
    }

    public function handle(): void
    {
        $backup = BackupSekolah::find($this->backupId);
        if ($backup && $backup->status === 'berjalan') {
            Pencadangan::kerjakan($backup);
        }
    }
}
