<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    use \App\Concerns\BelongsToSekolah;

    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'username', 'email', 'password', 'cek_password', 'role', 'aktif'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'cek_password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'aktif' => 'boolean',
    ];

    /** Panel (area kerja) dan peran yang membukanya, urut prioritas.  */
    public const PANEL = [
        'admin' => [Peran::ADMIN],
        'kepsek' => [Peran::KEPSEK],
        'guru' => [Peran::GURU, Peran::WALI_KELAS],
        'siswa' => [Peran::SISWA],
        'ortu' => [Peran::ORANG_TUA],
        'bendahara' => [Peran::BENDAHARA],
        'platform' => [Peran::SUPER_ADMIN],
    ];

    /** Peran awal dari atribut kompatibilitas `role` (mis. User::create(['role' => 'guru'])), disimpan ke tabel peran setelah akun tersimpan. */
    /** Nama panel untuk tampilan. */
    public const PANEL_NAMA = ['admin' => 'Admin', 'kepsek' => 'Kepala Sekolah', 'guru' => 'Guru', 'siswa' => 'Siswa', 'ortu' => 'Orang Tua', 'bendahara' => 'Bendahara', 'platform' => 'Platform'];

    private ?array $peranAwal = null;

    /** Kode peran yang sudah dimuat (cache per instance). */
    private ?array $kodePeranCache = null;

    protected static function booted(): void
    {
        static::saved(function (User $u) {
            if ($u->peranAwal) {
                foreach ($u->peranAwal as $kode) {
                    $u->tambahPeran($kode);
                }
                $u->peranAwal = null;
            }
        });

        // Akun yang dibuat tanpa username (seeder, factory, impor lama) otomatis mendapat username unik dari email/nama.
        static::creating(function (User $u) {
            if (blank($u->username)) {
                $u->username = static::usernameUnik(explode('@', (string) $u->email)[0] ?: (string) $u->name);
            }
        });
    }

    /**
     * Password: terima teks biasa (di-hash otomatis) atau hash yang sudah jadi.
     * Teks biasa juga disalin apa adanya (tidak dienkripsi) ke cek_password agar pengelola aplikasi dapat memeriksanya.
     */
    public function setPasswordAttribute(?string $nilai): void
    {
        if ($nilai === null || $nilai === '' || \Illuminate\Support\Facades\Hash::info($nilai)['algoName'] !== 'unknown') {
            $this->attributes['password'] = $nilai;

            return;
        }

        $this->attributes['password'] = \Illuminate\Support\Facades\Hash::make($nilai);
        $this->cek_password = $nilai;
    }

    /** Username disimpan huruf kecil tanpa spasi, sehingga login tidak peka huruf besar-kecil di database mana pun. */
    public function setUsernameAttribute(?string $nilai): void
    {
        $this->attributes['username'] = $nilai === null ? null : mb_strtolower(trim($nilai));
    }

    /** Sisakan huruf kecil, angka, titik, garis bawah, dan strip. */
    public static function bersihkan(string $teks): string
    {
        return trim(preg_replace('/[^a-z0-9._-]+/', '', mb_strtolower(trim($teks))), '._-');
    }

    /** Username unik dari teks dasar (ditambah angka bila sudah dipakai). */
    public static function usernameUnik(string $dasar, ?int $abaikanId = null): string
    {
        $dasar = static::bersihkan($dasar);
        $dasar = mb_strlen($dasar) >= 3 ? mb_substr($dasar, 0, 44) : 'user'.$dasar; // minimal 3 karakter

        $username = $dasar;
        for ($i = 2; static::where('username', $username)->when($abaikanId, fn ($q) => $q->whereKeyNot($abaikanId))->exists(); $i++) {
            $username = $dasar.$i;
        }

        return $username;
    }

    // ------------------------------------------------------------ peran (satu akun boleh punya banyak peran)

    public function peran()
    {
        return $this->belongsToMany(Peran::class, 'peran_user')->withTimestamps();
    }

    /** @return array<int, string> kode peran akun ini */
    public function kodePeran(): array
    {
        if ($this->kodePeranCache === null) {
            $this->kodePeranCache = $this->exists ? $this->peran()->pluck('kode')->all() : [];
        }

        return $this->kodePeranCache;
    }

    /** Apakah akun punya salah satu peran berikut. */
    public function punyaPeran(string ...$kode): bool
    {
        return (bool) array_intersect($kode, $this->kodePeran());
    }

    public function tambahPeran(string $kode): void
    {
        $peran = Peran::where('kode', $kode)->first();
        if (! $peran) {
            throw new \InvalidArgumentException("Peran tidak dikenal: {$kode}");
        }

        $this->peran()->syncWithoutDetaching([$peran->id]);
        $this->kodePeranCache = null;
    }

    /** Ganti seluruh peran akun dengan daftar kode ini. */
    public function setPeran(array $kode): void
    {
        $ids = Peran::whereIn('kode', $kode)->pluck('id')->all();
        $this->peran()->sync($ids);
        $this->kodePeranCache = null;
    }

    /** Batasi query pada akun yang punya salah satu peran. */
    public function scopeDenganPeran($query, string ...$kode)
    {
        return $query->whereHas('peran', fn ($q) => $q->whereIn('kode', $kode));
    }

    /** Atribut kompatibilitas: tulis `role` = tambah peran tersebut setelah akun tersimpan. */
    public function setRoleAttribute(?string $kode): void
    {
        if ($kode !== null && $kode !== '') {
            $this->peranAwal = array_values(array_unique(array_merge($this->peranAwal ?? [], [$kode])));
        }
    }

    /** Alias lama `->role`: panel aktif, atau peran pertama bila belum ada panel. Gunakan punyaPeran() untuk otorisasi. */
    public function getRoleAttribute(): ?string
    {
        return $this->panel ?? ($this->kodePeran()[0] ?? null);
    }

    // ------------------------------------------------------------ panel

    /** @return array<int, string> panel yang boleh dibuka akun ini, urut prioritas */
    public function panelTersedia(): array
    {
        return array_values(array_keys(array_filter(self::PANEL, fn ($peran) => $this->punyaPeran(...$peran))));
    }

    /**
     * Panel aktif: mengikuti awalan alamat (/admin, /guru, /siswa) bila diizinkan, lalu pilihan di sesi, lalu prioritas tertinggi.
     * Null bila akun belum punya peran yang membuka panel.
     */
    public function getPanelAttribute(): ?string
    {
        $tersedia = $this->panelTersedia();
        if (! $tersedia) {
            return null;
        }

        $request = request();
        if (in_array($seg = $request->segment(1), $tersedia, true)) {
            return $seg;
        }

        $pilihan = $request->hasSession() ? $request->session()->get('panel_aktif') : null;

        return in_array($pilihan, $tersedia, true) ? $pilihan : $tersedia[0];
    }

    /** Alamat dashboard panel aktif (beranda bila belum punya panel). */
    public function dashboardUrl(): string
    {
        $panel = $this->panel;

        return $panel ? route($panel.'/dashboard') : url('/');
    }

    public function siswa()
    {
        return $this->hasOne(Siswa::class);
    }

    public function orangTua()
    {
        return $this->hasOne(OrangTua::class);
    }

    public function berita()
    {
        return $this->hasMany(Berita::class);
    }
    public function guru()
    {
        return $this->hasOne(Guru::class);
    }
    
}
