<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** Catatan callback gerbang yang masuk (sah maupun tidak) untuk audit dan pencegahan pemrosesan ganda. */
class CallbackGateway extends Model
{
    protected $table = 'callback_gateway';
    protected $fillable = ['sekolah_id', 'driver', 'kunci', 'order_id', 'status', 'valid', 'hasil', 'payload', 'ip'];
    protected $casts = ['valid' => 'boolean'];
}
