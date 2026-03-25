<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Traits\TenantTrait;

class Offers extends Model
{
    use TenantTrait;

    protected $table = 'offers';
    protected $guarded = [];
    protected $casts = [
        'data' => 'json',
    ];

    public function scopeFilter($query, $filters)
    {
        return $query->where('status', 'active')
            ->when($filters['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($filters['method'] ?? null, fn($q, $m) => $q->where('payment_method', $m))
            ->orderBy($filters['sort'] ?? 'price_per_usd', 'asc');
    }

    public function updateStatus($status, $extra = [])
    {
        $this->update(array_merge(['status' => $status], $extra));
    }
}
