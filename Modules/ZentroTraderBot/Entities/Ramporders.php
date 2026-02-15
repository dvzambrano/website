<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;

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

    protected $appends = ['statusemoji'];
    protected function statusemoji(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match (strtoupper($this->status)) {
                    'COMPLETED' => '🟢',
                    'FAILED' => '🔴',
                    'PENDING' => '🟡',
                    'PROCESSING' => '🔵',
                    default => '⚪',
                };
            },
        );
    }
}
