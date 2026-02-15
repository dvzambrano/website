<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

class Positions extends Model
{
    use TenantTrait;

    protected $table = 'positions';
    protected $guarded = [];
}
