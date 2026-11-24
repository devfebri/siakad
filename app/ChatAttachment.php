<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChatAttachment extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $fillable = ['message_id', 'path', 'nama_asli', 'mime', 'ukuran'];
}
