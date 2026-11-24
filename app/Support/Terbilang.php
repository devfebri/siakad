<?php

namespace App\Support;

/** Angka menjadi kalimat rupiah dalam Bahasa Indonesia, untuk kuitansi. Contoh: 1080000 menjadi "satu juta delapan puluh ribu rupiah". */
class Terbilang
{
    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(int $n): string
    {
        return $n === 0 ? 'nol rupiah' : trim(preg_replace('/\s+/', ' ', self::angka($n))).' rupiah';
    }

    private static function angka(int $n): string
    {
        return match (true) {
            $n < 12 => self::SATUAN[$n],
            $n < 20 => self::angka($n - 10).' belas',
            $n < 100 => self::angka(intdiv($n, 10)).' puluh '.self::angka($n % 10),
            $n < 200 => 'seratus '.self::angka($n - 100),
            $n < 1000 => self::angka(intdiv($n, 100)).' ratus '.self::angka($n % 100),
            $n < 2000 => 'seribu '.self::angka($n - 1000),
            $n < 1000000 => self::angka(intdiv($n, 1000)).' ribu '.self::angka($n % 1000),
            $n < 1000000000 => self::angka(intdiv($n, 1000000)).' juta '.self::angka($n % 1000000),
            $n < 1000000000000 => self::angka(intdiv($n, 1000000000)).' miliar '.self::angka($n % 1000000000),
            default => self::angka(intdiv($n, 1000000000000)).' triliun '.self::angka($n % 1000000000000),
        };
    }
}
