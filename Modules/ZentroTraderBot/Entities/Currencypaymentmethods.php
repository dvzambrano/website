<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Laravel\Traits\TenantTrait;

class Currencypaymentmethods extends Pivot
{
    use TenantTrait;

    protected $table = 'currencypaymentmethods';

    // Esto permite que Laravel trate la tabla pivot como un modelo con IDs propios
    public $incrementing = true;
}
