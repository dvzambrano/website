<?php

namespace Modules\ZentroTraderBot\Entities;

use Modules\TelegramBot\Entities\Actors;
use Modules\Laravel\Traits\TenantTrait;


class Suscriptions extends Actors
{
    use TenantTrait;

    protected $table = "suscriptions";
}
