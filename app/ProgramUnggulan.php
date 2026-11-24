<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProgramUnggulan extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'program_unggulan';
    protected $fillable = ['judul', 'deskripsi', 'ikon', 'urutan', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    /** Ikon Bootstrap Icons yang tersedia untuk program (nama => label). */
    public const IKON = [
        'flask' => 'Sains (labu)',
        'translate' => 'Bahasa',
        'trophy' => 'Prestasi',
        'book' => 'Buku',
        'people' => 'Kegiatan / Komunitas',
        'moon-stars' => 'Keagamaan',
        'palette' => 'Seni',
        'music-note-beamed' => 'Musik',
        'laptop' => 'Teknologi',
        'globe-asia-australia' => 'Wawasan Global',
        'bicycle' => 'Olahraga',
        'pencil' => 'Menulis',
    ];
}
