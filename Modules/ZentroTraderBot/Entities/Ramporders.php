<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

/**
 * @property int $id
 * @property string $order_id
 * @property int $bot_id
 * @property int $user_id
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property array|null $raw_data
 * @property string $statusemoji
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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
