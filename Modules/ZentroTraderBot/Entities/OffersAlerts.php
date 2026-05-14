<?php

namespace Modules\ZentroTraderBot\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type   buy|sell
 * @property string|null $payment_method
 * @property float|null $max_price
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OffersAlerts extends Model
{
    use TenantTrait;

    protected $table = 'offers_alerts';
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean',
        'max_price'  => 'decimal:2',
    ];
}
