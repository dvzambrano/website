<?php

namespace Modules\ZentroTraderBot\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Laravel\Services\DateService;
use Modules\Laravel\Traits\TenantTrait;
use Carbon\Carbon;

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
        $text .= "🆔 `{$this->code}`\n";

        if ($isSell) {
            $text .= "💵 En venta: *{$amount} USD*\n";
        } else {
            $text .= "💵 Se compra: *{$amount} USD*\n";
        }

        $text .= "🔖 Tasa: *{$this->price_per_usd} {$this->currency}/USD*\n";

        if ($isSell) {
            if ($owner) {
                $text .= "📥 Ud recibe: *{$total} {$this->currency}*\n";
            } else {
                $text .= "📤 Ud paga: *{$total} {$this->currency}*\n";
            }
        } else {
            $text .= "📤 Ud entrega: *{$total} {$this->currency}*\n";
        }

        $text .= "💳 Medio de pago: *{$this->payment_method}*\n\n";

        return $text;
    }

    public function getAsChannelMessage($botName)
    {
        // 1. Calculamos la antigüedad de la oferta
        $diff = DateService::getTimeDifference(
            $this->created_at->getTimestamp(),
            now()->getTimestamp()
        );

        $isSell = strtolower($this->type) == "sell";
        $icon = "🟩";
        if ($isSell)
            $icon = "🟥";

        // 2. Lógica de Títulos Dinámicos basada en el tiempo y el status
        $title = "📋 *OFERTA*"; // Default

        if ($this->status === 'open') {
            // Si tiene menos de 1 hora, es "RECIENTE"
            if ($diff['years'] == 0 && $diff['months'] == 0 && $diff['days'] == 0 && $diff['hours'] < 1) {
                $title = "{$icon} *¡OFERTA RECIENTE!* 💥 _{$diff['seconds']}S_";
                if ($diff['minutes'] > 0)
                    $title = "{$icon} *¡OFERTA RECIENTE!* 💥 _{$diff['minutes']}M_";
            }
            // Si tiene entre 1 y 24 horas, es "NUEVA"
            elseif ($diff['years'] == 0 && $diff['months'] == 0 && $diff['days'] == 0) {
                $title = "{$icon} *¡NUEVA OFERTA!* 🔥 _{$diff['hours']}H_";
            }
            // Si tiene más de un día, es "DISPONIBLE"
            else {
                $title = "{$icon} *OFERTA DISPONIBLE* ✨ _{$diff['days']}D{$diff['hours']}H_";
            }
        } elseif ($this->status === 'in_progress') {
            $title = "🟡 *OFERTA EN CURSO*";
        } elseif ($this->status === 'completed') {
            $title = "✅ *OFERTA FINALIZADA*";
        }

        // 3. Renderizar y Editar
        $text = $this->renderAsTelegramMessage($title);
        $text .= "🛡 _Use siempre el sistema de custodia para transacciones 100% seguras en nuestro P2P._\n\n";

        return array(
            "message" => array(
                "text" => $text,
                "chat" => array(
                    "id" => env("TRADER_BOT_CHANNEL"),
                ),
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [
                            [
                                "text" => "👉 Aplicar a esta oferta",
                                'url' => "https://t.me/" . $botName . "?start=offer_{$this->code}"
                            ]
                        ],
                    ],
                ]),
            ),
        );
    }
}
