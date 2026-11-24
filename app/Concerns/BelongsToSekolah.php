<?php

namespace App\Concerns;

use App\Scopes\SekolahScope;
use App\Sekolah;
use App\Support\SekolahAktif;

/**
 * Model data milik satu sekolah: query otomatis difilter sekolah aktif dan sekolah_id terisi otomatis saat dibuat.
 * Untuk melewati filter (platform/console) pakai Model::withoutGlobalScope(SekolahScope::class).
 */
trait BelongsToSekolah
{
    public static function bootBelongsToSekolah(): void
    {
        static::addGlobalScope(new SekolahScope());

        static::creating(function ($model) {
            if ($model->sekolah_id === null && ($id = SekolahAktif::instance()->id()) !== null) {
                $model->sekolah_id = $id;
            }
        });
    }

    public function initializeBelongsToSekolah(): void
    {
        $this->mergeFillable(['sekolah_id']);
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }
}
