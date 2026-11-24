<?php

namespace App\Support;

use App\Http\Middleware\ApplySiteSettings;
use App\Sekolah;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Rule;

/**
 * Sekolah yang sedang dilayani pada request/proses ini (konteks tenant). Singleton di container.
 * Selama konteks terisi, model ber-trait BelongsToSekolah otomatis difilter dan diisi sekolah_id-nya.
 * Tanpa konteks (console, panel platform) tidak ada filter sekolah.
 */
class SekolahAktif
{
    private ?Sekolah $sekolah = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    public function set(?Sekolah $sekolah): void
    {
        $this->sekolah = $sekolah;
    }

    public function get(): ?Sekolah
    {
        return $this->sekolah;
    }

    public function id(): ?int
    {
        return $this->sekolah?->id;
    }

    /**
     * Jalankan callback dalam konteks sekolah tertentu (mis. antrian email, seeder), lalu pulihkan konteks dan config semula.
     * Pengaturan situs sekolah itu ikut diterapkan ke config('school.*') selama callback berjalan.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function jalankan(Sekolah|int|null $sekolah, callable $callback): mixed
    {
        $sekolah = is_int($sekolah) ? Sekolah::find($sekolah) : $sekolah;
        $konteks = self::instance();
        $lama = $konteks->get();
        $configLama = config('school');

        $konteks->set($sekolah);
        if ($sekolah) {
            ApplySiteSettings::apply();
        }

        try {
            return $callback();
        } finally {
            $konteks->set($lama);
            config(['school' => $configLama]);
        }
    }

    /** Aturan validasi unique yang dibatasi pada sekolah aktif (NISN, NIP, username, dll. unik per sekolah). */
    public static function unik(string $tabel, string $kolom): Unique
    {
        return Rule::unique($tabel, $kolom)->where(fn ($q) => $q->where('sekolah_id', self::instance()->id()));
    }

    /** Aturan validasi exists yang dibatasi pada sekolah aktif (id kelas/mapel/guru milik sekolah lain ditolak). */
    public static function ada(string $tabel, string $kolom = 'id'): Exists
    {
        return Rule::exists($tabel, $kolom)->where(fn ($q) => $q->where('sekolah_id', self::instance()->id()));
    }
}
