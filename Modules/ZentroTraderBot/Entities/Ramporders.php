<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Ramporders extends Model
{
    use TenantTrait;

    protected $table = 'ramporders';
    protected $fillable = [
        'order_id',
        'bot_id',
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
