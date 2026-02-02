<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ModuleTrait;

class Ramporders extends Model
{
    use ModuleTrait;

    protected $table = 'ramporders';
    protected $fillable = [
        'order_id',
        'botname',
        'user_id',
        'amount',
        'currency',
        'status',
        'raw_data'
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];
}
