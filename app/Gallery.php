<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Gallery extends Model
{
    use \App\Concerns\BelongsToSekolah;

    protected $table = 'gallery';
    protected $fillable = ['author', 'foto'];
}
