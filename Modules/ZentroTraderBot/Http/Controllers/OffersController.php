<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Currencies;
use Modules\ZentroTraderBot\Entities\OffersRatings;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Lang;
use Modules\Web3\Http\Controllers\CoingeckoController;
use Modules\Laravel\Services\Exchange\CambiocupService;
use Modules\Laravel\Services\NumberService;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Jobs\ProcessReputationUpdate;
use Modules\Web3\Http\Controllers\EscrowController;
use Modules\Web3\Traits\BlockchainTools;
use Modules\Web3\Services\ConfigService;
use Modules\Laravel\Services\DateService;
use Carbon\Carbon;
use Modules\Laravel\Services\TextService;

class OffersController extends Controller
{
    use BlockchainTools;

    public function sell($bot, $stars = "")
    {
        return $this->wizard($bot, 'sell', $stars);
    }

    public function buy($bot, $stars = "")
    {
        return $this->wizard($bot, 'buy', $stars);
    }

    public function wizard($bot, $type = 'sell', $stars = "")
    {
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $cacheKey = "wizard_{$bot->tenant->key}_{$userId}";

        $state = Cache::get($cacheKey, [
            'controller' => self::class,
            'method' => 'wizard',
            'step' => 'START',
            'data' => ['type' => $type], // Guardamos el tipo inicial
            'history' => []
        ]);

        // Mantenemos el tipo en el estado
        $isSell = ($state['data']['type'] ?? $type) === 'sell';

        // --- SALIDA Y RETROCESO ---
        if ($text === '/wizardcancel') {
            Cache::forget($cacheKey);
            $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);
            return [
                "text" =>
                    "❌ *" . Lang::get("zentrotraderbot::bot.wizard.cancelled_title") . "*\n" .
                    "_" . Lang::get("zentrotraderbot::bot.wizard.cancelled") . "_\n\n" .
                    "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.backtop2pmenu"), "callback_data" => "/p2pmenu"]],
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
            return $this->wizard($bot, $state['data']['type'], $stars);
        }

        switch ($state['step']) {
            case 'START':
                $state['step'] = 'STEP_AMOUNT';
                Cache::forever($cacheKey, $state);

            case 'STEP_AMOUNT':
                // --- VALIDACIÓN DE BALANCE (Solo si es venta) ---
                $balance = 0;
                if ($isSell) {
                    $walletCtrl = new TraderWalletController();
                    $suscriptor = Suscriptions::where('user_id', $userId)->first();
                    $balance = number_format($walletCtrl->getBalance($suscriptor), 2);
                }

                if ($text !== null && !in_array($text, ['/p2psell', '/p2pbuy'])) {
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
                                "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                                "◾️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(1), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                                "▫️ *" . Lang::get("zentrotraderbot::bot.wizard.step1.subtitle") . "*\n" .
                                "❌ " . Lang::get("zentrotraderbot::bot.wizard.step1.invalid_amount", ['value' => $text]) . "\n" .
                                "▫️ " . ($isSell
                                    ? Lang::get("zentrotraderbot::bot.wizard.step1.ask_sell_available", ['balance' => $balance])
                                    : Lang::get("zentrotraderbot::bot.wizard.step1.ask_buy")) . "\n" .
                                "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step1.number_hint", ['example' => "`" . ($balance ?: 100) . "`"]),
                            "chat" => ["id" => $userId],
                            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]]]]),
                            "editprevious" => 1
                        ];
                    }

                    // Validación de balance solo si es venta
                    if ($isSell && $text > $balance) {
                        return [
                            "text" =>
                                "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                                "◾️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(1), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                                "▫️ *" . Lang::get("zentrotraderbot::bot.wizard.step1.subtitle") . "*\n" .
                                "❌ " . Lang::get("zentrotraderbot::bot.wizard.step1.selling_too_much", ['amount' => $text]) . "\n" .
                                "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step1.ask_sell_available", ['balance' => $balance]) . "\n" .
                                "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step1.number_hint", ['example' => "`" . $balance . "`"]),
                            "chat" => ["id" => $userId],
                            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]]]]),
                            "editprevious" => 1
                        ];
                    }


                    $state['history'][] = ['step' => 'STEP_AMOUNT', 'data' => $state['data']];
                    $state['data']['amount'] = $text;
                    $state['step'] = 'STEP_CURRENCY'; // SALTO A MONEDA
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->wizard($bot, $state['data']['type'], $stars);
                }

                $amountPrompt = $isSell
                    ? "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step1.ask_sell_available", ['balance' => $balance]) . "\n" .
                    "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step1.number_hint", ['example' => "`" . $balance . "`"])
                    : "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step1.ask_buy") . "_";

                return [
                    "text" =>
                        "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                        "◾️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(1), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                        "▫️ *" . Lang::get("zentrotraderbot::bot.wizard.step1.subtitle") . "*\n" .
                        "▫️ \n" .
                        $amountPrompt,
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]]]]),
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
                    return $this->wizard($bot, $state['data']['type'], $stars);
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
                $buttons[] = [
                    ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
                    ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]
                ];

                $currencyPrompt = $isSell
                    ? Lang::get("zentrotraderbot::bot.wizard.step2.ask_sell")
                    : Lang::get("zentrotraderbot::bot.wizard.step2.ask_buy");

                return [
                    "text" =>
                        "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                        "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(2), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                        "◾️ *" . Lang::get("zentrotraderbot::bot.wizard.step2.subtitle") . "*\n" .
                        "▫️ \n" .
                        "▫️ _{$currencyPrompt}_\n" .
                        "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step2.select_available") . " 👇",
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
                                "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                                "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(3), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                                "▫️ *" . Lang::get("zentrotraderbot::bot.wizard.step3.subtitle", ['coin' => $coin]) . "*\n" .
                                "❌ " . Lang::get("zentrotraderbot::bot.wizard.step3.invalid_price", ['value' => $text]) . "\n" .
                                "▫️ " . ($isSell
                                    ? Lang::get("zentrotraderbot::bot.wizard.step3.ask_sell", ['coin' => $coin])
                                    : Lang::get("zentrotraderbot::bot.wizard.step3.ask_buy", ['coin' => $coin])) . "\n" .
                                "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step3.example", ['example' => "`" . number_format($val, 2) . "`"]),
                            "chat" => ["id" => $userId],
                            "reply_markup" => json_encode([
                                "inline_keyboard" => [
                                    [
                                        ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
                                        ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]
                                    ]
                                ]
                            ]),
                            "editprevious" => 1
                        ];
                    }
                    $state['history'][] = ['step' => 'STEP_PRICE', 'data' => $state['data']];
                    $state['data']['price'] = $text;
                    $state['step'] = 'STEP_METHOD'; // SALTO A MÉTODO
                    Cache::forever($cacheKey, $state);
                    $bot->message["text"] = null;
                    return $this->wizard($bot, $state['data']['type'], $stars);
                }

                return [
                    "text" =>
                        "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                        "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(3), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                        "▫️ *" . Lang::get("zentrotraderbot::bot.wizard.step3.subtitle", ['coin' => $coin]) . "*\n" .
                        "◾️ \n" .
                        "▫️ " . ($isSell
                            ? Lang::get("zentrotraderbot::bot.wizard.step3.ask_sell", ['coin' => $coin])
                            : Lang::get("zentrotraderbot::bot.wizard.step3.ask_buy", ['coin' => $coin])) . "\n" .
                        "▫️ " . Lang::get("zentrotraderbot::bot.wizard.step3.example", ['example' => "`" . number_format($val, 2) . "`"]),
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
                                ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
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
                    return $this->wizard($bot, $state['data']['type'], $stars);
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
                $buttons[] = [
                    ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
                    ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]
                ];

                $methodPrompt = $isSell
                    ? Lang::get("zentrotraderbot::bot.wizard.step4.ask_sell", ['currency' => $state['data']['currency']])
                    : Lang::get("zentrotraderbot::bot.wizard.step4.ask_buy", ['currency' => $state['data']['currency']]);

                return [
                    "text" =>
                        "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                        "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(4), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                        "▫️ *" . Lang::get("zentrotraderbot::bot.wizard.step4.subtitle") . "*\n" .
                        "▫️ \n" .
                        "▫️ _{$methodPrompt}_\n" .
                        "◾️ " . Lang::get("zentrotraderbot::bot.wizard.step4.select_available") . " 👇",
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
                    return $this->wizard($bot, $state['data']['type'], $stars);
                }

                $methodName = $state['data']['method_name'] ?? $state['data']['method'];
                $detailsPrompt = $isSell
                    ? "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step5.ask_sell", ['method' => $methodName]) . "_"
                    : "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step5.ask_buy", ['method' => $methodName]) . "_";

                return [
                    "text" =>
                        "✨ *" . Lang::get("zentrotraderbot::bot.wizard.title") . "*\n" .
                        "▫️ _" . Lang::get("zentrotraderbot::bot.wizard.step", ['n' => TextService::getNumberAsEmoji(5), 'total' => TextService::getNumberAsEmoji(5)]) . "_\n" .
                        "▫️ *" . Lang::get("zentrotraderbot::bot.wizard.step5.subtitle") . "*\n" .
                        "▫️ \n" .
                        $detailsPrompt . "\n" .
                        "◾️ *" . Lang::get("zentrotraderbot::bot.wizard.step5.be_explicit") . "*",
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
                                ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
                    "editprevious" => 1
                ];

            case 'CONFIRM':
                $this->deleteUserText($bot);
                if ($text === '/offerconfirm') {
                    return $this->publishOffer($bot, $state, $stars);
                }

                $total = number_format($state['data']['amount'] * $state['data']['price'], 2);
                $confirmLabel = $isSell ? "💵 " . Lang::get("zentrotraderbot::bot.wizard.confirm.selling") : "🟢 " . Lang::get("zentrotraderbot::bot.wizard.confirm.buying");
                $resultLabel = $isSell ? "💱 " . Lang::get("zentrotraderbot::bot.wizard.confirm.receiving") : "💱 " . Lang::get("zentrotraderbot::bot.wizard.confirm.paying");

                return [
                    "text" => "✨ *" . Lang::get("zentrotraderbot::bot.wizard.confirm.title") . "*\n"
                        . "{$confirmLabel}: *{$state['data']['amount']} USD* a *{$state['data']['price']} {$state['data']['currency']}/USD*\n"
                        . "{$resultLabel}: *{$total} {$state['data']['currency']}*\n"
                        . "🏦 {$state['data']['method_name']}: `{$state['data']['details']}`\n" .
                        "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext"),
                    "chat" => ["id" => $userId],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "✅ " . Lang::get("zentrotraderbot::bot.options.publish"), "callback_data" => "/offerconfirm"]],
                            [
                                ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
                                ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]
                            ]
                        ]
                    ]),
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

    private function publishOffer($bot, $state, $stars = "")
    {
        // 1. PRIMER GUARDADO: Creamos la instancia y la persistimos para obtener el ID real de la DB
        $offer = new Offers([
            'uuid' => (string) Str::uuid(),
            'user_id' => $bot->actor->user_id,
            'type' => $state['data']['type'], // Dinámico: sell o buy
            'amount' => $state['data']['amount'],
            'price_per_usd' => $state['data']['price'],
            'currency' => $state['data']['currency'],
            'payment_method' => $state['data']['method_name'],
            'payment_details' => $state['data']['details'],
            'status' => 'open',
            'network_id' => env("BASE_NETWORK"),
            'token_address' => env("BASE_TOKEN"),
        ]);
        // Asignamos los componentes aleatorios del código de soporte
        $offer->data = [
            "code" => [
                "prefix" => collect(range('A', 'Z'))->random(),
                "suffix" => Str::upper(Str::random(1))
            ]
        ];
        // Guardamos para disparar el ID autoincremental
        $offer->save();


        // 3. ENVÍO A TELEGRAM
        $response = TelegramController::sendMessage(
            $offer->getAsChannelMessage($bot->tenant->code, $stars),
            $bot->tenant->token
        );
        if ($response) {
            $array = json_decode($response, true);
            $messageId = $array["result"]["message_id"] ?? null;

            // 4. SEGUNDO GUARDADO (Actualización): Guardamos el ID del mensaje y activamos la oferta
            $currentData = $offer->data;
            $currentData["channel"] = ["message_id" => $messageId];
            $offer->update([
                'data' => $currentData
            ]);

            // Limpiamos el wizard de la caché
            Cache::forget("wizard_{$bot->tenant->key}_{$bot->actor->user_id}");

            $isSell = $state['data']['type'] === 'sell';
            $text = "✅ *" . Lang::get("zentrotraderbot::bot.offer_publish.title") . "*\n";
            $text .= "📢 " . Lang::get("zentrotraderbot::bot.offer_publish.published") . "\n\n";
            $text .= "⚠️ *" . Lang::get("zentrotraderbot::bot.offer_publish.security_notes") . "*\n";

            if ($isSell) {
                $text .= "🔒 *" . Lang::get("zentrotraderbot::bot.offer_publish.sell_lock") . "*\n";
                $text .= "🚫 *" . Lang::get("zentrotraderbot::bot.offer_publish.sell_rule") . "*\n";
            } else {
                $text .= "🔒 *" . Lang::get("zentrotraderbot::bot.offer_publish.buy_custody") . "*\n";
                $text .= "🚫 *" . Lang::get("zentrotraderbot::bot.offer_publish.buy_rule") . "*\n";
            }

            $text .= "⚖️ *" . Lang::get("zentrotraderbot::bot.offer_publish.arbitrage") . "*\n\n";
            $text .= "👍 " . ($isSell
                ? Lang::get("zentrotraderbot::bot.offer_publish.goodluck_sell")
                : Lang::get("zentrotraderbot::bot.offer_publish.goodluck_buy")) .
                " _" . Lang::get("zentrotraderbot::bot.offer_publish.notify") . "_";

            return [
                "text" => $text,
                "chat" => ["id" => $bot->actor->user_id],
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [
                            ["text" => "👀 " . Lang::get("zentrotraderbot::bot.options.view_my_offer"), "url" => "https://t.me/KashioChannel/{$messageId}"]
                        ],
                        [
                            ["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]
                        ]
                    ],
                ]),
            ];
        }

        // Opcional: Manejo de error si Telegram falla
        return [
            "text" => "❌ *" . Lang::get("zentrotraderbot::bot.offer_publish.error") . "*\n_" . Lang::get("zentrotraderbot::bot.offer_publish.error_retry") . "_",
            "chat" => ["id" => $bot->actor->user_id]
        ];
    }

    public function showOffer($bot, $code, $menu = false)
    {
        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();

        $text = "";
        if (!$menu)
            $menu = [];

        $offer = Offers::findByCode($code);
        if ($offer && $offer->id > 0) {
            $diff = DateService::getTimeDifference($offer->created_at->getTimestamp(), Carbon::now()->addSeconds($status["tradeTimeout"])->getTimestamp());

            $isSell = strtolower($offer->type) == "sell";
            $title = $isSell ? "🟥" : "🟩";
            $isOwner = $bot->actor->user_id == $offer->user_id;

            $text = $offer->renderAsTelegramMessage("{$title} *" . Lang::get("zentrotraderbot::bot.show_offer.offer_label") . "*", $isOwner);
            $text .= "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");


            switch ($offer->status) {
                case 'open':
                    if ($isOwner)
                        array_push($menu, [
                            ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.delete_offer"), "callback_data" => "confirmation|deleteoffer-{$offer->code}|menu"]
                        ]);
                    else {
                        $total = number_format(($offer->amount * $offer->price_per_usd), 2);
                        $btnAction = $isSell ? "✅ Comprar" : "💰 Vender";
                        array_push($menu, [
                            ["text" => "{$btnAction} por {$total} {$offer->currency}", "callback_data" => "/offerapply {$offer->code}"]
                        ]);
                    }
                    break;
                case 'locked':
                    if ($isOwner)
                        array_push($menu, [
                            ["text" => "⏱️ " . Lang::get("zentrotraderbot::bot.options.recover_long_wait", ['time' => $diff["legible"]]), "callback_data" => "/recoveroffer {$offer->code}"]
                        ]);
                    else {
                        array_push($menu, [
                            ["text" => "🧾 " . Lang::get("zentrotraderbot::bot.options.send_proof"), "callback_data" => "/comprobantoffer " . $offer->code]
                        ]);
                        array_push($menu, [
                            ["text" => "❌ " . Lang::get("telegrambot::bot.options.cancel"), "callback_data" => "/canceloffer " . $offer->code]
                        ]);
                    }
                    break;
                case 'disputed':
                    array_push($menu, [
                        ["text" => "🧾 " . Lang::get("zentrotraderbot::bot.options.send_evidence"), "callback_data" => "/evidenceoffer " . $offer->code],
                    ]);
                    break;
                default:
                    break;
            }
        } else {
            $text = "🤔 *" . Lang::get("zentrotraderbot::bot.show_offer.not_found_title") . "*\n";
            $text .= "_" . Lang::get("zentrotraderbot::bot.show_offer.not_found") . "_\n\n";
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

    public function updateStatus($bot, $text)
    {
        try {
            TelegramController::editMessageText([
                "message" => [
                    "text" => $text,
                    "chat" => ["id" => $bot->actor->user_id],
                    "message_id" => $bot->message["message_id"],
                ]
            ], $bot->tenant->token);
        } catch (\Throwable $th) {
        }
    }

    public function applyForOffer($bot, $code)
    {
        // 1. Usamos el $code o $offer->id para que NADIE más pueda tocar esta oferta por 2 minutos
        $lockKey = "applying_offer_lock_{$code}";

        if (!Cache::add($lockKey, $bot->actor->user_id, now()->addMinutes(2))) {
            $whoIsApplying = Cache::get($lockKey);
            // Si el que tiene el candado soy yo mismo, es un reintento de Telegram, dejamos pasar.
            // Si es otro ID, detenemos el proceso.
            if ($whoIsApplying != $bot->actor->user_id) {
                $this->updateStatus($bot, "⚠️ " . Lang::get("zentrotraderbot::bot.apply_offer.being_processed"));
                return true;
            }
        }

        // 2. Localización y Validación de la Oferta
        $offer = Offers::findByCode($code);
        if (!$offer || strtoupper($offer->status) !== 'OPEN') { // Validación extra de estado
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.apply_offer.not_available", ['code' => $code]));
            Cache::forget($lockKey);
            return false;
        }

        // Guardamos el message_id para ediciones futuras desde el Listener
        $currentData = $offer->data ?? [];
        $currentData["apply"] = [
            "message_id" => $bot->message["message_id"],
            "user_id" => $bot->actor->user_id // Guardamos quién aplicó
        ];
        $offer->update(['data' => $currentData]);

        // 3. Configuración de Red y Roles 
        $network = ConfigService::getNetworks(env("BASE_NETWORK"));
        $tokenInfo = ConfigService::getToken(env('BASE_TOKEN'), $network["chainId"]);
        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));

        $isSell = strtolower($offer->type) == "sell";
        $sellerUserId = $isSell ? $offer->user_id : $bot->actor->user_id;
        $buyerUserId = $isSell ? $bot->actor->user_id : $offer->user_id;

        $seller = Suscriptions::on('tenant')->where('user_id', $sellerUserId)->first();
        $buyer = Suscriptions::on('tenant')->where('user_id', $buyerUserId)->first();

        if (!$seller || !$buyer) {
            Cache::forget($lockKey);
            return false;
        }

        // 4. Preparación Blockchain
        $key = decryptValue($seller->data['wallet']['private_key']);
        $buyerAddress = $buyer->data['wallet']['address'];

        // Monto con decimales correctos (usando bcmath como tenías antes)
        $amountWei = bcmul($offer->amount, bcpow(10, $tokenInfo["decimals"]));
        $escrow = new EscrowController();
        $deadline = time() + 3600; // 1 hora de validez para el Permit

        // ESTADO 1: Preparación Criptográfica (Local y rápido)
        $this->updateStatus($bot, "⌛️ *" . Lang::get("zentrotraderbot::bot.apply_offer.step1") . "*");

        if (env("DEBUG_MODE", false))
            Log::debug("🐞 OffersController applyForOffer:", [
                "code" => $code,
                "sellerUserId" => $sellerUserId,
                "buyerUserId" => $buyerUserId,
                "offer" => $offer,
                "deadline" => $deadline,
            ]);

        try {
            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($bot, $escrow, $key, $offer, $amountWei, $buyerAddress, $deadline, $network) {

                $this->updateStatus($bot, "⌛️ *" . Lang::get("zentrotraderbot::bot.apply_offer.step2") . "*");
                $relayerKey = env('TRADER_BOT_KEY');

                return $escrow->createTradeWithSignature(
                    $rpc,
                    $relayerKey,
                    $key,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $offer->id,                  // ID del trade para el contrato
                    env('BASE_TOKEN'),
                    $amountWei,
                    $buyerAddress,
                    $deadline,
                    env('ETHERSCAN_API_KEY')     // Para obtener el selector del contrato
                );
            });

            if (!$txHash) {
                $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.apply_offer.no_hash"));
                return false;
            }

            $this->updateStatus($bot, "⌛️ *" . Lang::get("zentrotraderbot::bot.apply_offer.step3") . "*");

            // NOTA: No liberamos el $lockKey aquí, dejamos que expire o
            // que el Listener lo haga al confirmar, para evitar "clicks" rápidos.
            return $txHash;

        } catch (\Exception $e) {
            Log::error("🆘 OffersController applyForOffer:", [
                "code" => $code,
                "sellerUserId" => $sellerUserId,
                "buyerUserId" => $buyerUserId,
                "offer" => $offer,
                "deadline" => $deadline,
                "message" => $e->getMessage()
            ]);

            // Manejo de error "ID already exists" (Transacción enviada pero error en respuesta RPC)
            if (str_contains($e->getMessage(), 'ID already exists')) {
                $this->updateStatus($bot, "⌛️ *" . Lang::get("zentrotraderbot::bot.apply_offer.step3") . "*");
                return true;
            }

            // Si es un error real, liberamos para permitir reintento
            Cache::forget($lockKey);
            $this->updateStatus($bot, "❌ *" . Lang::get("zentrotraderbot::bot.apply_offer.network_error") . "*\n" . $e->getMessage());
            return false;
        }
    }

    public function recoverOffer($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer)
            return false;

        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();

        $this->updateStatus($bot, "⏳ *" . Lang::get("zentrotraderbot::bot.recover_offer.checking") . "*");

        // 1. Configuración de Red
        $network = ConfigService::getNetworks(env("BASE_NETWORK"));
        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));
        $escrow = new EscrowController();

        // 2. Obtener datos del trade desde la Blockchain
        $blockchainTrade = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $network, $offer) {
            return $escrow->getTradeById(
                $rpc,
                env('ESCROW_CONTRACT'),
                $network['chainId'],
                $offer->id,
                env('ETHERSCAN_API_KEY')
            );
        });

        if (!$blockchainTrade) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.recover_offer.not_found_blockchain"));
            return false;
        }

        // 3. Validaciones de Seguridad (Tiempo de expiración)
        $now = time();
        $timeoutAt = (int) $blockchainTrade['createdAt'] + (int) $status["tradeTimeout"];

        if ($now < $timeoutAt) {
            $minutes = ceil(($timeoutAt - $now) / 60);
            $this->updateStatus($bot, "🚫 " . Lang::get("zentrotraderbot::bot.recover_offer.wait", ['minutes' => $minutes]));
            return false;
        }

        // Guardamos metadata de la recuperación
        $currentData = $offer->data ?? [];
        $currentData["recover"] = [
            "message_id" => $bot->message["message_id"],
            "user_id" => $bot->actor->user_id // Guardamos quién aplicó para la recuperacion
        ];
        $offer->update(['data' => $currentData]);

        // 4. Ejecución Gasless (expireTradeWithSignature)
        $this->updateStatus($bot, "⚖️ *" . Lang::get("zentrotraderbot::bot.recover_offer.requesting") . "*");

        try {
            // Obtenemos la llave del vendedor para FIRMAR (No gasta gas)
            $seller = Suscriptions::on('tenant')->where('user_id', $bot->actor->user_id)->first();
            $userPrivateKey = decryptValue($seller->data['wallet']['private_key']);

            // Obtenemos la llave de la Tesorería para PAGAR el gas (Relayer)
            $relayerKey = env('TRADER_BOT_KEY');

            // Definimos un deadline para la firma (ej. 1 hora desde ahora)
            $deadline = time() + 3600;

            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $relayerKey, $userPrivateKey, $network, $offer, $deadline) {
                return $escrow->expireTradeWithSignature(
                    $rpc,
                    $relayerKey,      // Paga el POL
                    $userPrivateKey,   // Firma el mensaje EIP-712
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $offer->id,
                    $deadline,
                    env('ETHERSCAN_API_KEY')
                );
            });

            if (!$txHash) {
                $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.recover_offer.rejected"));
                return false;
            }

            $this->updateStatus($bot, "✅ *" . Lang::get("zentrotraderbot::bot.recover_offer.success", ['amount' => number_format($offer->amount, 2)]) . "*");

            return $txHash;

        } catch (\Exception $e) {
            $this->updateStatus($bot, "❌ *" . Lang::get("zentrotraderbot::bot.recover_offer.error") . "* " . $e->getMessage());
            return false;
        }
    }

    public function rateOfferPerformance($bot, $code, $stars)
    {
        $text = "";
        $menu = [];

        // 2. Buscar la oferta
        $offer = Offers::findByCode($code);
        if ($offer) {
            // Sugerencia de validación rápida
            $suscriptor = Suscriptions::findByAddress($offer->seller_address);
            if ($suscriptor && $suscriptor->user_id == $bot->actor->user_id) {
                $suscriptor = Suscriptions::findByAddress($offer->buyer_address);
            }
            if (!$suscriptor) {
                Log::error("No se pudo identificar la contraparte para calificar la oferta {$code}");
                return;
            }

            $evaluated_user_id = $suscriptor->user_id;

            try {
                // 3. Guardar el voto en la fuente de verdad (SQL)
                OffersRatings::create([
                    'offer_id' => $offer->id,
                    'rater_user_id' => $bot->actor->user_id,
                    'rated_user_id' => $evaluated_user_id, // El que recibe la fama
                    'stars' => $stars
                ]);

                // 4. ¡AQUÍ SE DISPARA EL JOB!
                ProcessReputationUpdate::dispatch($evaluated_user_id, $stars, $bot->tenant->key);
            } catch (\Throwable $th) {

            }

            $text = "✅ *" . Lang::get("zentrotraderbot::bot.rate_offer.success_title") . "*\n";
            $text .= "🙏 " . Lang::get("zentrotraderbot::bot.rate_offer.thanks") . "\n\n";
            $text .= "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");

        } else {
            $text = "🤔 *" . Lang::get("zentrotraderbot::bot.rate_offer.not_found_title") . "*\n";
            $text .= "_" . Lang::get("zentrotraderbot::bot.rate_offer.not_found") . "_\n\n";
            $text .= "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");
        }
        array_push($menu, [
            ["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"],
        ]);

        $payload = [
            "message" => [
                "text" => $text,
                "chat" => ["id" => $bot->actor->user_id],
                "message_id" => $bot->message["message_id"],
                "reply_markup" => json_encode([
                    "inline_keyboard" => $menu,
                ]),
            ]
        ];
        try {
            // Editamos el mensaje (esto quita el botón si el estado cambió a 'taken')
            TelegramController::editMessageText($payload, $bot->tenant->token);
        } catch (\Throwable $th) {
        }
    }

    public function getActiveOffers($suscriptor, $page = 1)
    {

        $address = strtolower($suscriptor->data['wallet']['address']);
        $activeStatuses = ['OPEN', 'LOCKED', 'SIGNED', 'DISPUTED', 'EXPIRED'];

        $perPage = 8; // Número ideal de ofertas por pantalla
        $offset = ($page - 1) * $perPage;

        $userId = $suscriptor->user_id; // El ID de Telegram del suscriptor
        $query = Offers::on('tenant')
            ->whereIn('status', $activeStatuses)
            ->where(function ($query) use ($address, $userId) {
                $query->asSeller($address)
                    ->orWhere->asBuyer($address)
                    ->orWhere('user_id', $userId); // Filtro por el creador original
            });

        $total = $query->count();
        $offers = $query->orderBy('updated_at', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        if ($offers->isEmpty()) {
            return [
                "text" => "📭 *" . Lang::get("zentrotraderbot::bot.active_offers.empty") . "*\n" .
                    Lang::get("zentrotraderbot::bot.active_offers.empty_cta"),
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [
                            ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.backtop2pmenu"), "callback_data" => "/p2pmenu"]
                        ]
                    ]
                ])
            ];
        }

        // 2. Construir el menú visual
        $text = "📋 *" . Lang::get("zentrotraderbot::bot.active_offers.title") . "*\n";
        $text .= "👇 " . Lang::get("telegrambot::bot.prompts.chooseoneoption");

        $buttons = [];
        foreach ($offers as $offer) {
            $isSeller = strtolower($offer->seller_address) === $address;
            $roleEmoji = $isSeller ? Offers::getTypeEmoji("sell")["color"] : Offers::getTypeEmoji("buy")["color"];
            $emoji = Offers::getStatusEmoji($offer->status);
            $statusEmoji = $emoji["icon"];

            // Etiqueta del botón: [Icono Rol] [ID] [Estado] - [Monto] USD
            $label = "{$roleEmoji} {$offer->code} - " . number_format($offer->amount, 2) . " USD {$statusEmoji}";

            $buttons[] = [
                ["text" => $label, "callback_data" => "/showoffer {$offer->code}"]
            ];
        }

        $navigation = [];
        if ($page > 1)
            $navigation[] = ["text" => "◀️ " . Lang::get("zentrotraderbot::bot.options.previous"), "callback_data" => "/activeoffers " . ($page - 1)];
        if ($total > ($page * $perPage))
            $navigation[] = ["text" => Lang::get("zentrotraderbot::bot.options.next") . " ▶️", "callback_data" => "/activeoffers " . ($page + 1)];

        if (!empty($navigation))
            $buttons[] = $navigation;


        // Botón para volver
        $buttons[] = [["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.backtop2pmenu"), "callback_data" => "/p2pmenu"]];
        $buttons[] = [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]];

        return [
            "text" => $text,
            "reply_markup" => json_encode(["inline_keyboard" => $buttons])
        ];
    }

    /**
     * Buyer confirms payment was sent and signs the trade on-chain.
     * The relayer pays gas; buyer signs off-chain only.
     */
    public function comprobantoffer($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"));
            return false;
        }

        if (!in_array(strtoupper($offer->status), ['LOCKED', 'SIGNED'])) {
            $this->updateStatus($bot, "⚠️ " . Lang::get("zentrotraderbot::bot.sign_offer.wrong_state"));
            return false;
        }

        $buyer = Suscriptions::on('tenant')->where('user_id', $bot->actor->user_id)->first();
        if (!$buyer) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.account_not_found"));
            return false;
        }

        $network = ConfigService::getNetworks(env("BASE_NETWORK"));
        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));

        $buyerKey = decryptValue($buyer->data['wallet']['private_key']);
        $relayerKey = env('TRADER_BOT_KEY');
        $deadline = time() + 3600;

        $this->updateStatus($bot, "⌛️ " . Lang::get("zentrotraderbot::bot.sign_offer.sending_proof"));

        try {
            $escrow = new EscrowController();
            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $relayerKey, $buyerKey, $network, $offer, $deadline) {
                return $escrow->signTradeWithSignature(
                    $rpc,
                    $relayerKey,
                    $buyerKey,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $offer->id,
                    $deadline,
                    env('ETHERSCAN_API_KEY')
                );
            });

            if (!$txHash) {
                $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.no_confirm_payment"));
                return false;
            }

            $this->updateStatus($bot, "✅ *" . Lang::get("zentrotraderbot::bot.sign_offer.proof_sent") . "*");
            return $txHash;

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ID already exists')) {
                $this->updateStatus($bot, "✅ *" . Lang::get("zentrotraderbot::bot.sign_offer.proof_sent") . "*");
                return true;
            }
            $this->updateStatus($bot, "❌ *" . Lang::get("zentrotraderbot::bot.sign_offer.error") . "*\n" . $e->getMessage());
            return false;
        }
    }

    /**
     * Seller (or the pending signer) confirms receipt and signs the trade on-chain.
     * The relayer pays gas; signer signs off-chain only.
     */
    public function signOffer($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"));
            return false;
        }

        if (!in_array(strtoupper($offer->status), ['LOCKED', 'SIGNED'])) {
            $this->updateStatus($bot, "⚠️ " . Lang::get("zentrotraderbot::bot.sign_offer.wrong_state"));
            return false;
        }

        $signer = Suscriptions::on('tenant')->where('user_id', $bot->actor->user_id)->first();
        if (!$signer) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.account_not_found"));
            return false;
        }

        $network = ConfigService::getNetworks(env("BASE_NETWORK"));
        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));

        $signerKey = decryptValue($signer->data['wallet']['private_key']);
        $relayerKey = env('TRADER_BOT_KEY');
        $deadline = time() + 3600;

        $this->updateStatus($bot, "⌛️ " . Lang::get("zentrotraderbot::bot.sign_offer.confirming_receipt"));

        try {
            $escrow = new EscrowController();
            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $relayerKey, $signerKey, $network, $offer, $deadline) {
                return $escrow->signTradeWithSignature(
                    $rpc,
                    $relayerKey,
                    $signerKey,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $offer->id,
                    $deadline,
                    env('ETHERSCAN_API_KEY')
                );
            });

            if (!$txHash) {
                $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.no_sign"));
                return false;
            }

            $this->updateStatus($bot, "✅ *" . Lang::get("zentrotraderbot::bot.sign_offer.confirmation_sent") . "*");
            return $txHash;

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ID already exists')) {
                $this->updateStatus($bot, "✅ *" . Lang::get("zentrotraderbot::bot.sign_offer.confirmation_sent") . "*");
                return true;
            }
            $this->updateStatus($bot, "❌ *" . Lang::get("zentrotraderbot::bot.sign_offer.error") . "*\n" . $e->getMessage());
            return false;
        }
    }

    /**
     * Buyer cancels a LOCKED trade on-chain via meta-transacción.
     * The relayer pays gas; buyer signs off-chain only.
     */
    public function cancelOnChain($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.cancel_onchain.not_found"));
            return false;
        }

        if (strtoupper($offer->status) !== 'LOCKED') {
            $this->updateStatus($bot, "⚠️ " . Lang::get("zentrotraderbot::bot.cancel_onchain.wrong_state"));
            return false;
        }

        $buyer = Suscriptions::on('tenant')->where('user_id', $bot->actor->user_id)->first();
        if (!$buyer || strtolower($buyer->data['wallet']['address']) !== strtolower($offer->buyer_address)) {
            $this->updateStatus($bot, "🚫 " . Lang::get("zentrotraderbot::bot.cancel_onchain.not_buyer"));
            return false;
        }

        $network = ConfigService::getNetworks(env("BASE_NETWORK"));
        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));

        $buyerKey = decryptValue($buyer->data['wallet']['private_key']);
        $relayerKey = env('TRADER_BOT_KEY');
        $deadline = time() + 3600;

        $this->updateStatus($bot, "⌛️ " . Lang::get("zentrotraderbot::bot.cancel_onchain.processing"));

        try {
            $escrow = new EscrowController();
            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $relayerKey, $buyerKey, $network, $offer, $deadline) {
                return $escrow->cancelTradeWithSignature(
                    $rpc,
                    $relayerKey,
                    $buyerKey,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $offer->id,
                    $deadline,
                    env('ETHERSCAN_API_KEY')
                );
            });

            if (!$txHash) {
                $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.cancel_onchain.no_cancel"));
                return false;
            }

            $this->updateStatus($bot, "⌛️ " . Lang::get("zentrotraderbot::bot.cancel_onchain.sent"));
            return $txHash;

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ID already exists')) {
                $this->updateStatus($bot, "⌛️ " . Lang::get("zentrotraderbot::bot.cancel_onchain.sent"));
                return true;
            }
            $this->updateStatus($bot, "❌ *" . Lang::get("zentrotraderbot::bot.cancel_onchain.error") . "*\n" . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancela una oferta abierta: la elimina del canal y actualiza el estado en DB.
     */
    public function cancelOffer($bot, $code)
    {
        $reply = [
            "text" => "",
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.backtop2pmenu"), "callback_data" => "/p2pmenu"]],
                    [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                ],
            ]),
        ];

        try {
            $userId = $bot->actor->user_id;

            // 1. Buscar la oferta en el tenant actual
            $offer = Offers::findByCode($code);

            if (!$offer) {
                $reply["text"] = "⚠️ " . Lang::get("zentrotraderbot::bot.delete_offer.not_found");
                return $reply;
            }

            // 2. Validar propiedad: Solo el creador puede cancelarla
            if ((int) $offer->user_id !== $userId) {
                $reply["text"] = "🚫 " . Lang::get("zentrotraderbot::bot.delete_offer.no_permission");
                return $reply;
            }

            // 3. Verificar que esté abierta (Si ya hay un Escrow LOCK, no se puede cancelar así)
            if (strtoupper($offer->status) !== 'OPEN') {
                $reply["text"] = "🛑 " . Lang::get("zentrotraderbot::bot.delete_offer.wrong_state");
                return $reply;
            }

            // 4. ELIMINAR DEL CANAL
            if (isset($offer->data['channel']['message_id'])) {
                try {
                    TelegramController::deleteMessage([
                        "message" => [
                            "chat" => ["id" => env("TRADER_BOT_CHANNEL")],
                            "id" => $offer->data['channel']['message_id']
                        ]
                    ], $bot->tenant->token);
                } catch (\Throwable $th) {
                    //throw $th;
                }
            }

            // 5. ACTUALIZAR BASE DE DATOS
            // Usamos updateStatus para que el Observer dispare cualquier notificación necesaria
            $offer->updateStatus('CANCELLED', [
                'updated_at' => now(),
            ]);

            $reply["text"] = "✅ *" . Lang::get("zentrotraderbot::bot.delete_offer.success_title") . "*\n" .
                "_" . Lang::get("zentrotraderbot::bot.delete_offer.success") . "_\n\n" .
                "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");

        } catch (\Exception $e) {
            Log::error("🆘 Error cancelando oferta {$code}: " . $e->getMessage());

            $reply["text"] = "❌ " . Lang::get("zentrotraderbot::bot.delete_offer.error");
        }


        return $reply;
    }
}