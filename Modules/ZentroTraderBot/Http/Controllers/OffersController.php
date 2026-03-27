<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Entities\Offers;

class OffersController extends Controller
{
    public function sell($bot)
    {
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $cacheKey = "wizard_{$bot->tenant->key}_{$userId}";

        // Recuperamos el estado persistente
        $state = Cache::get($cacheKey, [
            'controller' => self::class,
            'method' => 'sell',
            'step' => 'START',
            'data' => [],
            'history' => []
        ]);

        // --- GESTIÓN DE SALIDA EXPLÍCITA ---
        if ($text === '/wizardcancel') {
            Cache::forget($cacheKey);
            return [
                "text" => "❌ *Operación cancelada.*\nEl borrador de tu oferta ha sido eliminado.",
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["remove_keyboard" => true])
            ];
        }

        // --- GESTIÓN DE RETROCESO ---
        if ($text === '/wizardprevious' && !empty($state['history'])) {
            $lastState = array_pop($state['history']);
            $state['step'] = $lastState['step'];
            $state['data'] = $lastState['data'];
            Cache::forever($cacheKey, $state);

            // Limpiamos el texto para que al re-entrar solo pinte la pregunta
            $bot->message["text"] = null;
            return $this->sell($bot);
        }

        switch ($state['step']) {
            case 'START':
                $state['step'] = 'STEP_AMOUNT';
                Cache::forever($cacheKey, $state);
            // No break: cae al primer paso para mostrar la pregunta inicial

            case 'STEP_AMOUNT':
                // Si hay texto y no es el comando inicial, procesamos el dato
                if ($text !== null && $text !== '/p2psell') {
                    if (!is_numeric($text) || $text <= 0) {
                        return ["text" => "❌ Por favor, envía un monto válido.", "chat" => ["id" => $userId]];
                    }
                    $state['history'][] = ['step' => 'STEP_AMOUNT', 'data' => $state['data']];
                    $state['data']['amount'] = $text;
                    $state['step'] = 'STEP_PRICE';
                    Cache::forever($cacheKey, $state);

                    // LIMPIEZA PARA RECURSIÓN: Evita el salto automático al siguiente paso
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                // Si text es null, mostramos la pregunta
                return [
                    "text" => "💰 *Paso 1:* ¿Cuánto USD deseas vender?\n_(Escribe solo el número)_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]
                    ])
                ];

            case 'STEP_PRICE':
                if ($text !== null) {
                    if (!is_numeric($text) || $text <= 0) {
                        return ["text" => "❌ Precio inválido. Debe ser un número (ej: 1.05).", "chat" => ["id" => $userId]];
                    }
                    $state['history'][] = ['step' => 'STEP_PRICE', 'data' => $state['data']];
                    $state['data']['price'] = $text;
                    $state['step'] = 'STEP_METHOD';
                    Cache::forever($cacheKey, $state);

                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "💵 *Paso 2:* ¿A qué precio por cada USD?\n_(Ejemplo: 1.05)_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"],
                                ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ])
                ];

            case 'STEP_METHOD':
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_METHOD', 'data' => $state['data']];
                    $state['data']['method'] = $text;
                    $state['step'] = 'STEP_DETAILS';
                    Cache::forever($cacheKey, $state);

                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                $menu = [
                    [["text" => "Zelle", "callback_data" => "Zelle"], ["text" => "Bizum", "callback_data" => "Bizum"]],
                    [["text" => "Transferencia Bancaria", "callback_data" => "Transferencia Bancaria"]],
                    [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]
                ];

                return [
                    "text" => "🏦 *Paso 3:* Selecciona el método donde recibirás el pago:",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => $menu])
                ];

            case 'STEP_DETAILS':
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_DETAILS', 'data' => $state['data']];
                    $state['data']['details'] = $text;
                    $state['step'] = 'CONFIRM';
                    Cache::forever($cacheKey, $state);

                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                $method = $state['data']['method'] ?? 'pago';
                return [
                    "text" => "📝 *Paso 4:* Introduce los detalles de tu cuenta para *{$method}*:\n_(Email, IBAN, Nombre, etc.)_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"],
                                ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ])
                ];

            case 'CONFIRM':
                if ($text === '/offerconfirm') {
                    return $this->publishOffer($bot, $state);
                }
                return $this->showSummary($bot->actor, $state['data']);
        }
    }

    private function showSummary($actor, $data)
    {
        $total = number_format($data['amount'] * $data['price'], 2);
        $text = "🚀 *Resumen de tu Oferta*\n"
            . "──────────────────\n"
            . "📦 Vendes: *{$data['amount']} USD*\n"
            . "🏷 Precio: *{$data['price']}*\n"
            . "💰 Recibirás: *{$total} USD*\n"
            . "🏦 Método: *{$data['method']}*\n"
            . "📍 Datos: `{$data['details']}`\n"
            . "──────────────────\n"
            . "¿Deseas publicar esta oferta en Kashio?";

        $menu = [
            [["text" => "🚀 Publicar Oferta", "callback_data" => "/offerconfirm"]],
            [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]
        ];

        return [
            "text" => $text,
            "chat" => ["id" => $actor->user_id],
            "reply_markup" => json_encode(["inline_keyboard" => $menu]),
        ];
    }

    private function publishOffer($bot, $state)
    {
        $data = $state['data'];

        /*
        Offers::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $bot->actor->user_id,
            'type' => 'sell',
            'amount' => $data['amount'],
            'price_per_usd' => $data['price'],
            'payment_method' => $data['method'],
            'payment_details' => $data['details'],
            'status' => 'signed',
            'network_id' => 137,
            'data' => ['source' => 'telegram_wizard']
        ]);
        */

        Cache::forget("wizard_{$bot->tenant->key}_{$bot->actor->user_id}");

        return [
            "text" => "✅ *¡Oferta publicada con éxito!*\n\nTu anuncio ya está registrado.",
            "chat" => ["id" => $bot->actor->user_id],
            "reply_markup" => json_encode(["remove_keyboard" => true])
        ];
    }
}