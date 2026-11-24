<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $fillable = ['session_id', 'sender', 'user_id', 'body', 'meta', 'read_at'];
    protected $casts = ['read_at' => 'datetime', 'meta' => 'array'];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(ChatAttachment::class, 'message_id');
    }
}
