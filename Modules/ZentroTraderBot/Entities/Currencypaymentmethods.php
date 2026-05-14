<?php

namespace Modules\ZentroTraderBot\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Laravel\Traits\TenantTrait;

/**
 * @property int $id
 * @property int $payment_method_id
 * @property int $currency_id
 * @property float|null $min_limit
 * @property float|null $max_limit
 * @property string|null $instructions
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Currencypaymentmethods extends Pivot
{
    use TenantTrait;

    protected $table = 'currencypaymentmethods';

    // Esto permite que Laravel trate la tabla pivot como un modelo con IDs propios
    public $incrementing = true;
}
