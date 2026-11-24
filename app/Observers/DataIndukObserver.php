<?php

namespace App\Observers;

use App\Jobs\KirimWebhook;
use App\Semester;
use App\Support\Api\Sumber;
use App\Support\Integrasi;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Mengabarkan perubahan data induk (siswa, guru, rombel, jadwal, mapel, tahun ajaran) ke E-Learning lewat antrian. */
class DataIndukObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void
    {
        $this->kabarkan($model, $model->wasRecentlyCreated ? 'created' : 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->kabarkan($model, 'deleted');
    }

    private function kabarkan(Model $model, string $aksi): void
    {
        // Perubahan semester dikabarkan sebagai perubahan tahun ajarannya (semester ikut di dalam datanya).
        if ($model instanceof Semester) {
            $model = $model->tahunAjaran;
            $aksi = 'updated';
            if (! $model) {
                return;
            }
        }

        $sumber = Sumber::untukModel($model);
        $sekolahId = (int) $model->sekolah_id;
        if (! $sumber || ! $sekolahId || ! ($klien = Integrasi::klienWebhook($sekolahId))) {
            return;
        }
        if (! ($sekolah = $model->sekolah) || ! Integrasi::tersedia($sekolah)) {
            return;
        }

        $data = $aksi === 'deleted' ? ['id' => $model->getKey()] : Sumber::petakan($sumber, $model);

        KirimWebhook::dispatch($klien['id'], (string) Str::uuid(), "{$sumber}.{$aksi}", $data);
    }
}
