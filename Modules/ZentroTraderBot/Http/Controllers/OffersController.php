<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\ZentroTraderBot\Entities\Currencies;
use Modules\ZentroTraderBot\Entities\Paymentmethods;
use Modules\TelegramBot\Http\Controllers\TelegramController;

class OffersController extends Controller
{
    public function sell($bot)
    {
        // 1. Normalización de entrada
        $isCallback = isset($bot->callback_query);
        $text = $isCallback ? $bot->callback_query['data'] : ($bot->message["text"] ?? null);

        $userId = $bot->actor->user_id;
        $cacheKey = "wizard_{$bot->tenant->key}_{$userId}";

        $state = Cache::get($cacheKey, [
            'step' => 'STEP_AMOUNT',
            'data' => [],
            'history' => []
        ]);

        // --- GESTIÓN DE SALIDA Y RETROCESO ---
        if ($text === '/wizardcancel') {
            Cache::forget($cacheKey);
            return [
                "text" => "❌ *Operación cancelada.*",
                "chat" => ["id" => $userId],
                "editprevious" => $isCallback ? 1 : 0
            ];
        }

        if ($text === '/wizardprevious' && !empty($state['history'])) {
            $lastState = array_pop($state['history']);
            $state['step'] = $lastState['step'];
            $state['data'] = $lastState['data'];
            Cache::forever($cacheKey, $state);
            // Al retroceder, forzamos que el flujo caiga en el render del paso anterior
            $text = null;
        }

        // --- FLUJO LINEAL ---
        switch ($state['step']) {

            case 'STEP_AMOUNT':
                // Si recibimos datos, validamos y saltamos al siguiente
                if ($text !== null && $text !== '/p2psell') {
                    if (!is_numeric($text) || $text <= 0) {
                        return ["text" => "❌ Monto inválido. Escriba un número mayor a 0:", "chat" => ["id" => $userId], "editprevious" => 1];
                    }
                    $state['history'][] = ['step' => 'STEP_AMOUNT', 'data' => $state['data']];
                    $state['data']['amount'] = $text;
                    $state['step'] = 'STEP_CURRENCY';
                    Cache::forever($cacheKey, $state);
                    // Importante: No retornamos, dejamos que caiga al siguiente case para pintar la pregunta
                    $text = null;
                } else {
                    return [
                        "text" => "1️⃣ *Definir el monto*\n_¿Cuánto USD desea vender?_\n\nEscriba solo el número. _Ej:_ `100`",
                        "chat" => ["id" => $userId],
                        "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]])
                    ];
                }

            case 'STEP_CURRENCY':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_CURRENCY', 'data' => $state['data']];
                    $state['data']['currency'] = $text;
                    $state['step'] = 'STEP_PRICE';
                    Cache::forever($cacheKey, $state);
                    $text = null; // Limpiamos para el render del siguiente
                } else {
                    $currencies = Currencies::where('is_active', true)->get();
                    $buttons = [];
                    foreach ($currencies->chunk(2) as $chunk) {
                        $row = [];
                        foreach ($chunk as $c) {
                            $row[] = ["text" => "{$c->symbol} {$c->code}", "callback_data" => $c->code];
                        }
                        $buttons[] = $row;
                    }
                    $buttons[] = [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]];

                    return [
                        "text" => "2️⃣ *Moneda a recibir*\n_¿En qué moneda recibirás el pago?_",
                        "chat" => ["id" => $userId],
                        "reply_markup" => json_encode(["inline_keyboard" => $buttons]),
                        "editprevious" => 1
                    ];
                }

            case 'STEP_PRICE':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    if (!is_numeric($text) || $text <= 0) {
                        return ["text" => "❌ Precio inválido.", "chat" => ["id" => $userId], "editprevious" => 1];
                    }
                    $state['history'][] = ['step' => 'STEP_PRICE', 'data' => $state['data']];
                    $state['data']['price'] = $text;
                    $state['step'] = 'STEP_METHOD';
                    Cache::forever($cacheKey, $state);
                    $text = null;
                } else {
                    $curr = $state['data']['currency'];
                    return [
                        "text" => "3️⃣ *Precio de venta*\n_¿A qué precio por cada USD en *{$curr}*?_\n\nEscriba el número. _Ej:_ `1.05` o `1200`",
                        "chat" => ["id" => $userId],
                        "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                        "editprevious" => 1
                    ];
                }

            case 'STEP_METHOD':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    $currency = Currencies::where('code', $state['data']['currency'])->first();
                    $methodInfo = $currency->paymentmethods()->where('identifier', $text)->first();

                    $state['history'][] = ['step' => 'STEP_METHOD', 'data' => $state['data']];
                    $state['data']['method'] = $text;
                    $state['data']['method_name'] = $methodInfo->name ?? $text;
                    $state['step'] = 'STEP_DETAILS';
                    Cache::forever($cacheKey, $state);
                    $text = null;
                } else {
                    $currency = Currencies::where('code', $state['data']['currency'])->first();
                    $methods = $currency ? $currency->activePaymentmethods()->get() : [];
                    $buttons = [];
                    foreach ($methods->chunk(2) as $chunk) {
                        $row = [];
                        foreach ($chunk as $m) {
                            $row[] = ["text" => "{$m->icon} {$m->name}", "callback_data" => $m->identifier];
                        }
                        $buttons[] = $row;
                    }
                    $buttons[] = [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]];

                    return [
                        "text" => "4️⃣ *Método de pago*\n_Selecciona cómo recibirás tus {$state['data']['currency']}_:",
                        "chat" => ["id" => $userId],
                        "reply_markup" => json_encode(["inline_keyboard" => $buttons]),
                        "editprevious" => 1
                    ];
                }

            case 'STEP_DETAILS':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_DETAILS', 'data' => $state['data']];
                    $state['data']['details'] = $text;
                    $state['step'] = 'CONFIRM';
                    Cache::forever($cacheKey, $state);
                    $text = null;
                } else {
                    $method = $state['data']['method_name'];
                    return [
                        "text" => "5️⃣ *Datos de la cuenta*\n_Escribe los datos para recibir el pago vía *{$method}*:_",
                        "chat" => ["id" => $userId],
                        "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                        "editprevious" => 1
                    ];
                }

            case 'CONFIRM':
                $this->deleteUserText($bot);
                if ($text === '/offerconfirm') {
                    return $this->publishOffer($bot, $state);
                }

                $total = number_format($state['data']['amount'] * $state['data']['price'], 2);
                $curr = $state['data']['currency'];

                return [
                    "text" => "🌟 *Resumen de su Oferta*\n\n"
                        . "💵 Vendes: *{$state['data']['amount']} USD*\n"
                        . "💰 Precio: *{$state['data']['price']} {$curr}*\n"
                        . "💱 Recibes: *{$total} {$curr}*\n"
                        . "🏦 Método: *{$state['data']['method_name']}*\n"
                        . "📝 Datos: `{$state['data']['details']}`",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "✅ Publicar Oferta", "callback_data" => "/offerconfirm"]],
                            [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]
                        ]
                    ]),
                    "editprevious" => 1
                ];
        }
    }

    private function deleteUserText($bot)
    {
        if (!isset($bot->callback_query) && !empty($bot->message["message_id"])) {
            try {
                $array = ["message" => ["id" => $bot->message["message_id"], "chat" => ["id" => $bot->message["chat"]["id"]]]];
                TelegramController::deleteMessage($array, $bot->tenant->token);
            } catch (\Throwable $e) {
            }
        }
    }

    private function publishOffer($bot, $state)
    {
        Cache::forget("wizard_{$bot->tenant->key}_{$bot->actor->user_id}");
        return [
            "text" => "✅ *¡Oferta publicada con éxito!*",
            "chat" => ["id" => $bot->actor->user_id],
            "reply_markup" => json_encode(["remove_keyboard" => true])
        ];
    }
}