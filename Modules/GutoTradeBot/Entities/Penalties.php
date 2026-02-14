<?php

namespace Modules\GutoTradeBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

class Penalties extends Model
{
    use TenantTrait;
    protected $fillable = ['from', 'to', 'amount'];

    public $timestamps = false;

}
