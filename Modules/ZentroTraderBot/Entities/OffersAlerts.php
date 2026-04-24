<?php

namespace Modules\ZentroTraderBot\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

/**
 * @property int $id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class OffersAlerts extends Model
{
    use TenantTrait;

    protected $table = 'offers_alerts';
    protected $guarded = [];
}
