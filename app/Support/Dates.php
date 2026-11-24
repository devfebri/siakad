<?php

namespace App\Support;

use Carbon\Carbon;

class Dates
{
    /** Nilai tanggal dari database (bisa teks lama yang tidak beraturan) menjadi Y-m-d, atau '' bila tidak terbaca. */
    public static function iso(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }
}
