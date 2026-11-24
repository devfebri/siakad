<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Satu arsip zip data sebuah sekolah. Tidak terikat sekolah (dibaca panel platform lintas sekolah); controller admin menyaring sendiri. */
class BackupSekolah extends Model
{
    public const JENIS = ['manual' => 'Manual', 'terjadwal' => 'Terjadwal', 'arsip' => 'Arsip sekolah nonaktif'];
    public const STATUS = ['berjalan' => 'Sedang dibuat', 'selesai' => 'Selesai', 'gagal' => 'Gagal'];
    public const WARNA = ['berjalan' => 'warning', 'selesai' => 'success', 'gagal' => 'danger'];

    protected $table = 'backup_sekolah';
    protected $fillable = ['sekolah_id', 'jenis', 'status', 'berkas', 'ukuran', 'checksum', 'jumlah_tabel', 'jumlah_baris', 'jumlah_berkas', 'dibuat_oleh', 'galat'];

    public function sekolah()
    {
        return $this->belongsTo(Sekolah::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh')->withoutGlobalScopes();
    }

    /** Berkas zip masih ada di penyimpanan. */
    public function adaBerkas(): bool
    {
        return $this->status === 'selesai' && $this->berkas && Storage::disk('local')->exists($this->berkas);
    }

    public function namaUnduh(): string
    {
        return basename((string) $this->berkas) ?: 'backup-'.$this->id.'.zip';
    }
}
