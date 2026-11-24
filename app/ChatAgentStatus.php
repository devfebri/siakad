<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChatAgentStatus extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'chat_agent_status';
    protected $fillable = ['user_id', 'status', 'last_seen_at'];
    protected $casts = ['last_seen_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
