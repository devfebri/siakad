<?php

namespace App\Observers;

use App\Absensi;
use App\PpdbPendaftar;
use App\Wa\Kabar;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/** Kejadian data yang memicu WhatsApp: siswa alpa dan hasil seleksi PPDB. Keluar cepat bila sekolah belum menyalakan WhatsApp. */
class WaObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void
    {
        if ($model instanceof Absensi && $model->status === 'alpa' && ($model->wasRecentlyCreated || $model->wasChanged('status'))) {
            Kabar::alpa($model);
        }

        if ($model instanceof PpdbPendaftar && in_array($model->status, ['diterima', 'ditolak'], true) && $model->wasChanged('status')) {
            Kabar::ppdb($model);
        }
    }
}
