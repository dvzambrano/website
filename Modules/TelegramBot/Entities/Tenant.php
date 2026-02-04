<?php

namespace Modules\TelegramBot\Entities;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    // Todos los modelos que hereden de aquí usarán la DB del cliente
    protected $connection = 'tenant';
}
