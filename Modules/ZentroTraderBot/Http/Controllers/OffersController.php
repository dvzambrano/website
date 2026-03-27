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
            // Si viene de un botón (callback), editamos el mensaje actual
            // Si escribió el comando manualmente, enviamos uno nuevo
            $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);
            return [
                "text" => "❌ *Operación cancelada.*\nEl borrador de tu oferta ha sido eliminado.",
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["remove_keyboard" => true]),
                "editprevious" => $isCallback ? 1 : 0 // Solo editamos si ya existía el mensaje del Wizard
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
                        return ["text" => "❌ Monto inválido.", "chat" => ["id" => $userId], "editprevious" => 1];
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
                    "text" => "💵 *Paso 1️⃣: Definir el monto*\n\n¿Cuánto USD desea vender?\n_Escriba solo el número. Por ejemplo:_`100`",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]])
                ];

            case 'STEP_PRICE':
                $this->deleteUserText($bot); // Limpia el "100" del usuario
                if ($text !== null) {
                    if (!is_numeric($text) || $text <= 0) {
                        return ["text" => "❌ Precio inválido.", "chat" => ["id" => $userId], "editprevious" => 1];
                    }
                    $state['history'][] = ['step' => 'STEP_PRICE', 'data' => $state['data']];
                    $state['data']['price'] = $text;
                    $state['step'] = 'STEP_CURRENCY';
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "💰 *Paso 2️⃣: Precio de venta*\n\n¿A qué precio por cada USD?\n_Escriba solo el número. Por ejemplo:_`1.02`\n_En el ejemplo estaría cobrando 2% de recargo._",
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
                    "text" => "💱 *Paso 3️⃣: Moneda a recibir*\n\n¿En qué moneda recibirás el pago?\n\n👇 _Seleccione una desde las disponibles_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "EUR", "callback_data" => "EUR"], ["text" => "USD", "callback_data" => "USD"]],
                            [["text" => "CUP", "callback_data" => "CUP"], ["text" => "MLC", "callback_data" => "MLC"]],
                            [
                                ["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"],
                                ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
                    "editprevious" => 1
                ];

            case 'STEP_METHOD':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_METHOD', 'data' => $state['data']];
                    $state['data']['method'] = $text;
                    $state['step'] = 'STEP_DETAILS';
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "🏦 *Paso 4️⃣: Método de pago*\n\n¿Por qué vía desea recibir *{$state['data']['currency']}*?\n\n👇 _Seleccione una desde las disponibles_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "Zelle", "callback_data" => "Zelle"], ["text" => "Bizum", "callback_data" => "Bizum"]],
                            [["text" => "Transferencia", "callback_data" => "Transferencia"]],
                            [
                                ["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"],
                                ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
                    "editprevious" => 1
                ];

            case 'STEP_DETAILS':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_DETAILS', 'data' => $state['data']];
                    $state['data']['details'] = $text;
                    $state['step'] = 'CONFIRM';
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "📝 *Paso 5️⃣: Datos de la cuenta*\n\nEscriba los detalles de su cuenta *{$state['data']['method']}*:\n\n_Recuerde ser explícito, cualquier dato faltante podría afectar el tiempo de recepción de su dinero._",
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

                return [
                    "text" => "🌟 *Resumen de su Oferta*\n"
                        . "Vendes: *{$state['data']['amount']} USD*\n"
                        . "Precio: *{$state['data']['price']} {$currency}*\n"
                        . "Recibes: *{$total} {$currency}*\n"
                        . "Método: *{$state['data']['method']}*\n"
                        . "Datos: `{$state['data']['details']}`",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "✅ Publicar", "callback_data" => "/offerconfirm"]],
                            [
                                ["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"],
                                ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
                    "editprevious" => 1
                ];
        }
    }

    private function deleteUserText($bot)
    {
        // IMPORTANTE: Solo borrar si es un mensaje de texto real del usuario
        // Si es un callback_query, no hay "mensaje de usuario" que borrar, 
        // y el message_id que trae es el del propio Bot.
        /*
        $isCallback = isset($bot->callback_query) || isset($bot->message['reply_markup']);
        if (!$isCallback && !empty($bot->message["message_id"])) {
            try {
                $array = [
                    "message" => [
                        "id" => $bot->message["message_id"],
                        "chat" => ["id" => $bot->message["chat"]["id"]]
                    ]
                ];
                TelegramController::deleteMessage($array, $bot->tenant->token);
            } catch (\Throwable $th) {
                // Log::error("Error borrando: " . $th->getMessage());
            }
        }
        */
    }

    private function publishOffer($bot, $state)
    {
        // Lógica de base de datos aquí...

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
            "text" => "✅ *¡Oferta publicada!*",
            "chat" => ["id" => $bot->actor->user_id],
            "reply_markup" => json_encode(["remove_keyboard" => true])
            // Nota: Aquí NO usamos editprevious para que el check verde sea un mensaje nuevo
        ];
    }
}