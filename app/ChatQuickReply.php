<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Template balasan cepat untuk petugas konsultasi online. */
class ChatQuickReply extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'chat_quick_replies';
    protected $fillable = ['judul', 'isi', 'urutan', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];
}
