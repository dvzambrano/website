<?php
namespace Modules\GutoTradeBot\Entities;

use Modules\Laravel\Traits\TenantTrait;

class Accounts extends Jsons
{
    use TenantTrait;
    protected $table = "accounts";

    protected $fillable = ['bank', 'name', 'number', 'detail', 'is_active', 'data'];

    public $timestamps = false;
    protected $attributes = [
        'data' => '[]',
    ];
}
