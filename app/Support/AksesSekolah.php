<?php

namespace App\Support;

use App\Modul;
use App\Support\Integrasi;

/**
 * Hak akses sekolah aktif berdasarkan langganan: status sewa, mode baca-saja, dan modul paket.
 * Hasil dihitung sekali per request (disimpan di atribut request). Tanpa konteks sekolah (platform/console) tidak ada batasan.
 *
 * @phpstan-type Akses array{status: string, bebas: bool, baca_saja: bool, modul: ?array<int,string>, paket: ?string, berakhir: ?\Illuminate\Support\Carbon, sisa_hari: ?int, tenggang_sampai: ?\Illuminate\Support\Carbon}
 */
class AksesSekolah
{
    /** @return array<string, mixed>|null null bila tidak ada sekolah aktif */
    public static function ambil(): ?array
    {
        $sekolah = SekolahAktif::instance()->get();
        if (! $sekolah) {
            return null;
        }

        $request = request();
        $kunci = 'akses_sekolah_'.$sekolah->id;
        if (! $request->attributes->has($kunci)) {
            $request->attributes->set($kunci, self::hitung($sekolah));
        }

        return $request->attributes->get($kunci);
    }

    /** Buang hasil hitung (dipakai tes dan setelah langganan berubah dalam request yang sama). */
    public static function lupakan(): void
    {
        $sekolah = SekolahAktif::instance()->get();
        if ($sekolah) {
            request()->attributes->remove('akses_sekolah_'.$sekolah->id);
        }
    }

    /** @return array<string, mixed> */
    private static function hitung(\App\Sekolah $sekolah): array
    {
        if ($sekolah->tanpa_batas) {
            return ['status' => 'aktif', 'bebas' => true, 'baca_saja' => false, 'modul' => null, 'paket' => null, 'berakhir' => null, 'sisa_hari' => null, 'tenggang_sampai' => null];
        }

        $status = $sekolah->statusLangganan();
        $terkini = $sekolah->langgananTerkini();
        $paket = $terkini?->paket?->load('modul');

        return [
            'status' => $status,
            'bebas' => false,
            'baca_saja' => in_array($status, ['baca_saja', 'belum_ada'], true),
            'modul' => $paket ? $paket->kodeModul() : [],
            'paket' => $paket?->nama,
            'berakhir' => $terkini?->berakhir,
            'sisa_hari' => $terkini ? (int) today()->diffInDays($terkini->berakhir, false) : null,
            'tenggang_sampai' => $terkini?->berakhir->copy()->addDays(\App\Sekolah::TENGGANG_HARI),
        ];
    }

    /** Hak akses untuk sekolah tertentu (di luar request sekolah itu: antrian, API, console). */
    public static function untuk(\App\Sekolah $sekolah): array
    {
        return self::hitung($sekolah);
    }

    /** Apakah modul termasuk paket sekolah tertentu. */
    public static function modulUntuk(\App\Sekolah $sekolah, string $kode): bool
    {
        $akses = self::hitung($sekolah);

        return $akses['modul'] === null || in_array($kode, $akses['modul'], true);
    }

    /** Apakah modul termasuk paket sekolah aktif (selalu true tanpa sekolah aktif atau bila tanpa batas). */
    public static function modul(string $kode): bool
    {
        $akses = self::ambil();

        return $akses === null || $akses['modul'] === null || in_array($kode, $akses['modul'], true);
    }

    public static function bacaSaja(): bool
    {
        return (bool) (self::ambil()['baca_saja'] ?? false);
    }

    public static function namaModul(string $kode): string
    {
        return Modul::where('kode', $kode)->value('nama') ?? $kode;
    }

    /** Saring item menu: buang item yang modulnya tidak ada di paket, lalu judul bagian yang jadi kosong. */
    public static function saringMenu(array $items): array
    {
        $hasil = [];
        foreach ($items as $item) {
            if (isset($item['header'])) {
                $hasil[] = $item;

                continue;
            }
            if ((! isset($item['modul']) || self::modul($item['modul'])) && (($item['syarat'] ?? null) !== 'elearning' || Integrasi::alamatElearning())) {
                $hasil[] = $item;
            }
        }

        // judul tanpa item di bawahnya dibuang
        $bersih = [];
        foreach ($hasil as $i => $item) {
            if (isset($item['header']) && (! isset($hasil[$i + 1]) || isset($hasil[$i + 1]['header']))) {
                continue;
            }
            $bersih[] = $item;
        }

        return $bersih;
    }
}
