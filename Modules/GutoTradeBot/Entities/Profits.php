<?php
namespace Modules\GutoTradeBot\Entities;


use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;


class Profits extends Model
{
    use TenantTrait;

    protected $fillable = ['name', 'comment', 'value'];

    public $timestamps = false;

}
