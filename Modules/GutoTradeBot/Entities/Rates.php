<?php
namespace Modules\GutoTradeBot\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Rates extends Model
{
    use TenantTrait;

    protected $fillable = ['date', 'base', 'coin', 'rate'];

    public $timestamps = false;

}
