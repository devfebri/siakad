<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChatFaq extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $fillable = ['pertanyaan', 'kata_kunci', 'jawaban', 'urutan', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];
}
