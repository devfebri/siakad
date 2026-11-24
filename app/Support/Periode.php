<?php

namespace App\Support;

use App\Semester;
use App\TahunAjaran;
use Carbon\CarbonInterface;

/** Tahun ajaran Ditutup bersifat baca-saja: jadwal, absensi, nilai, dan keanggotaan rombelnya tidak dapat diubah. */
class Periode
{
    public static function pastikanTerbuka(TahunAjaran|Semester|int|null $periode): void
    {
        $ta = match (true) {
            $periode instanceof Semester => $periode->tahunAjaran,
            $periode instanceof TahunAjaran => $periode,
            is_int($periode) => TahunAjaran::find($periode),
            default => null,
        };

        if ($ta?->isDitutup()) {
            abort(409, "Tahun ajaran {$ta->nama} sudah ditutup (baca-saja). Data periode ini tidak dapat diubah.");
        }
    }

    public static function pastikanTanggalTerbuka(CarbonInterface|string $tanggal): void
    {
        self::pastikanTerbuka(Semester::untukTanggal($tanggal));
    }
}
