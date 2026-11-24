<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Timeline extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'timeline';
    protected $fillable = ['author', 'message'];
}
