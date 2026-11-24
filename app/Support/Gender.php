<?php

namespace App\Support;

class Gender
{
    /** Data lama menyimpan "L"/"P"; form memakai "Laki-Laki"/"Perempuan". */
    public static function label(?string $value): string
    {
        return match (strtoupper(trim((string) $value))) {
            'L', 'LAKI-LAKI', 'LAKI LAKI' => 'Laki-Laki',
            'P', 'PEREMPUAN' => 'Perempuan',
            default => (string) $value,
        };
    }
}
