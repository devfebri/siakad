<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Berkas yang dapat diunduh pengunjung di menu Unduhan. */
class Unduhan extends Model
{
    use \App\Concerns\BelongsToSekolah;

    public const DIR = 'storage/unduhan';
    public const KATEGORI = ['Formulir', 'Panduan', 'Brosur', 'Materi', 'Pengumuman', 'Umum'];
    public const EKSTENSI = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'jpg', 'jpeg', 'png', 'txt'];
    public const MAKS_KB = 10240;

    protected $table = 'unduhan';
    protected $fillable = ['judul', 'kategori', 'deskripsi', 'berkas', 'nama_asli', 'mime', 'ukuran', 'jumlah_unduh', 'urutan', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    public function getPathAttribute(): string
    {
        return public_path(self::DIR.'/'.$this->berkas);
    }

    public function getEkstensiAttribute(): string
    {
        return strtolower(pathinfo($this->nama_asli, PATHINFO_EXTENSION));
    }

    /** Ikon Bootstrap Icons sesuai jenis berkas. */
    public function getIkonAttribute(): string
    {
        return match ($this->ekstensi) {
            'pdf' => 'file-earmark-pdf',
            'doc', 'docx', 'txt' => 'file-earmark-word',
            'xls', 'xlsx' => 'file-earmark-excel',
            'ppt', 'pptx' => 'file-earmark-slides',
            'zip' => 'file-earmark-zip',
            'jpg', 'jpeg', 'png' => 'file-earmark-image',
            default => 'file-earmark',
        };
    }

    public function getUkuranTeksAttribute(): string
    {
        return $this->ukuran >= 1048576
            ? number_format($this->ukuran / 1048576, 1, ',', '.').' MB'
            : max(1, (int) round($this->ukuran / 1024)).' KB';
    }
}
