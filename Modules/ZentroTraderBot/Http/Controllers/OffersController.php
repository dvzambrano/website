<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\TelegramBot\Http\Controllers\TelegramController;

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
                "reply_markup" => json_encode(["remove_keyboard" => true]),
                "editprevious" => 1
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
                        return [
                            "text" => "❌ Por favor, envía un monto válido.",
                            "chat" => ["id" => $userId],
                            "editprevious" => 1
                        ];
                    }
                    $state['history'][] = ['step' => 'STEP_AMOUNT', 'data' => $state['data']];
                    $state['data']['amount'] = $text;
                    $state['step'] = 'STEP_PRICE';
                    Cache::forever($cacheKey, $state);

                    // LIMPIEZA PARA RECURSIÓN: Evita el salto automático al siguiente paso
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "💰 *Paso 1:* ¿Cuánto USD deseas vender?\n_(Escribe solo el número)_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]
                    ])
                ];

            case 'STEP_PRICE':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    if (!is_numeric($text) || $text <= 0) {
                        return [
                            "text" => "❌ Precio inválido. Debe ser un número (ej: 0.85).",
                            "chat" => ["id" => $userId],
                            "editprevious" => 1
                        ];
                    }
                    $state['history'][] = ['step' => 'STEP_PRICE', 'data' => $state['data']];
                    $state['data']['price'] = $text;
                    $state['step'] = 'STEP_CURRENCY'; // Nuevo salto
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "💵 *Paso 2:* ¿A qué precio por cada USD?\n_(Ejemplo: 0.85)_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"],
                                ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
                    "editprevious" => 1
                ];

            case 'STEP_CURRENCY':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_CURRENCY', 'data' => $state['data']];
                    $state['data']['currency'] = $text;
                    $state['step'] = 'STEP_METHOD';
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "💱 *Paso 3:* ¿En qué moneda quieres recibir el pago?",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "EUR", "callback_data" => "EUR"], ["text" => "USD", "callback_data" => "USD"]],
                            [["text" => "CUP", "callback_data" => "CUP"], ["text" => "MLC", "callback_data" => "MLC"]],
                            [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]
                        ]
                    ]),
                    "editprevious" => 1
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
                    "text" => "🏦 *Paso 4:* Selecciona el método de pago:",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => $menu]),
                    "editprevious" => 1
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
                    "text" => "📝 *Paso 5:* Introduce los detalles de tu cuenta para *{$method}*:",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"],
                                ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
                    "editprevious" => 1
                ];

            case 'CONFIRM':
                $this->deleteUserText($bot);
                if ($text === '/offerconfirm') {
                    return $this->publishOffer($bot, $state);
                }

                $total = number_format($state['data']['amount'] * $state['data']['price'], 2);
                $currency = $state['data']['currency'] ?? 'USD';

                $text = "🚀 *Resumen de tu Oferta*\n"
                    . "──────────────────\n"
                    . "📦 Vendes: *{$state['data']['amount']} USD*\n"
                    . "🏷 Precio: *{$state['data']['price']} {$currency}/USD*\n"
                    . "💰 Recibirás: *{$total} {$currency}*\n"
                    . "🏦 Método: *{$state['data']['method']}*\n"
                    . "📍 Datos: `{$state['data']['details']}`\n"
                    . "──────────────────\n"
                    . "¿Deseas publicar esta oferta en Kashio?";

                $menu = [
                    [["text" => "🚀 Publicar Oferta", "callback_data" => "/offerconfirm"]],
                    [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]
                ];

                return [
                    "text" => $text,
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => $menu]),
                    "editprevious" => 1
                ];
        }
    }

    private function deleteUserText($bot)
    {
        // eliminar el mensaje q origino esta interaccion del bot
        if ($bot->message["message_id"] != "") {
            try {
                $array = array(
                    "message" => array(
                        "id" => $bot->message["message_id"],
                        "chat" => array(
                            "id" => $bot->message["chat"]["id"],
                        ),
                    ),
                );
                TelegramController::deleteMessage($array, $bot->tenant->token);
            } catch (\Throwable $th) {
            }
        }
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