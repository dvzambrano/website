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
    public function scopeAsBuyer($query, $address)
    {
        return $query->whereRaw('LOWER(buyer_address) = ?', [strtolower($address)]);
    }
    public function scopeAsSeller($query, $address)
    {
        return $query->whereRaw('LOWER(seller_address) = ?', [strtolower($address)]);
    }

    public function updateStatus($status, $extra = [])
    {
        $this->update(array_merge(["status" => $status], $extra));
    }

    public function renderAsTelegramMessage($title = "", $owner = false, $stars = "")
    {
        $total = number_format(($this->amount * $this->price_per_usd), 2);
        $amount = number_format($this->amount, 2);
        $isSell = strtolower($this->type) == "sell";

        $text = "{$title}\n";
        $text .= "🆔 `{$this->code}` {$stars}\n";

        if ($isSell) {
            $text .= "💵 En venta: *{$amount} USD*\n";
        } else {
            $text .= "💵 Se compra: *{$amount} USD*\n";
        }

        $text .= "🔖 Tasa: *{$this->price_per_usd} {$this->currency}/USD*\n";

        if ($isSell) {
            if ($owner) {
                $text .= Offers::getTypeEmoji("buy")["icon"] . " Ud recibe: *{$total} {$this->currency}*\n";
            } else {
                $text .= Offers::getTypeEmoji("sell")["icon"] . " Ud paga: *{$total} {$this->currency}*\n";
            }
        } else {
            $text .= Offers::getTypeEmoji("sell")["icon"] . " Ud entrega: *{$total} {$this->currency}*\n";
        }

        $text .= "💳 Medio de pago: *{$this->payment_method}*\n";
        $text .= "🗓 Creada: *{$this->created_at}*\n\n";

        //$created_at = $actor->getLocalDateTime($this->created_at, $tenant->code);
        //$this->created_at

        return $text;
    }

    public function getAsChannelMessage($botName, $stars = "")
    {
        // 1. Calculamos la antigüedad de la oferta
        $diff = DateService::getTimeDifference(
            $this->created_at->getTimestamp(),
            now()->getTimestamp(),
            "DH"
        );

        $isSell = strtolower($this->type) == "sell";
        $icon = Offers::getStatusEmoji($this->status)["icon"];

        $buttons = [];
        $title = "*" . Offers::getStatusTitle($this->status, $diff) . "*";
        $subtitle = "🛡 _Use siempre el sistema de custodia para transacciones 100% seguras en nuestro P2P._";

        // 2. Lógica de Títulos Dinámicos basada en el status
        switch ($this->status) {
            case 'open':
                $icon = "🟩";
                if ($isSell)
                    $icon = "🟥";

                $title = "{$icon} " . $title;

                // Solo si está abierta calculamos los prefijos de tiempo
                if ($diff['days'] == 0 && $diff['hours'] < 1) {
                    $diff = DateService::getTimeDifference($this->created_at->getTimestamp(), now()->getTimestamp(), "IS");
                    $time = "💥 " . strtoupper($diff["legible"]);
                } elseif ($diff['days'] == 0) {
                    $diff = DateService::getTimeDifference($this->created_at->getTimestamp(), now()->getTimestamp(), "HI");
                    $time = "🔥 " . strtoupper($diff["legible"]);
                } else {
                    $time = "✨ " . strtoupper($diff["legible"]);
                }

                $title .= " " . $time;

                $buttons = [
                    "inline_keyboard" => [
                        [
                            ["text" => "👉 Aplicar a esta oferta", 'url' => "https://t.me/" . $botName . "?start=offer_{$this->code}"]
                        ]
                    ],
                ];
                break;

            case 'locked':
                $title = "{$icon} " . $title;
                $subtitle = "🔐 _La liquidez de este intercambio ha sido bloqueada._";
                break;
            case 'cancelled':
                $title = "{$icon} " . $title;
                $subtitle = "🙅‍♂️ _El comprador no ha querido continuar con el intercambio._";
                break;
            case 'expired':
                $title = "{$icon} " . $title;
                $subtitle = "⏱️ _El tiempo de seguridad ha expirado antes de completar la verificación._";
                break;
            case 'signed':
                $title = "{$icon} " . $title;
                $subtitle = "🏃‍♂️ _Una de las partes ya ha confirmado la transacción._";
                break;
            case 'disputed':
                $title = "{$icon} " . $title;
                $subtitle = "👮‍♀️ _Un administrador está revisando este intercambio._";
                break;
            case 'completed':
                $title = "{$icon} " . $title;
                $subtitle = "🙏 _¡Gracias por confiar en nosotros!_";
                break;
            case 'solved':
                $title = "{$icon} " . $title;
                $subtitle = "⚖️ _Este intercambio ha sido decidido por arbitraje._";
                break;
            default:
                $title = "{$icon} " . $title;
                break;
        }

        // 3. Renderizar y Editar
        $text = $this->renderAsTelegramMessage($title, false, $stars);
        $text .= $subtitle;

        $array = [
            "message" => [
                "text" => $text,
                "chat" => ["id" => env("TRADER_BOT_CHANNEL")],
            ],
        ];

        if (count($buttons) > 0) {
            $array["message"]["reply_markup"] = json_encode($buttons);
        }

        return $array;
    }


    public function getNetProceeds($status)
    {
        if (!$status) {
            // Fallback de seguridad si falla el RPC (0.25% por defecto)
            return number_format($this->amount * 0.9975, 2);
        }

        // 1. Cálculo por Porcentaje (BPS)
        $feeByPercentage = $this->amount * $status['realFeeFactor'];
        // 2. Comparación con el MinFee (en USD)
        // El contrato siempre cobra lo que sea mayor para cubrir el GAS
        $finalFee = max($feeByPercentage, $status['currentMinFeeUsd']);

        $net = $this->amount - $finalFee;

        return [
            'net' => number_format($net, 2),
            'fee' => number_format($finalFee, 2),
            'min' => ($finalFee == $status['currentMinFeeUsd'])
        ];
    }

    public static function getStatusTitle($status, $diff)
    {
        $title = "";
        // Solo si está abierta calculamos los prefijos de tiempo
        if ($diff['days'] == 0 && $diff['hours'] < 1) {
            $title = "¡NUEVA OFERTA";
        } elseif ($diff['days'] == 0) {
            $title = "OFERTA RECIENTE";
        } else {
            $title = "OFERTA DISPONIBLE";
        }

        return match (strtoupper($status)) {
            'OPEN' => $title,
            'CANCELLED' => "OFERTA FINALIZADA",
            'COMPLETED' => "OFERTA FINALIZADA",
            'LOCKED' => "OFERTA EN CURSO",   // Fondos en Escrow
            'SIGNED' => "OFERTA EN CURSO",   // Una parte ya firmó
            'DISPUTED' => "OFERTA EN CURSO", // En disputa
            'SOLVED' => "OFERTA FINALIZADA",
            'EXPIRED' => "OFERTA FINALIZADA",  // Tiempo agotado
            default => "OFERTA ACTUALIZADA",
        };
    }

    public static function getStatusEmoji($status)
    {
        return match (strtoupper($status)) {
            'OPEN' => ["icon" => '⬜️', "color" => "⬜️"],
            'CANCELLED' => ["icon" => '❌', "color" => "🟫"],
            'COMPLETED' => ["icon" => '✅', "color" => "🟩"],
            'LOCKED' => ["icon" => '🔒', "color" => "🟧"],   // Fondos en Escrow
            'SIGNED' => ["icon" => '✍️', "color" => "🟨"],   // Una parte ya firmó
            'DISPUTED' => ["icon" => '⚖️', "color" => "🟪"], // En disputa
            'SOLVED' => ["icon" => '☑️', "color" => "🟪"],
            'EXPIRED' => ["icon" => '⏱️', "color" => "🟦"],  // Tiempo agotado
            default => ["icon" => '▫️', "color" => "⬜️"],
        };
    }

    public static function getTypeEmoji($type)
    {
        return match (strtolower($type)) {
            'sell' => ["icon" => '📤', "color" => "🟥"],
            default => ["icon" => '📥', "color" => "🟩"],
        };
    }
}
