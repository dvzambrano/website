<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Currencies;
use Modules\ZentroTraderBot\Entities\Paymentmethods;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Lang;
use Modules\Web3\Http\Controllers\CoingeckoController;
use Modules\Laravel\Services\Exchange\CambiocupService;
use Modules\Laravel\Services\NumberService;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Entities\Suscriptions;

class OffersController extends Controller
{
    public function sell($bot)
    {
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $cacheKey = "wizard_{$bot->tenant->key}_{$userId}";

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
            $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);
            return [
                "text" =>
                    "❌ *Operación cancelada.*\n" .
                    "_Ud ha cancelado la publicación de su Oferta satisfactoriamente._\n\n" .
                    "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                    ],
                ]),
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
                // --- VALIDACIÓN DE BALANCE ---
                $walletCtrl = new TraderWalletController();
                $suscriptor = Suscriptions::where('user_id', $userId)->first();
                $balance = $walletCtrl->getBalance($suscriptor);

                if ($text !== null && $text !== '/p2psell') {
                    $this->deleteUserText($bot);

                    try {
                        $parsedtext = NumberService::parse($text);
                        if (is_numeric($parsedtext))
                            $text = $parsedtext;
                    } catch (\Throwable $th) {
                    }
                    if (!is_numeric($text) || $text <= 0) {
                        return [
                            "text" =>
                                "✨ *Asistente de creación de ofertas*\n" .
                                "◾️ _Paso 1️⃣ de 5️⃣_\n" .
                                "▫️ *Definir el monto de la transacción*\n" .
                                "❌ '{$text}' no es un monto válido\n" .
                                "▫️ _¿Cuántos de sus {$balance} USD disponibles desea vender?_\n" .
                                "▫️ _Escriba solo el número. Ejemplo:_ `{$balance}`",
                            "chat" => ["id" => $userId],
                            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                            "editprevious" => 1
                        ];
                    }

                    if ($text > $balance) {
                        return [
                            "text" =>
                                "✨ *Asistente de creación de ofertas*\n" .
                                "◾️ _Paso 1️⃣ de 5️⃣_\n" .
                                "▫️ *Definir el monto de la transacción*\n" .
                                "❌ Intentas vender {$text} USD\n" .
                                "▫️ _¿Cuántos de sus {$balance} USD disponibles desea vender?_\n" .
                                "▫️ _Escriba solo el número. Ejemplo:_ `{$balance}`",
                            "chat" => ["id" => $userId],
                            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                            "editprevious" => 1
                        ];
                    }


                    $state['history'][] = ['step' => 'STEP_AMOUNT', 'data' => $state['data']];
                    $state['data']['amount'] = $text;
                    $state['step'] = 'STEP_CURRENCY'; // SALTO A MONEDA
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" =>
                        "✨ *Asistente de creación de ofertas*\n" .
                        "◾️ _Paso 1️⃣ de 5️⃣_\n" .
                        "▫️ *Definir el monto de la transacción*\n" .
                        "▫️ \n" .
                        "▫️ _¿Cuántos de sus {$balance} USD disponibles desea vender?_\n" .
                        "▫️ _Escriba solo el número. Ejemplo:_ `{$balance}`",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                    "editprevious" => $text == null ? 1 : 0
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

                $currencies = Currencies::where('is_active', true)
                    ->has('paymentmethods') // Filtro de relación
                    ->get();
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
                    "text" =>
                        "✨ *Asistente de creación de ofertas*\n" .
                        "▫️ _Paso 2️⃣ de 5️⃣_\n" .
                        "◾️ *Moneda a recibir en este intercambio*\n" .
                        "▫️ \n" .
                        "▫️ _¿En qué moneda recibirá el pago?_\n" .
                        "▫️ Seleccione una de las disponibles 👇",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => $buttons]),
                    "editprevious" => 1
                ];

            case 'STEP_PRICE':
                $this->deleteUserText($bot);

                $coin = $state['data']['currency'];
                // 1. Intentamos CoinGecko
                $cgval = CoingeckoController::getLivePrice("tether", $coin);
                // 2. Intentamos CambioCUP solo si CG no dio un resultado válido (mayor que 0)
                $ccval = ($cgval <= 0) ? CambiocupService::getRate($coin) : null;
                // 3. Asignación final con prioridad
                $val = $cgval > 0 ? $cgval : ($ccval > 0 ? $ccval : 1.02);

                if ($text !== null) {
                    try {
                        $parsedtext = NumberService::parse($text);
                        if (is_numeric($parsedtext))
                            $text = $parsedtext;
                    } catch (\Throwable $th) {
                    }
                    if (!is_numeric($text) || $text <= 0) {
                        return [
                            "text" =>
                                "✨ *Asistente de creación de ofertas*\n" .
                                "▫️ _Paso 3️⃣ de 5️⃣_\n" .
                                "▫️ *Precio de venta USD/{$coin}?*\n" .
                                "❌ '{$text}' no es un precio válido\n" .
                                "▫️ _¿Cuántos {$coin} desea recibir por cada USD que vende?_\n" .
                                "▫️ _Por ejemplo:_ `" . number_format($val, 2) . "`",
                            "chat" => ["id" => $userId],
                            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "⬅️ Atrás", "callback_data" => "/wizardprevious"], ["text" => "❌ Cancelar", "callback_data" => "/wizardcancel"]]]]),
                            "editprevious" => 1
                        ];
                    }
                    $state['history'][] = ['step' => 'STEP_PRICE', 'data' => $state['data']];
                    $state['data']['price'] = $text;
                    $state['step'] = 'STEP_METHOD'; // SALTO A MÉTODO
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->sell($bot);
                }

                return [
                    "text" =>
                        "✨ *Asistente de creación de ofertas*\n" .
                        "▫️ _Paso 3️⃣ de 5️⃣_\n" .
                        "▫️ *Precio de venta USD/{$coin}?*\n" .
                        "◾️ \n" .
                        "▫️ _¿Cuántos {$coin} desea recibir por cada USD que vende?_\n" .
                        "▫️ _Por ejemplo:_ `" . number_format($val, 2) . "`",
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
                    "text" =>
                        "✨ *Asistente de creación de ofertas*\n" .
                        "▫️ _Paso 4️⃣ de 5️⃣_\n" .
                        "▫️ *Método de pago deseado*\n" .
                        "▫️ \n" .
                        "▫️ _¿Por qué vía deben enviarle sus {$state['data']['currency']}?_\n" .
                        "◾️ Seleccione una de las disponibles 👇",
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
                    "text" =>
                        "✨ *Asistente de creación de ofertas*\n" .
                        "▫️ _Paso 5️⃣ de 5️⃣_\n" .
                        "▫️ *Datos de la cuenta*\n" .
                        "▫️ \n" .
                        "▫️ _Escriba los detalles de su cuenta {$methodName}:_\n" .
                        "◾️ *Recuerde ser explícito*, cualquier dato faltante podría afectar el tiempo de recepción de su dinero.",
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
                    "text" => "✨ *Resumen de su Oferta*\n"
                        . "💵 Vendes: *{$state['data']['amount']} USD* a *{$state['data']['price']} {$state['data']['currency']}/USD*\n"
                        . "💱 Recibes: *{$total} {$state['data']['currency']}*\n"
                        . "🏦 {$state['data']['method_name']}: `{$state['data']['details']}`\n" .
                        "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext"),
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
        // Creamos la instancia. NO toca la base de datos.
        $offer = new Offers([
            'uuid' => (string) Str::uuid(),
            'user_id' => $bot->actor->user_id,
            'type' => 'sell',
            'amount' => $state['data']['amount'],
            'price_per_usd' => $state['data']['price'],
            'currency' => $state['data']['currency'],
            'payment_method' => $state['data']['method_name'],
            'payment_details' => $state['data']['details'],
            'status' => 'open',
            'network_id' => env("BASE_NETWORK"),
            'token_address' => env("BASE_TOKEN"),
        ]);
        $text = $offer->renderAsTelegramMessage("🟥 *¡NUEVA OFERTA!*");
        $text .= "🛡 _Use siempre el sistema de custodia para transacciones 100% seguras en nuestro P2P._\n\n";
        $response = TelegramController::sendMessage(
            array(
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
                                    'url' => "https://t.me/" . $bot->tenant->code . "?start=offer-{$offer->uuid}"
                                ]
                            ],
                        ],
                    ]),
                ),
            ),
            $bot->tenant->token
        );
        if ($response) {
            $array = json_decode($response, true);
            $messageId = $array["result"]["message_id"];

            $offer->data = [
                "channel" => [
                    "message_id" => $messageId
                ]
            ];
            $offer->save();

            Cache::forget("wizard_{$bot->tenant->key}_{$bot->actor->user_id}");

            $text = "✅ *¡SU OFERTA YA ESTÁ ACTIVA!*\n";
            $text .= "📢 Su anuncio ha sido publicado en nuestro canal.\n\n";
            $text .= "⚠️ *NOTAS IMPORTANTES DE SEGURIDAD:*\n";
            $text .= "🔒 *Bloqueo de Garantía:* Tan pronto como un interesado aplique a su oferta, los fondos serán *bloqueados automáticamente*. _Esto garantiza al comprador que existen y estarán disponibles para su compra._\n";
            $text .= "🚫 *Regla de Oro*: *NUNCA libere los fondos* hasta que haya verificado manualmente la recepción del pago en su cuenta.\n";
            $text .= "⚖️ *Sistema de arbitraje:* Nuestro equipo de soporte está listo para intervenir en caso de cualquier disputa durante el proceso.\n\n";
            $text .= "👍 *¡Suerte con tu venta!* _Le notificaremos en cuanto alguien aplique._";

            return [
                "text" => $text,
                "chat" => ["id" => $bot->actor->user_id],
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [
                            ["text" => "👀 Ver mi Oferta", "url" => "https://t.me/KashioChannel/{$messageId}"]
                        ],
                        [
                            ["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]
                        ]
                    ],
                ]),
            ];
        }
    }

    public function showOffer($bot, $uuid, $menu = false)
    {
        $text = "";
        if (!$menu)
            $menu = [];

        $offer = Offers::on('tenant')->where('uuid', $uuid)->first();
        if ($offer && $offer->id > 0) {
            $title = "🟩";
            if (strtolower($offer->type) == "sell")
                $title = "🟥";
            $isOwner = $bot->actor->user_id == $offer->user_id;
            $text = $offer->renderAsTelegramMessage("{$title} *OFERTA*", $isOwner);
            $text .= "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");

            if ($isOwner)
                array_push($menu, [
                    ["text" => "❌ Eliminar", "callback_data" => "confirmation|deleteoffer-{$offer->id}|menu"]
                ]);
            else {
                $total = number_format(($offer->amount * $offer->price_per_usd), 2);
                array_push($menu, [
                    ["text" => "✅ Pagar {$total} {$offer->currency}", "callback_data" => "payoffer-{$offer->id}"]
                ]);
            }
        } else {
            $text = "🤔 *¡Que raro!*\n";
            $text .= "_No he encontrado la oferta_\n\n";
            $text .= "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");
        }

        array_push($menu, [
            ["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"],
        ]);

        return [
            "text" => $text,
            "chat" => ["id" => $bot->actor->user_id],
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),
        ];
    }
}