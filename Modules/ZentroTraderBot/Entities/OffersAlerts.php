<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class OffersAlerts extends Model
{
    use TenantTrait;

    protected $table = 'offers_alerts';
    protected $guarded = [];
}
