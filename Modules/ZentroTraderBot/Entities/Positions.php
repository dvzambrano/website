<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;
use Carbon\Carbon;

/**
 * @property int $id
 * @property array|null $data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Positions extends Model
{
    use TenantTrait;

    protected $table = 'positions';
    protected $guarded = [];
}
