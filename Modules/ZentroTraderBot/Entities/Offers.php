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

    public function renderAsTelegramMessage($title = "")
    {
        $total = $this->amount * $this->price_per_usd;

        $text = "{$title}\n";
        $text .= "🆔 `" . $this->uuid . "`\n";
        if (strtolower($this->type) == "sell")
            $text .= "💸 En venta: *{$this->amount} USD*\n";
        else
            $text .= "💰 Compra: *{$this->amount} USD*\n";
        $text .= "💱 Tasa: *{$this->price_per_usd} {$this->currency}/USD*\n";
        if (strtolower($this->type) == "sell")
            $text .= "💰 Recibe: *{$total} {$this->currency}*\n";
        else
            $text .= "💸 Entrega: *{$total} {$this->currency}*\n";
        $text .= "🏦 Medio de Pago: *{$this->payment_method}*\n\n";

        return $text;
    }
}
