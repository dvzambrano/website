<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Currencies;
use Modules\ZentroTraderBot\Entities\OffersRatings;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Lang;
use Modules\Web3\Http\Controllers\CoingeckoController;
use Modules\Laravel\Services\Exchange\CambiocupService;
use Modules\Laravel\Services\NumberService;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Jobs\ProcessReputationUpdate;
use Modules\Web3\Http\Controllers\EscrowController;
use Modules\TelegramBot\Entities\Actors;
use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\Web3\Traits\BlockchainTools;
use Modules\Web3\Services\ConfigService;
use Modules\Laravel\Services\DateService;
use Carbon\Carbon;
use Modules\Laravel\Services\TextService;
use Modules\ZentroTraderBot\Jobs\UpdateOfferInChannel;
use Modules\ZentroTraderBot\Jobs\SendRecoverReminder;

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
        $self = $this;
        $steps = [
            ['name' => 'STEP_AMOUNT', 'handler' => fn($b, $s) => $self->stepAmount($b, $s)],
            ['name' => 'STEP_CURRENCY', 'handler' => fn($b, $s) => $self->stepCurrency($b, $s)],
            ['name' => 'STEP_PRICE', 'handler' => fn($b, $s) => $self->stepPrice($b, $s)],
            ['name' => 'STEP_METHOD', 'handler' => fn($b, $s) => $self->stepMethod($b, $s)],
            ['name' => 'STEP_DETAILS', 'handler' => fn($b, $s) => $self->stepDetails($b, $s)],
            ['name' => 'CONFIRM', 'handler' => fn($b, $s) => $self->stepConfirm($b, $s)],
        ];

        return (new WizardController())->run($bot, $steps, [
            'controller' => self::class,
            'method' => 'wizard',
            'initialData' => ['type' => $type],
            'onComplete' => fn($b, $s) => $self->publishOffer($b, $s, $stars),
            'onCancel' => fn($b) => $self->cancelWizardResponse($b),
        ]);
    }

    private function cancelWizardResponse($bot): array
    {
        $userId = $bot->actor->user_id;
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
                    [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]],
                ],
            ]),
            "editprevious" => $isCallback ? 1 : 0,
        ];
    }

    private function stepAmount($bot, array $state): array
    {
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $isSell = ($state['data']['type'] ?? 'sell') === 'sell';
        $balanceRaw = 0.0;
        $balance = '0';

        if ($isSell) {
            $walletCtrl = new TraderWalletController();
            $suscriptor = Suscriptions::where('user_id', $userId)->first();
            $balanceRaw = (float) $walletCtrl->getBalance($suscriptor);
            $balance = number_format($balanceRaw, 2);
        }

        if ($text !== null && !in_array($text, ['/p2psell', '/p2pbuy'])) {
            $this->deleteUserText($bot);

            try {
                $parsed = NumberService::parse($text);
                if (is_numeric($parsed))
                    $text = $parsed;
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
                    "editprevious" => 1,
                ];
            }

            if ($isSell && (float) $text > $balanceRaw) {
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
                    "editprevious" => 1,
                ];
            }

            return ['__advance' => true, 'merge' => ['amount' => $text]];
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
            "editprevious" => $text == null ? 1 : 0,
        ];
    }

    private function stepCurrency($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $isSell = ($state['data']['type'] ?? 'sell') === 'sell';

        if ($text !== null) {
            return ['__advance' => true, 'merge' => ['currency' => $text]];
        }

        $currencies = Currencies::where('is_active', true)->has('paymentmethods')->get();
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
            ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"],
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
            "editprevious" => 1,
        ];
    }

    private function stepPrice($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $isSell = ($state['data']['type'] ?? 'sell') === 'sell';
        $coin = $state['data']['currency'];

        $cgval = CoingeckoController::getLivePrice("tether", $coin);
        $ccval = ($cgval <= 0) ? CambiocupService::getRate($coin) : null;
        $val = $cgval > 0 ? $cgval : ($ccval > 0 ? $ccval : 1.02);

        $navButtons = [
            ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
            ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"],
        ];

        if ($text !== null) {
            try {
                $parsed = NumberService::parse($text);
                if (is_numeric($parsed))
                    $text = $parsed;
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
                    "reply_markup" => json_encode(["inline_keyboard" => [$navButtons]]),
                    "editprevious" => 1,
                ];
            }

            return ['__advance' => true, 'merge' => ['price' => $text]];
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
            "reply_markup" => json_encode(["inline_keyboard" => [$navButtons]]),
            "editprevious" => 1,
        ];
    }

    private function stepMethod($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $isSell = ($state['data']['type'] ?? 'sell') === 'sell';

        if ($text !== null) {
            $currency = Currencies::where('code', $state['data']['currency'])->first();
            $methodInfo = $currency->paymentmethods()->where('identifier', $text)->first();
            return [
                '__advance' => true,
                'merge' => [
                    'method' => $text,
                    'method_name' => $methodInfo->name ?? $text,
                ]
            ];
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
            ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"],
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
            "editprevious" => 1,
        ];
    }

    private function stepDetails($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $isSell = ($state['data']['type'] ?? 'sell') === 'sell';
        $methodName = $state['data']['method_name'] ?? $state['data']['method'];

        if ($text !== null) {
            return ['__advance' => true, 'merge' => ['details' => $text]];
        }

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
                        ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"],
                    ]
                ],
            ]),
            "editprevious" => 1,
        ];
    }

    private function stepConfirm($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text = $bot->message["text"] ?? null;
        $userId = $bot->actor->user_id;
        $isSell = ($state['data']['type'] ?? 'sell') === 'sell';

        if ($text === '/offerconfirm') {
            return ['__advance' => true]; // onComplete → publishOffer
        }

        $total = number_format($state['data']['amount'] * $state['data']['price'], 2);
        $confirmLabel = $isSell ? "💵 " . Lang::get("zentrotraderbot::bot.wizard.confirm.selling") : "🟢 " . Lang::get("zentrotraderbot::bot.wizard.confirm.buying");
        $resultLabel = $isSell ? "💱 " . Lang::get("zentrotraderbot::bot.wizard.confirm.receiving") : "💱 " . Lang::get("zentrotraderbot::bot.wizard.confirm.paying");

        return [
            "text" =>
                "✨ *" . Lang::get("zentrotraderbot::bot.wizard.confirm.title") . "*\n" .
                "{$confirmLabel}: *{$state['data']['amount']} USD* a *{$state['data']['price']} {$state['data']['currency']}/USD*\n" .
                "{$resultLabel}: *{$total} {$state['data']['currency']}*\n" .
                "🏦 {$state['data']['method_name']}: `{$state['data']['details']}`\n" .
                "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext"),
            "chat" => ["id" => $userId],
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "✅ " . Lang::get("zentrotraderbot::bot.options.publish"), "callback_data" => "/offerconfirm"]],
                    [
                        ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.back"), "callback_data" => "/wizardprevious"],
                        ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"],
                    ],
                ],
            ]),
            "editprevious" => 1,
        ];
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

    /**
     * Edita el mensaje del canal quitando el boton "Aplicar" inmediatamente,
     * para evitar que otro usuario aplique mientras se procesa la transaccion.
     * Si falla silenciosamente: la logica de lock de Cache sigue protegiendo el flujo.
     */
    private function disableChannelApplyButton(Offers $offer, $botTenant): void
    {
        $messageId = $offer->data['channel']['message_id'] ?? null;
        if (!$messageId)
            return;

        try {
            $messageData = $offer->getAsChannelMessage($botTenant->code);
            TelegramController::editMessageText([
                "message" => [
                    "message_id" => $messageId,
                    "chat" => ["id" => env("TRADER_BOT_CHANNEL")],
                    "text" => $messageData['message']['text'],
                    "reply_markup" => json_encode(["inline_keyboard" => []]),
                ],
            ], $botTenant->token);
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

        // Quitamos el botón del canal inmediatamente para que nadie más pueda aplicar
        // mientras la transacción blockchain se está procesando.
        $this->disableChannelApplyButton($offer, $bot->tenant);

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
            UpdateOfferInChannel::dispatch($bot->tenant->key, $code);
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

        $relayerKey = decryptValue(env('TRADER_BOT_KEY'));

        try {
            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($bot, $escrow, $key, $offer, $amountWei, $buyerAddress, $deadline, $network, $relayerKey, $tokenInfo) {

                $this->updateStatus($bot, "⌛️ *" . Lang::get("zentrotraderbot::bot.apply_offer.step2") . "*");

                return $escrow->createTradeWithSignature(
                    $rpc,
                    $relayerKey,
                    $key,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $offer->id,                  // ID del trade para el contrato
                    $tokenInfo['address'],
                    $amountWei,
                    $buyerAddress,
                    $deadline,
                    env('ETHERSCAN_API_KEY')     // Para obtener el selector del contrato
                );
            });


            Log::debug("🐞 OffersController applyForOffer:", [
                "txHash" => $txHash,
            ]);

            if (!$txHash) {
                $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.apply_offer.no_hash"));
                Cache::forget($lockKey);
                UpdateOfferInChannel::dispatch($bot->tenant->key, $code);
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

            // Si es un error real, liberamos para permitir reintento y restauramos el boton
            Cache::forget($lockKey);
            UpdateOfferInChannel::dispatch($bot->tenant->key, $code);
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
            $secondsLeft = $timeoutAt - $now;
            $minutes = ceil($secondsLeft / 60);

            $this->updateStatus(
                $bot,
                "🚫 " . Lang::get("zentrotraderbot::bot.recover_offer.wait", ['minutes' => $minutes]) . "\n\n"
                . "🔔 " . Lang::get("zentrotraderbot::bot.recover_offer.wait_scheduled")
            );

            // Programar el recordatorio para cuando expire el plazo exactamente
            SendRecoverReminder::dispatch(
                $bot->tenant->key,
                $code,
                $bot->actor->user_id
            )->delay(now()->addSeconds($secondsLeft));

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
            $relayerKey = decryptValue(env('TRADER_BOT_KEY'));

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

    // =========================================================
    // RATING WIZARD — Paso de comentario opcional tras calificar
    // =========================================================

    public function startRatingWizard($bot, $code, $stars)
    {
        $offer = Offers::findByCode($code);
        if (!$offer) {
            $payload = [
                "message" => [
                    "text" => "🤔 *" . Lang::get("zentrotraderbot::bot.rate_offer.not_found_title") . "*\n"
                        . "_" . Lang::get("zentrotraderbot::bot.rate_offer.not_found") . "_\n\n"
                        . "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext"),
                    "chat"         => ["id" => $bot->actor->user_id],
                    "message_id"   => $bot->message["message_id"],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
                ],
            ];
            try { TelegramController::editMessageText($payload, $bot->tenant->token); } catch (\Throwable $th) {}
            return;
        }

        $suscriptor = Suscriptions::findByAddress($offer->seller_address);
        if ($suscriptor && $suscriptor->user_id == $bot->actor->user_id) {
            $suscriptor = Suscriptions::findByAddress($offer->buyer_address);
        }
        if (!$suscriptor) return;

        $bot->message['text'] = null; // primer render: mostrar prompt, no tratar el callback_data como input
        return $this->runRatingWizard($bot, [
            'offer_code'    => $code,
            'stars'         => $stars,
            'rated_user_id' => $suscriptor->user_id,
        ]);
    }

    public function ratingWizard($bot)
    {
        return $this->runRatingWizard($bot);
    }

    private function runRatingWizard($bot, array $initialData = [])
    {
        $self = $this;
        return (new WizardController())->run($bot, [
            ['name' => 'COMMENT', 'handler' => fn($b, $s) => $self->stepRatingComment($b, $s)],
        ], [
            'controller'  => self::class,
            'method'      => 'ratingWizard',
            'initialData' => $initialData,
            'onComplete'  => fn($b, $s) => $self->completeRating($b, $s),
            'onCancel'    => fn($b) => [
                "text"         => "❌ " . Lang::get("zentrotraderbot::bot.rate_offer.cancelled"),
                "chat"         => ["id" => $b->actor->user_id],
                "editprevious" => (isset($b->callback_query) || ($b->is_callback ?? false)) ? 1 : 0,
                "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
            ],
        ]);
    }

    private function stepRatingComment($bot, array $state): array
    {
        $text   = $bot->message['text'] ?? '';
        $userId = $bot->actor->user_id;
        $code   = $state['data']['offer_code'];
        $stars  = $state['data']['stars'];

        if ($text !== '') {
            $comment = str_contains(strtolower($text), 'ratingskip') ? null : (trim($text) ?: null);
            return ['__advance' => true, 'merge' => ['comment' => $comment]];
        }

        return [
            "text"         => "⭐ " . $stars . "\n\n💬 " . Lang::get("zentrotraderbot::bot.rate_offer.comment_prompt"),
            "chat"         => ["id" => $userId],
            "editprevious" => 1,
            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "⏭ " . Lang::get("zentrotraderbot::bot.rate_offer.comment_skip"), "callback_data" => "ratingskip {$code}"]]]]),
        ];
    }

    private function completeRating($bot, array $state): array
    {
        $userId      = $bot->actor->user_id;
        $ratedUserId = $state['data']['rated_user_id'] ?? null;
        $isCallback  = isset($bot->callback_query) || ($bot->is_callback ?? false);

        if ($ratedUserId) {
            try {
                $offer = Offers::findByCode($state['data']['offer_code']);
                OffersRatings::create([
                    'offer_id'      => $offer ? $offer->id : null,
                    'rater_user_id' => $userId,
                    'rated_user_id' => $ratedUserId,
                    'stars'         => $state['data']['stars'],
                    'comment'       => $state['data']['comment'] ?? null,
                ]);
                ProcessReputationUpdate::dispatch($ratedUserId, $state['data']['stars'], $bot->tenant->key);
            } catch (\Throwable $th) {}
        }

        $successText = "✅ *" . Lang::get("zentrotraderbot::bot.rate_offer.success_title") . "*\n"
            . "🙏 " . Lang::get("zentrotraderbot::bot.rate_offer.thanks") . "\n\n"
            . "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");
        $menu = json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]);

        if ($isCallback) {
            $payload = ["message" => ["text" => $successText, "chat" => ["id" => $userId], "message_id" => $bot->message["message_id"], "reply_markup" => $menu]];
            try { TelegramController::editMessageText($payload, $bot->tenant->token); } catch (\Throwable $th) {}
            return ["text" => ""];
        }

        return ["text" => $successText, "chat" => ["id" => $userId], "reply_markup" => $menu];
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
    public function comprobantOffer($bot, $code)
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
        $relayerKey = decryptValue(env('TRADER_BOT_KEY'));
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
        $relayerKey = decryptValue(env('TRADER_BOT_KEY'));
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
        $relayerKey = decryptValue(env('TRADER_BOT_KEY'));
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

    // =========================================================
    // PROOF WIZARD — Asistente de envio de comprobante de pago
    // =========================================================

    public function startProofWizard($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer || !in_array(strtoupper($offer->status), ['LOCKED', 'SIGNED'])) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"));
            return ["text" => ""];
        }

        $this->updateStatus($bot, "⏳ " . Lang::get("zentrotraderbot::bot.proof_wizard.wizard_started"));

        $bot->message['text'] = null;
        return $this->runProofWizard($bot, ['offer_code' => $code, 'images' => []]);
    }

    public function proofWizard($bot)
    {
        return $this->runProofWizard($bot);
    }

    private function runProofWizard($bot, array $initialData = [])
    {
        $self = $this;
        return (new WizardController())->run($bot, [
            ['name' => 'COLLECTING', 'handler' => fn($b, $s) => $self->stepProofCollecting($b, $s)],
        ], [
            'controller'  => self::class,
            'method'      => 'proofWizard',
            'initialData' => $initialData,
            'onComplete'  => fn($b, $s) => $self->completeProof($b, $s),
            'onCancel'    => fn($b) => [
                "text"         => "❌ " . Lang::get("zentrotraderbot::bot.proof_wizard.cancelled"),
                "chat"         => ["id" => $b->actor->user_id],
                "editprevious" => (isset($b->callback_query) || ($b->is_callback ?? false)) ? 1 : 0,
                "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
            ],
        ]);
    }

    private function stepProofCollecting($bot, array $state): array
    {
        $text      = $bot->message['text'] ?? '';
        $userId    = $bot->actor->user_id;
        $code      = $state['data']['offer_code'];
        $cancelBtn = [["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]];

        $msg         = request('message') ?? [];
        $hasPhoto    = isset($msg['photo']);
        $isImageDoc  = isset($msg['document']) && str_starts_with($msg['document']['mime_type'] ?? '', 'image/');
        $isValidImage = $hasPhoto || $isImageDoc;
        $isOtherMedia = !$isValidImage && (
            isset($msg['document']) || isset($msg['video']) || isset($msg['audio']) ||
            isset($msg['voice'])    || isset($msg['sticker']) || isset($msg['animation']) ||
            isset($msg['video_note']) || isset($msg['contact']) || isset($msg['location']) ||
            isset($msg['poll'])     || isset($msg['dice'])
        );

        // ── Imagen valida ────────────────────────────────────────────────────────
        if ($isValidImage) {
            $fileId = $hasPhoto ? end($msg['photo'])['file_id'] : $msg['document']['file_id'];
            $images = $state['data']['images'];
            $images[] = $fileId;

            $offer = Offers::findByCode($code);
            if ($offer) {
                $data = $offer->data ?? [];
                $data['proofs'][$userId][] = $fileId;
                $offer->update(['data' => $data]);
            }

            // Deduplicacion de album
            $mediaGroupId = $msg['media_group_id'] ?? null;
            if ($mediaGroupId) {
                $groupKey = "wizard_group_{$bot->tenant->key}_{$userId}_{$mediaGroupId}";
                if (Cache::has($groupKey)) {
                    return ['__update' => true, 'merge' => ['images' => $images], 'response' => ["text" => ""]];
                }
                Cache::put($groupKey, 1, now()->addSeconds(5));
            }

            return [
                '__update' => true,
                'merge'    => ['images' => $images],
                'response' => [
                    "text"         => "✅ " . Lang::get("zentrotraderbot::bot.proof_wizard.image_received", ['count' => count($images)]) . "\n\n❓ " . Lang::get("zentrotraderbot::bot.proof_wizard.ask_more"),
                    "chat"         => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[
                        ["text" => Lang::get("zentrotraderbot::bot.proof_wizard.yes_more"), "callback_data" => "proofmore {$code}"],
                        ["text" => Lang::get("zentrotraderbot::bot.proof_wizard.no_done"),  "callback_data" => "proofdone {$code}"],
                    ]]]),
                ],
            ];
        }

        // ── Contenido invalido ────────────────────────────────────────────────────
        if ($isOtherMedia) {
            return [
                "text"         => "⚠️ " . Lang::get("zentrotraderbot::bot.proof_wizard.invalid_content") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
                "chat"         => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── "No, eso es todo" ─────────────────────────────────────────────────────
        if (str_contains(strtolower($text), 'proofdone')) {
            if (empty($state['data']['images'])) {
                return [
                    "text"         => "⚠️ " . Lang::get("zentrotraderbot::bot.proof_wizard.no_images") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
                    "chat"         => ["id" => $userId],
                    "editprevious" => 1,
                    "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
                ];
            }
            return ['__advance' => true]; // → onComplete
        }

        // ── Texto inesperado ──────────────────────────────────────────────────────
        $isKnown = empty($text) || str_contains(strtolower($text), 'proofmore') || str_contains(strtolower($text), 'proofdone');
        if (!empty($text) && !$isKnown) {
            return [
                "text"         => "⚠️ " . Lang::get("zentrotraderbot::bot.proof_wizard.invalid_content") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
                "chat"         => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── Prompt inicial o "Sí, enviar otra" ───────────────────────────────────
        return [
            "text"         => "📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
            "chat"         => ["id" => $userId],
            "editprevious" => str_contains(strtolower($text), 'proofmore') ? 1 : 0,
            "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
        ];
    }

    private function completeProof($bot, array $state): array
    {
        $code   = $state['data']['offer_code'];
        $images = $state['data']['images'] ?? [];

        $offer = Offers::findByCode($code);
        if ($offer) {
            $botTenant = app('active_bot');
            $seller    = Suscriptions::findByAddress($offer->seller_address);
            if ($seller && $seller->user_id) {
                if (count($images) === 1) {
                    TelegramController::sendPhoto(["message" => ["photo" => $images[0], "text" => "", "chat" => ["id" => $seller->user_id]]], $botTenant->token);
                } else {
                    TelegramController::sendMediaGroup(["message" => ["chat" => ["id" => $seller->user_id], "media" => array_map(fn($fid) => ["type" => "photo", "media" => $fid], $images)]], $botTenant->token);
                }
                TelegramController::sendMessage(["message" => [
                    "text" => "📩 *" . Lang::get("zentrotraderbot::bot.proof_wizard.seller_notification_title") . "*\n🆔 `{$offer->code}`\n\n" . Lang::get("zentrotraderbot::bot.proof_wizard.seller_notification_body") . "\n\n🚨 *" . Lang::get("zentrotraderbot::bot.proof_wizard.seller_notification_warning") . "*",
                    "chat" => ["id" => $seller->user_id],
                ]], $botTenant->token);
            }
        }

        $this->comprobantOffer($bot, $code);
        return ["text" => ""];
    }

    // =========================================================
    // EVIDENCE WIZARD — Asistente de envio de evidencias en disputa
    // =========================================================

    public function startEvidenceWizard($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"));
            return ["text" => ""];
        }

        $bot->message['text'] = null;
        return $this->runEvidenceWizard($bot, ['offer_code' => $code, 'images' => []]);
    }

    public function evidenceWizard($bot)
    {
        return $this->runEvidenceWizard($bot);
    }

    private function runEvidenceWizard($bot, array $initialData = [])
    {
        $self = $this;
        return (new WizardController())->run($bot, [
            ['name' => 'COLLECTING', 'handler' => fn($b, $s) => $self->stepEvidenceCollecting($b, $s)],
        ], [
            'controller'  => self::class,
            'method'      => 'evidenceWizard',
            'initialData' => $initialData,
            'onComplete'  => fn($b, $s) => $self->completeEvidence($b, $s),
            'onCancel'    => fn($b) => [
                "text"         => "❌ " . Lang::get("zentrotraderbot::bot.evidence_wizard.cancelled"),
                "chat"         => ["id" => $b->actor->user_id],
                "editprevious" => (isset($b->callback_query) || ($b->is_callback ?? false)) ? 1 : 0,
                "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
            ],
        ]);
    }

    private function stepEvidenceCollecting($bot, array $state): array
    {
        $text      = $bot->message['text'] ?? '';
        $userId    = $bot->actor->user_id;
        $code      = $state['data']['offer_code'];
        $cancelBtn = [["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]];

        $msg          = request('message') ?? [];
        $hasPhoto     = isset($msg['photo']);
        $isImageDoc   = isset($msg['document']) && str_starts_with($msg['document']['mime_type'] ?? '', 'image/');
        $isValidImage = $hasPhoto || $isImageDoc;
        $isOtherMedia = !$isValidImage && (
            isset($msg['document']) || isset($msg['video']) || isset($msg['audio']) ||
            isset($msg['voice'])    || isset($msg['sticker']) || isset($msg['animation']) ||
            isset($msg['video_note']) || isset($msg['contact']) || isset($msg['location']) ||
            isset($msg['poll'])     || isset($msg['dice'])
        );

        // ── Imagen valida ────────────────────────────────────────────────────────
        if ($isValidImage) {
            $fileId = $hasPhoto ? end($msg['photo'])['file_id'] : $msg['document']['file_id'];
            $images = $state['data']['images'];
            $images[] = $fileId;

            $offer = Offers::findByCode($code);
            if ($offer) {
                $data = $offer->data ?? [];
                $data['evidence'][(string) $userId][] = $fileId;
                $offer->update(['data' => $data]);
            }

            // Deduplicacion de album
            $mediaGroupId = $msg['media_group_id'] ?? null;
            if ($mediaGroupId) {
                $groupKey = "wizard_group_{$bot->tenant->key}_{$userId}_{$mediaGroupId}";
                if (Cache::has($groupKey)) {
                    return ['__update' => true, 'merge' => ['images' => $images], 'response' => ["text" => ""]];
                }
                Cache::put($groupKey, 1, now()->addSeconds(5));
            }

            return [
                '__update' => true,
                'merge'    => ['images' => $images],
                'response' => [
                    "text"         => "✅ " . Lang::get("zentrotraderbot::bot.evidence_wizard.image_received", ['count' => count($images)]) . "\n\n❓ " . Lang::get("zentrotraderbot::bot.evidence_wizard.ask_more"),
                    "chat"         => ["id" => $userId],
                    "reply_markup" => json_encode(["inline_keyboard" => [[
                        ["text" => Lang::get("zentrotraderbot::bot.evidence_wizard.yes_more"), "callback_data" => "evimore {$code}"],
                        ["text" => Lang::get("zentrotraderbot::bot.evidence_wizard.no_done"),  "callback_data" => "evidone {$code}"],
                    ]]]),
                ],
            ];
        }

        // ── Contenido invalido ────────────────────────────────────────────────────
        if ($isOtherMedia) {
            return [
                "text"         => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.invalid_content") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                "chat"         => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── "No, eso es todo" ─────────────────────────────────────────────────────
        if (str_contains(strtolower($text), 'evidone')) {
            if (empty($state['data']['images'])) {
                return [
                    "text"         => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.no_images") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                    "chat"         => ["id" => $userId],
                    "editprevious" => 1,
                    "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
                ];
            }
            return ['__advance' => true]; // → onComplete
        }

        // ── Texto inesperado ──────────────────────────────────────────────────────
        $isKnown = empty($text) || str_contains(strtolower($text), 'evimore') || str_contains(strtolower($text), 'evidone');
        if (!empty($text) && !$isKnown) {
            return [
                "text"         => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.invalid_content") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                "chat"         => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── Prompt inicial o "Sí, enviar otra" ───────────────────────────────────
        $offer = Offers::findByCode($code);
        return [
            "text"         => "🧾 *" . Lang::get("zentrotraderbot::bot.evidence_wizard.title") . "*\n"
                . ($offer ? "🆔 `{$offer->code}`\n\n" : "")
                . "📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
            "chat"         => ["id" => $userId],
            "editprevious" => 1,
            "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
        ];
    }

    private function completeEvidence($bot, array $state): array
    {
        $code   = $state['data']['offer_code'];
        $userId = $bot->actor->user_id;

        $offer = Offers::findByCode($code);
        if ($offer) {
            $botTenant      = app('active_bot');
            $actorsController = new ActorsController();
            $admins         = $actorsController->getData(Actors::class, [
                ["contain" => true, "name" => "admin_level", "value" => [1, "1"]],
            ], $botTenant->code);

            $evidenceByUser = $offer->data['evidence'] ?? [];

            foreach ($admins as $admin) {
                TelegramController::sendMessage(["message" => [
                    "text" => "⚖️ *" . Lang::get("zentrotraderbot::bot.evidence_wizard.arbiter_notification_title") . "*\n🆔 `{$offer->code}`\n\n" . Lang::get("zentrotraderbot::bot.evidence_wizard.arbiter_notification_body"),
                    "chat" => ["id" => $admin->user_id],
                ]], $botTenant->token);

                foreach ($evidenceByUser as $senderId => $fileIds) {
                    TelegramController::sendMessage(["message" => ["text" => "👤 ID: `{$senderId}`", "chat" => ["id" => $admin->user_id]]], $botTenant->token);
                    if (count($fileIds) === 1) {
                        TelegramController::sendPhoto(["message" => ["photo" => $fileIds[0], "text" => "", "chat" => ["id" => $admin->user_id]]], $botTenant->token);
                    } else {
                        TelegramController::sendMediaGroup(["message" => ["chat" => ["id" => $admin->user_id], "media" => array_map(fn($fid) => ["type" => "photo", "media" => $fid], $fileIds)]], $botTenant->token);
                    }
                }
            }
        }

        return [
            "text"         => "✅ " . Lang::get("zentrotraderbot::bot.evidence_wizard.arbiter_notified"),
            "chat"         => ["id" => $userId],
            "editprevious" => 1,
            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
        ];
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