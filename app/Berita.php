<?php

namespace App;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;

class Berita extends Model
{
    use \App\Concerns\BelongsToSekolah;

    use Sluggable;

    /** Folder gambar baru, relatif terhadap public/. Gambar lama ada di storage/berita/{nama kategori}/. */
    public const IMAGE_DIR = 'storage/berita/files';

    protected $casts = ['berita_tanggal' => 'datetime'];
    protected $table = 'berita';
    protected $fillable = [
        'berita_judul', 'berita_isi', 'berita_tanggal', 'kategori_id',
        'berita_views', 'berita_gambar', 'author',
        'berita_img_slider', 'berita_slug',
    ];

    public function sluggable(): array
    {
        return [
            'berita_slug' => [
                'source' => 'berita_judul',
            ],
        ];
    }

    /** URL gambar: folder baru bila ada, kalau tidak folder lama per kategori, kalau tidak gambar bawaan. */
    public function getGambarUrlAttribute(): string
    {
        $file = $this->berita_gambar;
        if (! $file) {
            return asset('images/default.png');
        }

        if (is_file(public_path(self::IMAGE_DIR.'/'.$file))) {
            return asset(self::IMAGE_DIR.'/'.$file);
        }

        return asset('storage/berita/'.($this->kategori?->kategori_nama).'/'.$file);
    }

    /** Semua lokasi yang mungkin menyimpan gambar ini (untuk dihapus). */
    public function gambarPaths(): array
    {
        if (! $this->berita_gambar) {
            return [];
        }

        return [
            public_path(self::IMAGE_DIR.'/'.$this->berita_gambar),
            public_path('storage/berita/'.($this->kategori?->kategori_nama).'/'.$this->berita_gambar),
        ];
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
