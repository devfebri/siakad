<?php

namespace App\Scopes;

use App\Support\SekolahAktif;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Batasi query model pada sekolah aktif. Tanpa konteks sekolah (console/platform) tidak memfilter. */
class SekolahScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $id = SekolahAktif::instance()->id();

        if ($id !== null) {
            $builder->where($model->getTable().'.sekolah_id', $id);
        }
    }
}
