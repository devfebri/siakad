<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Catatan tindakan penting (platform: login-sebagai, konfirmasi bayar; sekolah: kenaikan, kelulusan, mutasi, nilai, pembayaran).
 * sekolah_id otomatis dari sekolah aktif bila tidak diberikan. */
class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';
    protected $fillable = ['user_id', 'sekolah_id', 'aksi', 'deskripsi', 'data', 'ip'];
    protected $casts = ['data' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public static function catat(string $aksi, string $deskripsi, ?int $sekolahId = null, array $data = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'sekolah_id' => $sekolahId ?? \App\Support\SekolahAktif::instance()->id(),
            'aksi' => $aksi,
            'deskripsi' => mb_substr($deskripsi, 0, 300),
            'data' => $data ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
