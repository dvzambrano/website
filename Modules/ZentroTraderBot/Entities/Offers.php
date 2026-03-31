<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Services\NumberService;
use Modules\Laravel\Traits\TenantTrait;

class Offers extends Model
{
    use TenantTrait;

    protected $table = "offers";
    protected $guarded = [];
    protected $casts = [
        "data" => "json",
    ];

    /**
     * Accesor: Permite obtener el código como $offer->code
     */
    public function getCodeAttribute(): string
    {
        // 1. Extraer o generar los componentes aleatorios persistentes
        // Guardamos el prefijo y sufijo en el campo "data" para que sean fijos
        $prefix = $this->data["code"]["prefix"] ?? "K";
        $suffix = $this->data["code"]["suffix"] ?? "0";

        // 2. Convertir el ID a Base36 con Pad de 6
        $idBase36 = str_pad(strtoupper(base_convert($this->id, 10, 36)), 6, "0", STR_PAD_LEFT);

        // Resultado: A00002Y1
        return "{$prefix}{$idBase36}{$suffix}";
    }

    /**
     * Método estático para encontrar una oferta a partir de su código de soporte
     */
    public static function findByCode(string $code): ?self
    {
        // Si el código es "A00002Y1", extraemos "00002Y"
        $idBase36 = substr($code, 1, -1);

        try {
            $idDecimal = base_convert($idBase36, 36, 10);
            return self::find($idDecimal);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function scopeFilter($query, $filters)
    {
        return $query->where("status", "active")
            ->when($filters["type"] ?? null, fn($q, $t) => $q->where("type", $t))
            ->when($filters["method"] ?? null, fn($q, $m) => $q->where("payment_method", $m))
            ->orderBy($filters["sort"] ?? "price_per_usd", "asc");
    }

    public function updateStatus($status, $extra = [])
    {
        $this->update(array_merge(["status" => $status], $extra));
    }

    public function renderAsTelegramMessage($title = "", $owner = false)
    {
        $total = number_format(($this->amount * $this->price_per_usd), 2);
        $amount = number_format($this->amount, 2);
        $isSell = strtolower($this->type) == "sell";

        $text = "{$title}\n";
        $text .= "🔖 Ticket: `{$this->code}`\n";

        if ($isSell) {
            $text .= "💎 Vendes: *{$amount} USD*\n";
        } else {
            $text .= "💎 Compras: *{$amount} USD*\n";
        }

        $text .= "🏷️ Tasa: *{$this->price_per_usd} {$this->currency}/USD*\n";

        if ($isSell) {
            if ($owner) {
                $text .= "📥 Recibe: *{$total} {$this->currency}*\n";
            } else {
                $text .= "📤 Paga: *{$total} {$this->currency}*\n";
            }
        } else {
            $text .= "📤 Entrega: *{$total} {$this->currency}*\n";
        }

        $text .= "💳 Pago: *{$this->payment_method}*\n\n";

        return $text;
    }
}
