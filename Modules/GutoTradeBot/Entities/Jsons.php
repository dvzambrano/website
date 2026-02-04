<?php

namespace Modules\GutoTradeBot\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Jsons extends Model
{
    use TenantTrait;

    protected $casts = [
        'data' => 'json',
    ];
}
