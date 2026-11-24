<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Sekolah penyewa (tenant). Tabel ini tidak terikat sekolah_id. */
class Sekolah extends Model
{
    public const STATUS = ['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];
    public const JENJANG = ['SD', 'SMP', 'SMA', 'SMK', 'MI', 'MTs', 'MA', 'Lainnya'];

    /** Subdomain yang tidak boleh dipakai sekolah (alamat milik platform). */
    public const SUBDOMAIN_DICADANGKAN = ['www', 'platform', 'admin', 'api', 'mail', 'app', 'daftar', 'produk', 'login', 'elearning', 'static', 'cdn', 'ftp', 'smtp'];

    /** Masa tenggang (hari) setelah langganan berakhir sebelum mode baca-saja. */
    public const TENGGANG_HARI = 7;

    protected $table = 'sekolah';
    protected $fillable = ['nama', 'npsn', 'jenjang', 'subdomain', 'domain_kustom', 'status', 'tanpa_batas'];
    protected $casts = ['tanpa_batas' => 'boolean', 'domain_terverifikasi_pada' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->domain_token ??= \Illuminate\Support\Str::random(40);
        });

        // Dasar arsip otomatis: kapan sekolah mulai nonaktif (dikosongkan lagi bila diaktifkan kembali).
        static::saving(function (self $s) {
            if ($s->isDirty('status')) {
                $s->dinonaktifkan_pada = $s->status === 'nonaktif' ? now() : null;
                if ($s->status === 'aktif') {
                    $s->diarsipkan_pada = null;
                }
            }
        });

        // Domain kustom yang diganti harus dibuktikan ulang (token baru), kecuali penanda verifikasi ikut diatur sengaja (verifikasi/penghapusan).
        static::saving(function (self $s) {
            if ($s->isDirty('domain_kustom') && ! $s->isDirty('domain_terverifikasi_pada')) {
                $s->domain_terverifikasi_pada = null;
                $s->domain_token = \Illuminate\Support\Str::random(40);
            }
        });
    }

    /** Domain kustom yang sudah terbukti milik sekolah (satu-satunya yang dipakai mengenali sekolah dan membangun alamat). */
    public function domainAktif(): ?string
    {
        return $this->domain_kustom && $this->domain_terverifikasi_pada ? $this->domain_kustom : null;
    }

    public function isAktif(): bool
    {
        return $this->status === 'aktif';
    }

    public function langganan()
    {
        return $this->hasMany(Langganan::class);
    }

    public function invoice()
    {
        return $this->hasMany(Invoice::class);
    }

    /** Langganan aktif dengan tanggal berakhir paling akhir (bisa sudah lewat tanggal berakhir: lihat statusLangganan). */
    public function langgananTerkini(): ?Langganan
    {
        return $this->langganan()->where('status', 'aktif')->orderByDesc('berakhir')->orderByDesc('id')->with('paket')->first();
    }

    /**
     * Status sewa pada suatu hari:
     *  aktif (belum lewat tanggal berakhir), tenggang (lewat sampai TENGGANG_HARI hari), baca_saja (lewat tenggang), belum_ada.
     */
    public function statusLangganan(?Carbon $hari = null): string
    {
        $langganan = $this->langgananTerkini();
        if (! $langganan) {
            return 'belum_ada';
        }

        $hari = ($hari ?? now())->copy()->startOfDay();
        $berakhir = $langganan->berakhir->copy()->startOfDay();

        return match (true) {
            $hari->lte($berakhir) => 'aktif',
            $hari->lte($berakhir->copy()->addDays(self::TENGGANG_HARI)) => 'tenggang',
            default => 'baca_saja',
        };
    }

    /** Admin sekolah pertama (akun yang dipakai untuk login-sebagai). */
    public function adminPertama(): ?User
    {
        return User::withoutGlobalScopes()->where('sekolah_id', $this->id)->denganPeran(Peran::ADMIN)->orderBy('id')->first();
    }

    /** Alamat situs sekolah: domain kustom (yang sudah terverifikasi), subdomain di domain utama, atau (lokal) alamat saat ini dengan ?sekolah=. */
    public function url(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $utama = config('sekolah.domain_utama');
        $skema = request()->getScheme();

        if ($domain = $this->domainAktif()) {
            return $skema.'://'.$domain.$path;
        }
        if ($utama) {
            return $skema.'://'.$this->subdomain.'.'.$utama.$path;
        }

        return rtrim(request()->root(), '/').$path.(str_contains($path, '?') ? '&' : '?').'sekolah='.$this->subdomain;
    }
}
