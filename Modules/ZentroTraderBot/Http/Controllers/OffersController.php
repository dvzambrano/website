<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Currencies;
use Modules\ZentroTraderBot\Entities\Paymentmethods;
use Modules\TelegramBot\Http\Controllers\TelegramController;

class OffersController extends Controller
{
    public function sell($bot)
    {
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $cacheKey = "wizard_{$bot->tenant->key}_{$userId}";
        $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);

        $state = Cache::get($cacheKey, [
            'controller' => self::class,
            'method' => 'sell',
            'step' => 'START',
            'data' => [],
            'history' => []
        ]);

        // --- SALIDA Y RETROCESO ---
        if ($text === '/wizardcancel') {
            Cache::forget($cacheKey);
            return [
                "text" => "❌ *Operación cancelada.*",
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["remove_keyboard" => true]),
                "editprevious" => $isCallback ? 1 : 0
            ];
        }

        if ($text === '/wizardprevious' && !empty($state['history'])) {
            $lastState = array_pop($state['history']);
            $state['step'] = $lastState['step'];
            $state['data'] = $lastState['data'];
            Cache::forever($cacheKey, $state);
            $bot->message["text"] = null;
            return $this->sell($bot);
        }

        switch ($state['step']) {
            case 'START':
                $state['step'] = 'STEP_AMOUNT';
                Cache::forever($cacheKey, $state);

            case 'STEP_AMOUNT':
                if ($text !== null && $text !== '/p2psell') {
                    if (!is_numeric($text) || $text <= 0) {
                        return ["text" => "❌ Monto inválido.", "chat" => ["id" => $userId], "editprevious" => 1];
                    }
                    $state['history'][] = ['step' => 'STEP_AMOUNT', 'data' => $state['data']];
                    $state['data']['amount'] = $text;
                    $state['step'] = 'STEP_CURRENCY'; // SALTO A MONEDA
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" => "1️⃣ *Definir el monto*\n_¿Cuánto USD desea vender?_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                    "editprevious" => $isCallback ? 1 : 0
                ];

            case 'STEP_CURRENCY':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    $state['history'][] = ['step' => 'STEP_CURRENCY', 'data' => $state['data']];
                    $state['data']['currency'] = $text;
                    $state['step'] = 'STEP_PRICE'; // SALTO A PRECIO
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

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

            case 'STEP_PRICE':
                $this->deleteUserText($bot);
                if ($text !== null) {
                    if (!is_numeric($text) || $text <= 0) {
                        return ["text" => "❌ Precio inválido.", "chat" => ["id" => $userId], "editprevious" => 1];
                    }
                    $state['history'][] = ['step' => 'STEP_PRICE', 'data' => $state['data']];
                    $state['data']['price'] = $text;
                    $state['step'] = 'STEP_METHOD'; // SALTO A MÉTODO
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                $curr = $state['data']['currency'] ?? 'moneda seleccionada';
                return [
                    "text" => "3️⃣ *Precio de venta*\n_¿A qué precio por cada USD en *{$curr}*?_\n\n_Ejemplo:_ `1.02` o `1150`",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                    "editprevious" => 1
                ];

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
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

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
                    "text" => "4️⃣ *Método de pago*\n_¿Cómo recibirás tus {$state['data']['currency']}?_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => $buttons]),
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

                $methodName = $state['data']['method_name'] ?? $state['data']['method'];
                return [
                    "text" => "5️⃣ *Datos de la cuenta*\n_Escribe los detalles para {$methodName}:_",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                    "editprevious" => 1
                ];

            case 'CONFIRM':
                $this->deleteUserText($bot);
                if ($text === '/offerconfirm') {
                    return $this->publishOffer($bot, $state);
                }

                $total = number_format($state['data']['amount'] * $state['data']['price'], 2);
                return [
                    "text" => "🌟 *Resumen de su Oferta*\n\n"
                        . "💵 Vendes: *{$state['data']['amount']} USD*\n"
                        . "💰 Precio: *{$state['data']['price']} {$state['data']['currency']}*\n"
                        . "💱 Recibes: *{$total} {$state['data']['currency']}*\n"
                        . "🏦 Método: *{$state['data']['method_name']}*\n"
                        . "📝 Datos: `{$state['data']['details']}`",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "✅ Publicar", "callback_data" => "/offerconfirm"]], [["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                    "editprevious" => 1
                ];
        }
    }

    private function deleteUserText($bot)
    {
        $isCallback = isset($bot->callback_query) || isset($bot->message['reply_markup']);
        if (!$isCallback && !empty($bot->message["message_id"])) {
            try {
                $array = ["message" => ["id" => $bot->message["message_id"], "chat" => ["id" => $bot->message["chat"]["id"]]]];
                TelegramController::deleteMessage($array, $bot->tenant->token);
            } catch (\Throwable $th) {
            }
        }
    }

    private function publishOffer($bot, $state)
    {
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
        return ["text" => "✅ *¡Oferta publicada!*", "chat" => ["id" => $bot->actor->user_id], "reply_markup" => json_encode(["remove_keyboard" => true])];
    }
}