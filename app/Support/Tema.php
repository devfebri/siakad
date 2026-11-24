<?php

namespace App\Support;

/** Tema warna aplikasi (admin, guru, siswa, situs publik, login). Dipilih admin di Pengaturan > Identitas Sekolah. */
class Tema
{
    public const BAWAAN = 'navy';

    /** kode => [nama, warna utama, aksen, aksen gelap, aksen lembut] */
    public const PALET = [
        'navy' => ['Biru Navy', '#1e3a8a', '#f59e0b', '#d97706', '#fef3c7'],
        'hijau' => ['Hijau Zamrud', '#166534', '#eab308', '#ca8a04', '#fef9c3'],
        'marun' => ['Merah Marun', '#9f1239', '#f59e0b', '#d97706', '#fef3c7'],
        'ungu' => ['Ungu Royal', '#5b21b6', '#f97316', '#ea580c', '#ffedd5'],
        'teal' => ['Teal Segar', '#0f766e', '#f43f5e', '#e11d48', '#ffe4e6'],
        'cokelat' => ['Cokelat Hangat', '#92400e', '#14b8a6', '#0d9488', '#ccfbf1'],
        'slate' => ['Abu Kelam', '#1e293b', '#38bdf8', '#0284c7', '#e0f2fe'],
    ];

    /** Kode tema aktif (kode tak dikenal jatuh ke bawaan). */
    public static function kode(): string
    {
        $kode = (string) config('school.tema_warna');

        return isset(self::PALET[$kode]) ? $kode : self::BAWAAN;
    }

    /** Warna utama tema aktif (hex), untuk email dan PDF. */
    public static function warna(): string
    {
        return self::PALET[self::kode()][1];
    }

    /** Warna aksen tema aktif (hex), untuk grafik. */
    public static function aksen(): string
    {
        return self::PALET[self::kode()][2];
    }

    /** Pilihan untuk select pengaturan: kode => nama. */
    public static function opsi(): array
    {
        return array_map(fn ($p) => $p[0].' ('.$p[1].')', self::PALET);
    }

    /** Deklarasi variabel CSS tema aktif. */
    public static function css(): string
    {
        [, $brand, $aksen, $gelap, $lembut] = self::PALET[self::kode()];

        return ":root{--brand:{$brand};--accent:{$aksen};--accent-dark:{$gelap};--accent-soft:{$lembut};}";
    }
}
