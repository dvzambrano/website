<?php

namespace Modules\GutoTradeBot\Entities;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class Comments extends Model
{
    use TenantTrait;

    protected $table = "comments";
    protected $fillable = ['comment', 'screenshot', 'sender_id', 'payment_id', 'data'];

    protected $casts = [
        'data' => 'json',
    ];

    public $timestamps = true;

}
