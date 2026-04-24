<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
use Modules\ZentroTraderBot\Jobs\ProcessProofSigning;

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
                            ["text" => "⏱️ " . Lang::get("zentrotraderbot::bot.recover_offer.ready_button"), "callback_data" => "/recoveroffer {$offer->code}"]
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
        // 1. Lock de 5 min para cubrir confirmación blockchain (hasta 1-3 min) + margen
        $lockKey = "applying_offer_lock_{$code}";

        if (!Cache::add($lockKey, $bot->actor->user_id, now()->addMinutes(5))) {
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

            $this->logOfferAction($offer, 'seller', 'offer_recovered', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $offer->code, 'tx_hash' => $txHash]);
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
            ["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.backtop2pmenu"), "callback_data" => "/p2pmenu"],
        ]);
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
                    "chat" => ["id" => $bot->actor->user_id],
                    "message_id" => $bot->message["message_id"],
                    "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
                ],
            ];
            try {
                TelegramController::editMessageText($payload, $bot->tenant->token);
            } catch (\Throwable $th) {
            }
            return;
        }

        $suscriptor = Suscriptions::findByAddress($offer->seller_address);
        if ($suscriptor && $suscriptor->user_id == $bot->actor->user_id) {
            $suscriptor = Suscriptions::findByAddress($offer->buyer_address);
        }
        if (!$suscriptor)
            return;

        $bot->message['text'] = null;
        return $this->runRatingWizard($bot, [
            'offer_code' => $code,
            'stars' => $stars,
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
            'controller' => self::class,
            'method' => 'ratingWizard',
            'initialData' => $initialData,
            'onComplete' => fn($b, $s) => $self->completeRating($b, $s),
            'onCancel' => fn($b) => [
                "text" => "❌ " . Lang::get("zentrotraderbot::bot.rate_offer.cancelled"),
                "chat" => ["id" => $b->actor->user_id],
                "editprevious" => (isset($b->callback_query) || ($b->is_callback ?? false)) ? 1 : 0,
                "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
            ],
        ]);
    }

    private function stepRatingComment($bot, array $state): array
    {
        $text = $bot->message['text'] ?? '';
        $userId = $bot->actor->user_id;
        $code = $state['data']['offer_code'];
        $stars = $state['data']['stars'];

        if ($text !== '') {
            $comment = str_contains(strtolower($text), 'ratingskip') ? null : (trim($text) ?: null);
            return ['__advance' => true, 'merge' => ['comment' => $comment]];
        }

        return [
            "text" => "⭐ " . $stars . "/5\n\n💬 " . Lang::get("zentrotraderbot::bot.rate_offer.comment_prompt"),
            "chat" => ["id" => $userId],
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "🙅 " . Lang::get("zentrotraderbot::bot.rate_offer.comment_skip"), "callback_data" => "ratingskip {$code}"],
                    ]
                ]
            ]),
        ];
    }

    private function completeRating($bot, array $state): array
    {
        $userId = $bot->actor->user_id;
        $ratedUserId = $state['data']['rated_user_id'] ?? null;
        $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);

        if ($ratedUserId) {
            try {
                $offer = Offers::findByCode($state['data']['offer_code']);
                OffersRatings::create([
                    'offer_id' => $offer ? $offer->id : null,
                    'rater_user_id' => $userId,
                    'rated_user_id' => $ratedUserId,
                    'stars' => $state['data']['stars'],
                    'comment' => $state['data']['comment'] ?? null,
                ]);
                ProcessReputationUpdate::dispatch($ratedUserId, $state['data']['stars'], $bot->tenant->key);
            } catch (\Throwable $th) {
            }
        }

        $successText = "✅ *" . Lang::get("zentrotraderbot::bot.rate_offer.success_title") . "*\n"
            . "🙏 " . Lang::get("zentrotraderbot::bot.rate_offer.thanks") . "\n\n"
            . "👇 " . Lang::get("telegrambot::bot.prompts.whatsnext");
        $menu = json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]);

        if ($isCallback) {
            $payload = ["message" => ["text" => $successText, "chat" => ["id" => $userId], "message_id" => $bot->message["message_id"], "reply_markup" => $menu]];
            try {
                TelegramController::editMessageText($payload, $bot->tenant->token);
            } catch (\Throwable $th) {
            }
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

            $this->updateStatus($bot, "✅ " . Lang::get("zentrotraderbot::bot.sign_offer.proof_sent"));
            return $txHash;

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ID already exists')) {
                $this->updateStatus($bot, "✅ " . Lang::get("zentrotraderbot::bot.sign_offer.proof_sent"));
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

            $this->logOfferAction($offer, 'seller', 'payment_confirmed', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $offer->code, 'tx_hash' => $txHash]);
            $this->updateStatus($bot, "✅ *" . Lang::get("zentrotraderbot::bot.sign_offer.confirmation_sent") . "*");
            return $txHash;

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ID already exists')) {
                $this->logOfferAction($offer, 'seller', 'payment_confirmed', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $offer->code, 'note' => 'already_exists']);
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

            $this->logOfferAction($offer, 'buyer', 'trade_cancelled', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $offer->code, 'tx_hash' => $txHash]);
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
            'controller' => self::class,
            'method' => 'proofWizard',
            'initialData' => $initialData,
            'onComplete' => fn($b, $s) => $self->completeProof($b, $s),
            'onCancel' => fn($b) => [
                "text" => "❌ " . Lang::get("zentrotraderbot::bot.proof_wizard.cancelled"),
                "chat" => ["id" => $b->actor->user_id],
                "editprevious" => (isset($b->callback_query) || ($b->is_callback ?? false)) ? 1 : 0,
                "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
            ],
        ]);
    }

    private function stepProofCollecting($bot, array $state): array
    {
        $text = $bot->message['text'] ?? '';
        $userId = $bot->actor->user_id;
        $code = $state['data']['offer_code'];
        $cancelBtn = [["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]];

        $msg = request('message') ?? [];
        $hasPhoto = isset($msg['photo']);
        $isImageDoc = isset($msg['document']) && str_starts_with($msg['document']['mime_type'] ?? '', 'image/');
        $isValidImage = $hasPhoto || $isImageDoc;
        $isOtherMedia = !$isValidImage && (
            isset($msg['document']) || isset($msg['video']) || isset($msg['audio']) ||
            isset($msg['voice']) || isset($msg['sticker']) || isset($msg['animation']) ||
            isset($msg['video_note']) || isset($msg['contact']) || isset($msg['location']) ||
            isset($msg['poll']) || isset($msg['dice'])
        );

        // ── Imagen valida ────────────────────────────────────────────────────────
        if ($isValidImage) {
            $fileId = $hasPhoto ? end($msg['photo'])['file_id'] : $msg['document']['file_id'];

            // Atomic DB append con lock para evitar race condition entre webhooks simultaneos de album
            $currentImages = [];
            $mediaGroupId = $msg['media_group_id'] ?? null;
            $offer = Offers::findByCode($code);
            if ($offer) {
                DB::transaction(function () use ($offer, $userId, $fileId, &$currentImages) {
                    $locked = Offers::lockForUpdate()->find($offer->id);
                    $data = $locked->data ?? [];
                    $proofs = $data['proofs'][$userId] ?? [];
                    if (!in_array($fileId, $proofs)) {
                        $proofs[] = $fileId;
                    }
                    $data['proofs'][$userId] = $proofs;
                    $locked->update(['data' => $data]);
                    $currentImages = $proofs;
                });
            } else {
                $currentImages = array_unique(array_merge($state['data']['images'] ?? [], [$fileId]));
            }

            $msgText = "✅ " . Lang::get("zentrotraderbot::bot.proof_wizard.image_received", ['count' => count($currentImages)])
                . "\n🤔 " . Lang::get("zentrotraderbot::bot.proof_wizard.ask_more");
            $buttons = json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => Lang::get("zentrotraderbot::bot.proof_wizard.yes_more"), "callback_data" => "proofmore {$code}"],
                        ["text" => Lang::get("zentrotraderbot::bot.proof_wizard.no_done"), "callback_data" => "proofdone {$code}"],
                    ]
                ],
            ]);

            if ($mediaGroupId) {
                // Cache::add es atomico (Redis SETNX): solo el primer webhook del album lo adquiere
                $albumKey = "album_msg_{$bot->tenant->key}_{$userId}_{$mediaGroupId}";
                $isFirst = Cache::add($albumKey, 'pending', now()->addSeconds(10));

                if ($isFirst) {
                    // Primera foto: envia el mensaje y guarda el message_id para que los demas editen
                    $resp = TelegramController::sendMessage([
                        "message" => ["text" => $msgText, "chat" => ["id" => $userId], "reply_markup" => $buttons],
                    ], $bot->tenant->token);
                    $messageId = json_decode($resp, true)['result']['message_id'] ?? null;
                    if ($messageId) {
                        Cache::put($albumKey, $messageId, now()->addSeconds(10));
                    }
                } else {
                    // Fotos siguientes: esperan el message_id y editan el mismo mensaje
                    $messageId = null;
                    for ($i = 0; $i < 10; $i++) {
                        $cached = Cache::get($albumKey);
                        if ($cached && $cached !== 'pending') {
                            $messageId = (int) $cached;
                            break;
                        }
                        usleep(50000); // 50 ms
                    }
                    if ($messageId) {
                        TelegramController::editMessageText([
                            "message" => ["chat" => ["id" => $userId], "message_id" => $messageId, "text" => $msgText, "reply_markup" => $buttons],
                        ], $bot->tenant->token);
                    }
                }

                return [
                    '__update' => true,
                    'merge' => ['images' => $currentImages],
                    'response' => ['text' => '', 'chat' => ['id' => $userId]],
                ];
            }

            return [
                '__update' => true,
                'merge' => ['images' => $currentImages],
                'response' => ["text" => $msgText, "chat" => ["id" => $userId], "reply_markup" => $buttons],
            ];
        }

        // ── Contenido invalido ────────────────────────────────────────────────────
        if ($isOtherMedia) {
            return [
                "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.proof_wizard.invalid_content") . "\n📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── "No, eso es todo" ─────────────────────────────────────────────────────
        if (str_contains(strtolower($text), 'proofdone')) {
            if (empty($state['data']['images'])) {
                return [
                    "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.proof_wizard.no_images") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
                    "chat" => ["id" => $userId],
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
                "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.proof_wizard.invalid_content") . "\n📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── Prompt inicial o "Sí, enviar otra" ───────────────────────────────────
        return [
            "text" => "📸 " . Lang::get("zentrotraderbot::bot.proof_wizard.instructions"),
            "chat" => ["id" => $userId],
            "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
        ];
    }

    private function completeProof($bot, array $state): array
    {
        $code = $state['data']['offer_code'];
        $images = $state['data']['images'] ?? [];

        $offer = Offers::findByCode($code);
        if ($offer) {
            // Las imágenes y el botón de confirmar al vendedor se envían desde
            // ProcessContractActivity::sendPendingNotification cuando Moralis detecta
            // la TX en mempool (TRADESIGNED unconfirmed). Nada que hacer aquí.
            $this->logOfferAction($offer, 'buyer', 'proof_submitted', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $code, 'image_count' => count($images)]);
        }

        ProcessProofSigning::dispatch(
            $bot->tenant->key,
            $code,
            $bot->actor->user_id
        );

        return [
            "text" => "✅ *" . Lang::get("zentrotraderbot::bot.sign_offer.proof_sent") . "*",
            "chat" => ["id" => $bot->actor->user_id],
        ];
    }

    // =========================================================
    // EVIDENCE WIZARD — Asistente de envio de evidencias en disputa
    // =========================================================

    /**
     * Inicializa el estado del wizard de evidencias en cache para un usuario.
     * Permite arrancarlo directamente sin que el usuario toque un botón.
     */
    public function seedEvidenceWizard(string $tenantKey, int $userId, string $offerCode): void
    {
        $cacheKey = "wizard_{$tenantKey}_{$userId}";
        Cache::forever($cacheKey, [
            'controller' => self::class,
            'method' => 'evidenceWizard',
            'step' => 'COLLECTING',
            'data' => ['offer_code' => $offerCode, 'images' => []],
            'history' => [],
        ]);
    }

    /**
     * Construye el texto del prompt inicial del wizard segun el contexto.
     * $context: 'dispute' (oferta entra en DISPUTED) | 'more' (árbitro pide más evidencias)
     */
    public function buildEvidenceWizardPromptText(Offers $offer, string $context = 'dispute'): string
    {
        $contextLine = $context === 'more'
            ? "🔔 _" . Lang::get("zentrotraderbot::bot.evidence_wizard.more_requested_body") . "_"
            : "⚖️ _" . Lang::get("zentrotraderbot::bot.evidence_wizard.arbiter_dispute_context") . "_";

        return "🧾 *" . Lang::get("zentrotraderbot::bot.evidence_wizard.title") . "*\n"
            . "🆔 `{$offer->code}`\n\n"
            . "{$contextLine}\n\n"
            . "📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions");
    }

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
            'controller' => self::class,
            'method' => 'evidenceWizard',
            'initialData' => $initialData,
            'onComplete' => fn($b, $s) => $self->completeEvidence($b, $s),
            'onCancel' => fn($b) => [
                "text" => "❌ " . Lang::get("zentrotraderbot::bot.evidence_wizard.cancelled"),
                "chat" => ["id" => $b->actor->user_id],
                "editprevious" => (isset($b->callback_query) || ($b->is_callback ?? false)) ? 1 : 0,
                "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
            ],
        ]);
    }

    private function stepEvidenceCollecting($bot, array $state): array
    {
        $text = $bot->message['text'] ?? '';
        $userId = $bot->actor->user_id;
        $code = $state['data']['offer_code'];
        $cancelBtn = [["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]];

        $msg = request('message') ?? [];
        $hasPhoto = isset($msg['photo']);
        $isImageDoc = isset($msg['document']) && str_starts_with($msg['document']['mime_type'] ?? '', 'image/');
        $isValidImage = $hasPhoto || $isImageDoc;
        $isOtherMedia = !$isValidImage && (
            isset($msg['document']) || isset($msg['video']) || isset($msg['audio']) ||
            isset($msg['voice']) || isset($msg['sticker']) || isset($msg['animation']) ||
            isset($msg['video_note']) || isset($msg['contact']) || isset($msg['location']) ||
            isset($msg['poll']) || isset($msg['dice'])
        );

        // ── Imagen valida ────────────────────────────────────────────────────────
        if ($isValidImage) {
            $fileId = $hasPhoto ? end($msg['photo'])['file_id'] : $msg['document']['file_id'];

            // Atomic DB append con lock
            $currentImages = [];
            $mediaGroupId = $msg['media_group_id'] ?? null;
            $offer = Offers::findByCode($code);
            if ($offer) {
                DB::transaction(function () use ($offer, $userId, $fileId, &$currentImages) {
                    $locked = Offers::lockForUpdate()->find($offer->id);
                    $data = $locked->data ?? [];
                    $proofs = $data['evidence'][(string) $userId] ?? [];
                    if (!in_array($fileId, $proofs)) {
                        $proofs[] = $fileId;
                    }
                    $data['evidence'][(string) $userId] = $proofs;
                    $locked->update(['data' => $data]);
                    $currentImages = $proofs;
                });
            } else {
                $currentImages = array_unique(array_merge($state['data']['images'] ?? [], [$fileId]));
            }

            $msgText = "✅ " . Lang::get("zentrotraderbot::bot.evidence_wizard.image_received", ['count' => count($currentImages)])
                . "\n🤔 " . Lang::get("zentrotraderbot::bot.evidence_wizard.ask_more");
            $buttons = json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => Lang::get("zentrotraderbot::bot.evidence_wizard.yes_more"), "callback_data" => "evimore {$code}"],
                        ["text" => Lang::get("zentrotraderbot::bot.evidence_wizard.no_done"), "callback_data" => "evidone {$code}"],
                    ]
                ],
            ]);

            if ($mediaGroupId) {
                $albumKey = "album_msg_{$bot->tenant->key}_{$userId}_{$mediaGroupId}";
                $isFirst = Cache::add($albumKey, 'pending', now()->addSeconds(10));

                if ($isFirst) {
                    $resp = TelegramController::sendMessage([
                        "message" => ["text" => $msgText, "chat" => ["id" => $userId], "reply_markup" => $buttons],
                    ], $bot->tenant->token);
                    $messageId = json_decode($resp, true)['result']['message_id'] ?? null;
                    if ($messageId) {
                        Cache::put($albumKey, $messageId, now()->addSeconds(10));
                    }
                } else {
                    $messageId = null;
                    for ($i = 0; $i < 10; $i++) {
                        $cached = Cache::get($albumKey);
                        if ($cached && $cached !== 'pending') {
                            $messageId = (int) $cached;
                            break;
                        }
                        usleep(50000);
                    }
                    if ($messageId) {
                        TelegramController::editMessageText([
                            "message" => ["chat" => ["id" => $userId], "message_id" => $messageId, "text" => $msgText, "reply_markup" => $buttons],
                        ], $bot->tenant->token);
                    }
                }

                return [
                    '__update' => true,
                    'merge' => ['images' => $currentImages],
                    'response' => ['text' => '', 'chat' => ['id' => $userId]],
                ];
            }

            return [
                '__update' => true,
                'merge' => ['images' => $currentImages],
                'response' => ["text" => $msgText, "chat" => ["id" => $userId], "reply_markup" => $buttons],
            ];
        }

        // ── Contenido invalido ────────────────────────────────────────────────────
        if ($isOtherMedia) {
            return [
                "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.invalid_content") . "\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── "No, eso es todo" ─────────────────────────────────────────────────────
        if (str_contains(strtolower($text), 'evidone')) {
            if (empty($state['data']['images'])) {
                return [
                    "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.no_images") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                    "chat" => ["id" => $userId],
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
                "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.invalid_content") . "\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
            ];
        }

        // ── Prompt inicial o "Sí, enviar otra" ───────────────────────────────────
        $offer = Offers::findByCode($code);
        return [
            "text" => "🧾 *" . Lang::get("zentrotraderbot::bot.evidence_wizard.title") . "*\n"
                . ($offer ? "🆔 `{$offer->code}`\n\n" : "")
                . "📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
            "chat" => ["id" => $userId],
            "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn]]),
        ];
    }

    private function completeEvidence($bot, array $state): array
    {
        $code = $state['data']['offer_code'];
        $userId = $bot->actor->user_id;

        $offer = Offers::findByCode($code);
        if ($offer) {
            $buyerSub = Suscriptions::findByAddress($offer->buyer_address);
            $evRole = ($buyerSub && (string) $buyerSub->user_id === (string) $userId) ? 'buyer' : 'seller';
            $this->logOfferAction($offer, $evRole, 'evidence_submitted', $userId, $bot->message['text'] ?? '', ['code' => $code, 'image_count' => count($offer->data['evidence'][(string) $userId] ?? [])]);
            $botTenant = app('active_bot');
            $actorsController = new ActorsController();
            $admins = $actorsController->getData(Actors::class, [
                ["contain" => true, "name" => "admin_level", "value" => [1, "1"]],
            ], $botTenant->code);

            $evidenceByUser = $offer->data['evidence'] ?? [];

            // Notificar por DM a los admins (flujo anterior)
            foreach ($admins as $admin) {
                TelegramController::sendMessage([
                    "message" => [
                        "text" => "⚖️ *" . Lang::get("zentrotraderbot::bot.evidence_wizard.arbiter_notification_title") . "*\n🆔 `{$offer->code}`\n\n" . Lang::get("zentrotraderbot::bot.evidence_wizard.arbiter_notification_body"),
                        "chat" => ["id" => $admin->user_id],
                    ]
                ], $botTenant->token);

                foreach ($evidenceByUser as $senderId => $fileIds) {
                    TelegramController::sendMessage(["message" => ["text" => "👤 ID: `{$senderId}`", "chat" => ["id" => $admin->user_id]]], $botTenant->token);
                    if (count($fileIds) === 1) {
                        TelegramController::sendPhoto(["message" => ["photo" => $fileIds[0], "text" => "", "chat" => ["id" => $admin->user_id]]], $botTenant->token);
                    } else {
                        TelegramController::sendMediaGroup(["message" => ["chat" => ["id" => $admin->user_id], "media" => array_map(fn($fid) => ["type" => "photo", "media" => $fid], $fileIds)]], $botTenant->token);
                    }
                }
            }

            // Reenviar SOLO las evidencias nuevas al forum thread si existe
            $threadId = $offer->data['dispute']['thread_id'] ?? null;
            $supportId = env('TRADER_BOT_SUPPORT');
            if ($threadId && $supportId) {
                $userEvidence = $evidenceByUser[(string) $userId] ?? [];
                $alreadyFwdCount = $offer->data['dispute']['evidence_forwarded'][(string) $userId] ?? 0;
                $newFileIds = array_values(array_slice($userEvidence, $alreadyFwdCount));

                if (!empty($newFileIds)) {
                    $buyerSub = Suscriptions::findByAddress($offer->buyer_address);
                    $sellerSub = Suscriptions::findByAddress($offer->seller_address);
                    $buyerTgId = $buyerSub ? (string) $buyerSub->user_id : null;
                    $sellerTgId = $sellerSub ? (string) $sellerSub->user_id : null;

                    $role = match (true) {
                        $buyerTgId && (string) $userId === $buyerTgId => 'buyer',
                        $sellerTgId && (string) $userId === $sellerTgId => 'seller',
                        default => 'unknown',
                    };
                    $label = match ($role) {
                        'buyer' => "*" . Lang::get("zentrotraderbot::bot.offer.disputed.by_buyer") . "* 🟢",
                        'seller' => "*" . Lang::get("zentrotraderbot::bot.offer.disputed.by_seller") . "* 🔴",
                        default => "👤 ID: `{$userId}`",
                    };

                    $base = ['chat' => ['id' => $supportId], 'message_thread_id' => (int) $threadId, 'text' => ''];

                    if (count($newFileIds) === 1) {
                        TelegramController::sendPhoto(['message' => array_merge($base, ['photo' => $newFileIds[0]])], $botTenant->token);
                    } else {
                        $media = array_map(fn($fid) => ['type' => 'photo', 'media' => $fid], $newFileIds);
                        TelegramController::sendMediaGroup(['message' => array_merge($base, ['media' => $media])], $botTenant->token);
                    }

                    TelegramController::sendMessage([
                        'message' => array_merge($base, [
                            'text' => "👆 {$label}\n🆔 `{$offer->code}`",
                            'reply_markup' => self::arbiterButtons($offer->code, (string) $userId),
                        ]),
                    ], $botTenant->token);

                    // Actualizar contador de evidencias reenviadas
                    DB::transaction(function () use ($offer, $userId, $userEvidence) {
                        $locked = Offers::lockForUpdate()->find($offer->id);
                        $data = $locked->data ?? [];
                        $data['dispute']['evidence_forwarded'][(string) $userId] = count($userEvidence);
                        $locked->update(['data' => $data]);
                    });
                }
            }
        }

        return [
            "text" => "✅ " . Lang::get("zentrotraderbot::bot.evidence_wizard.arbiter_notified"),
            "chat" => ["id" => $userId],
            "editprevious" => 1,
            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
        ];
    }

    // =========================================================
    // REQUEST MORE EVIDENCE — El árbitro pide más evidencias
    // =========================================================

    public function requestMoreEvidence($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer || strtoupper($offer->status) !== 'DISPUTED') {
            return [
                "text" => "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"),
                "chat" => ["id" => $bot->actor->user_id],
            ];
        }

        $botTenant = app('active_bot');
        $this->logOfferAction($offer, 'arbiter', 'request_more_evidence', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $code]);
        $cancelMenu = [[["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]]];
        $wizardText = $this->buildEvidenceWizardPromptText($offer, 'more');

        foreach ([$offer->buyer_address, $offer->seller_address] as $address) {
            $sub = Suscriptions::findByAddress($address);
            if ($sub && $sub->user_id) {
                $this->seedEvidenceWizard($botTenant->key, (int) $sub->user_id, $offer->code);
                TelegramController::sendMessage([
                    "message" => [
                        "text" => $wizardText,
                        "chat" => ["id" => $sub->user_id],
                        "reply_markup" => json_encode(["inline_keyboard" => $cancelMenu]),
                    ],
                ], $botTenant->token);
            }
        }

        // Notificar en el thread de disputa
        $threadId = $offer->data['dispute']['thread_id'] ?? null;
        $supportId = env('TRADER_BOT_SUPPORT');
        if ($threadId && $supportId) {
            TelegramController::sendMessage([
                "message" => [
                    "text" => "🔔 *" . Lang::get("zentrotraderbot::bot.evidence_wizard.thread_more_requested") . "*",
                    "chat" => ["id" => $supportId],
                    "message_thread_id" => (int) $threadId,
                ],
            ], $botTenant->token);
        }

        return [
            "text" => "✅ " . Lang::get("zentrotraderbot::bot.evidence_wizard.more_requested_sent"),
            "chat" => ["id" => $bot->actor->user_id],
            "editprevious" => 1,
            "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
        ];
    }

    // =========================================================
    // PROOF RESUBMIT — Vendedor dice no haber recibido, comprador reenvía evidencias
    // =========================================================

    public function notReceivedPayment($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer || !in_array(strtoupper($offer->status), ['LOCKED', 'SIGNED'])) {
            $this->updateStatus($bot, "⚠️ " . Lang::get("zentrotraderbot::bot.sign_offer.wrong_state"));
            return ["text" => ""];
        }

        $callerSub = Suscriptions::on('tenant')->where('user_id', $bot->actor->user_id)->first();
        if (!$callerSub || strtolower($callerSub->data['wallet']['address'] ?? '') !== strtolower($offer->seller_address)) {
            return ["text" => ""];
        }

        $botTenant = app('active_bot');
        $this->logOfferAction($offer, 'seller', 'payment_rejected', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $offer->code]);
        $buyerSub = Suscriptions::findByAddress($offer->buyer_address);

        if ($buyerSub && $buyerSub->user_id) {
            $this->seedProofResubmitWizard($botTenant->key, (int) $buyerSub->user_id, $offer->code);

            $text = "🧾 *" . Lang::get("zentrotraderbot::bot.evidence_wizard.title") . "*\n"
                . "🆔 `{$offer->code}`\n\n"
                . "⚠️ _" . Lang::get("zentrotraderbot::bot.proof_resubmit.wizard_context") . "_\n\n"
                . "📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions");

            TelegramController::sendMessage([
                "message" => [
                    "text" => $text,
                    "chat" => ["id" => $buyerSub->user_id],
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]],
                            [["text" => "⚖️ " . Lang::get("zentrotraderbot::bot.options.open_dispute"), "callback_data" => "/disputebybuyer {$offer->code}"]],
                        ]
                    ]),
                ],
            ], $botTenant->token);
        }

        return [
            "text" => "✅ " . Lang::get("zentrotraderbot::bot.proof_resubmit.seller_notified"),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "👍 " . Lang::get("zentrotraderbot::bot.options.confirm_received"), "callback_data" => "/signoffer {$offer->code}"]],
                    [["text" => "⬅️ " . Lang::get("zentrotraderbot::bot.options.backtop2pmenu"), "callback_data" => "/p2pmenu"]],
                    [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]],
                ]
            ]),
        ];
    }

    public function seedProofResubmitWizard(string $tenantKey, int $userId, string $offerCode): void
    {
        $cacheKey = "wizard_{$tenantKey}_{$userId}";
        Cache::forever($cacheKey, [
            'controller' => self::class,
            'method' => 'proofResubmitWizard',
            'step' => 'COLLECTING',
            'data' => ['offer_code' => $offerCode, 'images' => []],
            'history' => [],
        ]);
    }

    public function startProofResubmitWizard($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer) {
            return ["text" => "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found")];
        }
        $bot->message['text'] = null;
        return $this->runProofResubmitWizard($bot, ['offer_code' => $code, 'images' => []]);
    }

    public function proofResubmitWizard($bot)
    {
        $text = $bot->message['text'] ?? '';
        if (str_starts_with(ltrim($text), '/disputebybuyer') || str_starts_with(ltrim($text), '/disputebyseller')) {
            $parts = explode(' ', trim($text));
            $code = $parts[1] ?? '';
            Cache::forget("wizard_{$bot->tenant->key}_{$bot->actor->user_id}");
            return $this->openDispute($bot, $code);
        }
        return $this->runProofResubmitWizard($bot);
    }

    private function runProofResubmitWizard($bot, array $initialData = [])
    {
        $self = $this;
        return (new WizardController())->run($bot, [
            ['name' => 'COLLECTING', 'handler' => fn($b, $s) => $self->stepProofResubmitCollecting($b, $s)],
        ], [
            'controller' => self::class,
            'method' => 'proofResubmitWizard',
            'initialData' => $initialData,
            'onComplete' => fn($b, $s) => $self->completeProofResubmit($b, $s),
            'onCancel' => fn($b) => [
                "text" => "❌ " . Lang::get("zentrotraderbot::bot.proof_resubmit.cancelled"),
                "chat" => ["id" => $b->actor->user_id],
                "editprevious" => (isset($b->callback_query) || ($b->is_callback ?? false)) ? 1 : 0,
                "reply_markup" => json_encode(["inline_keyboard" => [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]]]),
            ],
        ]);
    }

    private function stepProofResubmitCollecting($bot, array $state): array
    {
        $text = $bot->message['text'] ?? '';
        $userId = $bot->actor->user_id;
        $code = $state['data']['offer_code'];
        $cancelBtn = [["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/wizardcancel"]];
        $disputeBtn = [["text" => "⚖️ " . Lang::get("zentrotraderbot::bot.options.open_dispute"), "callback_data" => "/disputebybuyer {$code}"]];

        $msg = request('message') ?? [];
        $hasPhoto = isset($msg['photo']);
        $isImageDoc = isset($msg['document']) && str_starts_with($msg['document']['mime_type'] ?? '', 'image/');
        $isValidImage = $hasPhoto || $isImageDoc;
        $isOtherMedia = !$isValidImage && (
            isset($msg['document']) || isset($msg['video']) || isset($msg['audio']) ||
            isset($msg['voice']) || isset($msg['sticker']) || isset($msg['animation']) ||
            isset($msg['video_note']) || isset($msg['contact']) || isset($msg['location']) ||
            isset($msg['poll']) || isset($msg['dice'])
        );

        // ── Imagen valida ────────────────────────────────────────────────────────
        if ($isValidImage) {
            $fileId = $hasPhoto ? end($msg['photo'])['file_id'] : $msg['document']['file_id'];
            $currentImages = [];
            $mediaGroupId = $msg['media_group_id'] ?? null;
            $offer = Offers::findByCode($code);
            if ($offer) {
                DB::transaction(function () use ($offer, $userId, $fileId, &$currentImages) {
                    $locked = Offers::lockForUpdate()->find($offer->id);
                    $data = $locked->data ?? [];
                    $proofs = $data['evidence'][(string) $userId] ?? [];
                    if (!in_array($fileId, $proofs)) {
                        $proofs[] = $fileId;
                    }
                    $data['evidence'][(string) $userId] = $proofs;
                    $locked->update(['data' => $data]);
                    $currentImages = $proofs;
                });
            } else {
                $currentImages = array_unique(array_merge($state['data']['images'] ?? [], [$fileId]));
            }

            $msgText = "✅ " . Lang::get("zentrotraderbot::bot.evidence_wizard.image_received", ['count' => count($currentImages)])
                . "\n🤔 " . Lang::get("zentrotraderbot::bot.evidence_wizard.ask_more");
            $buttons = json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => Lang::get("zentrotraderbot::bot.evidence_wizard.yes_more"), "callback_data" => "proofresmore {$code}"],
                        ["text" => Lang::get("zentrotraderbot::bot.evidence_wizard.no_done"), "callback_data" => "proofresdone {$code}"],
                    ]
                ],
            ]);

            if ($mediaGroupId) {
                $albumKey = "album_msg_{$bot->tenant->key}_{$userId}_{$mediaGroupId}";
                $isFirst = Cache::add($albumKey, 'pending', now()->addSeconds(10));
                if ($isFirst) {
                    $resp = TelegramController::sendMessage([
                        "message" => ["text" => $msgText, "chat" => ["id" => $userId], "reply_markup" => $buttons],
                    ], $bot->tenant->token);
                    $messageId = json_decode($resp, true)['result']['message_id'] ?? null;
                    if ($messageId)
                        Cache::put($albumKey, $messageId, now()->addSeconds(10));
                } else {
                    $messageId = null;
                    for ($i = 0; $i < 10; $i++) {
                        $cached = Cache::get($albumKey);
                        if ($cached && $cached !== 'pending') {
                            $messageId = (int) $cached;
                            break;
                        }
                        usleep(50000);
                    }
                    if ($messageId) {
                        TelegramController::editMessageText([
                            "message" => ["chat" => ["id" => $userId], "message_id" => $messageId, "text" => $msgText, "reply_markup" => $buttons],
                        ], $bot->tenant->token);
                    }
                }
                return ['__update' => true, 'merge' => ['images' => $currentImages], 'response' => ['text' => '', 'chat' => ['id' => $userId]]];
            }

            return ['__update' => true, 'merge' => ['images' => $currentImages], 'response' => ["text" => $msgText, "chat" => ["id" => $userId], "reply_markup" => $buttons]];
        }

        // ── Contenido invalido ────────────────────────────────────────────────────
        if ($isOtherMedia) {
            return [
                "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.invalid_content") . "\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn, $disputeBtn]]),
            ];
        }

        // ── "No, eso es todo" ─────────────────────────────────────────────────────
        if (str_contains(strtolower($text), 'proofresdone')) {
            $offer = Offers::findByCode($code);
            $currentImages = $offer?->data['evidence'][(string) $userId] ?? ($state['data']['images'] ?? []);
            if (empty($currentImages)) {
                return [
                    "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.no_images") . "\n\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                    "chat" => ["id" => $userId],
                    "editprevious" => 1,
                    "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn, $disputeBtn]]),
                ];
            }
            return ['__advance' => true];
        }

        // ── Texto inesperado ──────────────────────────────────────────────────────
        $isKnown = empty($text) || str_contains(strtolower($text), 'proofresmore') || str_contains(strtolower($text), 'proofresdone');
        if (!empty($text) && !$isKnown) {
            return [
                "text" => "⚠️ " . Lang::get("zentrotraderbot::bot.evidence_wizard.invalid_content") . "\n📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
                "chat" => ["id" => $userId],
                "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn, $disputeBtn]]),
            ];
        }

        // ── Prompt inicial o "Sí, enviar otra" ───────────────────────────────────
        $offer = Offers::findByCode($code);
        return [
            "text" => "🧾 *" . Lang::get("zentrotraderbot::bot.evidence_wizard.title") . "*\n"
                . ($offer ? "🆔 `{$offer->code}`\n\n" : "")
                . "⚠️ _" . Lang::get("zentrotraderbot::bot.proof_resubmit.wizard_context") . "_\n\n"
                . "📸 " . Lang::get("zentrotraderbot::bot.evidence_wizard.instructions"),
            "chat" => ["id" => $userId],
            "reply_markup" => json_encode(["inline_keyboard" => [$cancelBtn, $disputeBtn]]),
        ];
    }

    private function completeProofResubmit($bot, array $state): array
    {
        $code = $state['data']['offer_code'];
        $userId = $bot->actor->user_id;
        $botTenant = app('active_bot');

        $offer = Offers::findByCode($code);
        if (!$offer) {
            return ["text" => "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"), "chat" => ["id" => $userId]];
        }

        $this->logOfferAction($offer, 'buyer', 'proof_resubmitted', $userId, $bot->message['text'] ?? '', ['code' => $code, 'image_count' => count($offer->data['evidence'][(string) $userId] ?? [])]);
        $sellerSub = Suscriptions::findByAddress($offer->seller_address);
        if ($sellerSub && $sellerSub->user_id) {
            $newImages = $offer->data['evidence'][(string) $userId] ?? [];
            if (!empty($newImages)) {
                if (count($newImages) === 1) {
                    TelegramController::sendPhoto(["message" => ["photo" => $newImages[0], "text" => "", "chat" => ["id" => $sellerSub->user_id]]], $botTenant->token);
                } else {
                    TelegramController::sendMediaGroup(["message" => ["chat" => ["id" => $sellerSub->user_id], "media" => array_map(fn($fid) => ["type" => "photo", "media" => $fid], $newImages)]], $botTenant->token);
                }
            }

            $confirmMenu = [
                [
                    ["text" => "👍 " . Lang::get("zentrotraderbot::bot.options.confirm_received"), "callback_data" => "/signoffer {$offer->code}"],
                    ["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.not_received"), "callback_data" => "/notreceived {$offer->code}"],
                ],
                [["text" => "⚖️ " . Lang::get("zentrotraderbot::bot.options.open_dispute"), "callback_data" => "/disputebyseller {$offer->code}"]],
            ];
            TelegramController::sendMessage([
                "message" => [
                    "text" => "👆 *" . Lang::get("zentrotraderbot::bot.proof_resubmit.new_evidence_title") . "*\n"
                        . "🆔 `{$offer->code}`\n\n"
                        . "🏦 " . Lang::get("zentrotraderbot::bot.proof_resubmit.new_evidence_body"),
                    "chat" => ["id" => $sellerSub->user_id],
                    "reply_markup" => json_encode(["inline_keyboard" => $confirmMenu]),
                ],
            ], $botTenant->token);
        }

        return [
            "text" => "✅ " . Lang::get("zentrotraderbot::bot.proof_resubmit.buyer_submitted"),
            "chat" => ["id" => $userId],
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "⚖️ " . Lang::get("zentrotraderbot::bot.options.open_dispute"), "callback_data" => "/disputebybuyer {$offer->code}"],
                    ]
                ]
            ]),
        ];
    }

    public function openDispute($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"));
            return ["text" => ""];
        }

        if (!in_array(strtoupper($offer->status), ['LOCKED', 'SIGNED'])) {
            $this->updateStatus($bot, "⚠️ " . Lang::get("zentrotraderbot::bot.sign_offer.wrong_state"));
            return ["text" => ""];
        }

        $sub = Suscriptions::on('tenant')->where('user_id', $bot->actor->user_id)->first();
        if (!$sub) {
            $this->updateStatus($bot, "🚫 " . Lang::get("zentrotraderbot::bot.cancel_onchain.not_buyer"));
            return ["text" => ""];
        }

        $walletAddress = strtolower($sub->data['wallet']['address'] ?? '');
        $isBuyer = $walletAddress === strtolower($offer->buyer_address);
        $isSeller = $walletAddress === strtolower($offer->seller_address);

        if (!$isBuyer && !$isSeller) {
            $this->updateStatus($bot, "🚫 " . Lang::get("zentrotraderbot::bot.cancel_onchain.not_buyer"));
            return ["text" => ""];
        }

        $role = $isBuyer ? 'buyer' : 'seller';
        $network = ConfigService::getNetworks(env("BASE_NETWORK"));
        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));
        $signerKey = decryptValue($sub->data['wallet']['private_key']);
        $relayerKey = decryptValue(env('TRADER_BOT_KEY'));
        $deadline = time() + 3600;

        $this->updateStatus($bot, "⌛️ " . Lang::get("zentrotraderbot::bot.proof_resubmit.opening_dispute"));

        try {
            $escrow = new EscrowController();
            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $relayerKey, $signerKey, $network, $offer, $deadline) {
                return $escrow->openDisputeWithSignature(
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
                $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.proof_resubmit.dispute_error"));
                return ["text" => ""];
            }

            $this->logOfferAction($offer, $role, 'dispute_opened', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $offer->code, 'tx_hash' => $txHash]);
            $this->updateStatus($bot, "✅ " . Lang::get("zentrotraderbot::bot.proof_resubmit.dispute_opened"));
            return ["text" => ""];

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ID already exists')) {
                $this->logOfferAction($offer, $role, 'dispute_opened', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $offer->code, 'note' => 'already_exists']);
                $this->updateStatus($bot, "✅ " . Lang::get("zentrotraderbot::bot.proof_resubmit.dispute_opened"));
                return ["text" => ""];
            }
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.proof_resubmit.dispute_error") . "\n" . $e->getMessage());
            return ["text" => ""];
        }
    }

    // =========================================================
    // INTERNAL CHAT — Chat anónimo entre comprador y vendedor
    // =========================================================

    public function startChat($bot, $code)
    {
        $offer = Offers::findByCode($code);
        if (!$offer || !in_array(strtoupper($offer->status), ['LOCKED', 'SIGNED', 'DISPUTED'])) {
            $this->updateStatus($bot, "❌ " . Lang::get("zentrotraderbot::bot.sign_offer.not_found"));
            return ["text" => ""];
        }

        $sub = Suscriptions::on('tenant')->where('user_id', $bot->actor->user_id)->first();
        if (!$sub) return ["text" => ""];

        $wallet  = strtolower($sub->data['wallet']['address'] ?? '');
        $isBuyer = $wallet === strtolower($offer->buyer_address);
        $isSeller = $wallet === strtolower($offer->seller_address);
        if (!$isBuyer && !$isSeller) return ["text" => ""];

        $role        = $isBuyer ? 'buyer' : 'seller';
        $counterpart = Lang::get("zentrotraderbot::bot.chat.counterpart_" . ($isBuyer ? 'seller' : 'buyer'));
        $botTenant   = app('active_bot');
        $chatKey     = "chat_{$botTenant->key}_{$bot->actor->user_id}";

        $exitBtn = [[["text" => "🚪 " . Lang::get("zentrotraderbot::bot.chat.exit_btn"), "callback_data" => "/exitchat"]]];

        // Send pinnable reminder message
        $reminderText = "💬 *" . Lang::get("zentrotraderbot::bot.chat.mode_active", ['counterpart' => $counterpart]) . "*";
        $raw = TelegramController::sendMessage([
            "message" => [
                "text"         => $reminderText,
                "chat"         => ["id" => $bot->actor->user_id],
                "reply_markup" => json_encode(["inline_keyboard" => $exitBtn]),
            ],
        ], $botTenant->token);

        $pinnedMsgId = json_decode($raw, true)['result']['message_id'] ?? null;
        if ($pinnedMsgId) {
            TelegramController::pinMessage([
                "message" => ["chat" => ["id" => $bot->actor->user_id], "message_id" => $pinnedMsgId],
            ], $botTenant->token);
        }

        Cache::put($chatKey, [
            'offer_code'       => $code,
            'role'             => $role,
            'pinned_message_id' => $pinnedMsgId,
        ], now()->addHours(24));

        return [
            "text" => "✅ " . Lang::get("zentrotraderbot::bot.chat.started", ['counterpart' => $counterpart]),
            "chat" => ["id" => $bot->actor->user_id],
        ];
    }

    public function chatRelay($bot)
    {
        $botTenant = app('active_bot');
        $chatKey   = "chat_{$botTenant->key}_{$bot->actor->user_id}";
        $chatData  = Cache::get($chatKey);
        if (!$chatData) return ["text" => ""];

        $text = $bot->message['text'] ?? '';

        if (str_starts_with(ltrim($text), '/exitchat')) {
            return $this->exitChat($bot);
        }

        // If user clicks the "Responder" button while already in chat, refresh session
        if (str_starts_with(ltrim($text), '/startchat')) {
            $parts = explode(' ', trim($text));
            return $this->startChat($bot, $parts[1] ?? $chatData['offer_code']);
        }

        $offer = Offers::findByCode($chatData['offer_code']);
        if (!$offer) {
            Cache::forget($chatKey);
            return ["text" => ""];
        }

        $role   = $chatData['role'];
        $prefix = "📨 *" . Lang::get("zentrotraderbot::bot.chat.{$role}_says") . ":*\n";

        $counterpartAddress = $role === 'buyer' ? $offer->seller_address : $offer->buyer_address;
        $counterpartSub     = Suscriptions::findByAddress($counterpartAddress);
        if (!$counterpartSub || !$counterpartSub->user_id) {
            return ["text" => "❌ " . Lang::get("zentrotraderbot::bot.chat.counterpart_unavailable")];
        }

        $counterpartId  = (int) $counterpartSub->user_id;
        $counterpartRole = $role === 'buyer' ? 'seller' : 'buyer';
        $counterpartLabel = Lang::get("zentrotraderbot::bot.chat.counterpart_{$role}"); // label for the counterpart's reply btn
        $replyMarkup = json_encode(["inline_keyboard" => [
            [["text" => Lang::get("zentrotraderbot::bot.options.message_{$counterpartRole}"), "callback_data" => "/startchat {$offer->code}"]],
        ]]);

        $this->forwardChatMessage($bot->message, $prefix, $counterpartId, $replyMarkup, $botTenant->token);

        // If DISPUTED, also relay to dispute thread
        if (strtoupper($offer->status) === 'DISPUTED') {
            $threadId  = $offer->data['dispute']['thread_id'] ?? null;
            $supportId = env('TRADER_BOT_SUPPORT');
            if ($threadId && $supportId) {
                $this->forwardChatMessage($bot->message, $prefix, (int) $supportId, null, $botTenant->token, (int) $threadId);
            }
        }

        return [
            "text" => "✅ " . Lang::get("zentrotraderbot::bot.chat.message_sent"),
            "chat" => ["id" => $bot->actor->user_id],
        ];
    }

    private function forwardChatMessage(array $msg, string $prefix, int $chatId, ?string $markup, string $token, ?int $threadId = null): void
    {
        $base = ['chat' => ['id' => $chatId]];
        if ($threadId) $base['message_thread_id'] = $threadId;
        if ($markup)   $base['reply_markup']       = $markup;

        if (!empty($msg['photo'])) {
            $photo = end($msg['photo']);
            TelegramController::sendPhoto(['message' => array_merge($base, [
                'photo' => $photo['file_id'],
                'text'  => $prefix . ($msg['caption'] ?? ''),
            ])], $token);
        } elseif (!empty($msg['document'])) {
            TelegramController::sendDocument(['message' => array_merge($base, [
                'document' => $msg['document']['file_id'],
                'text'     => $prefix . ($msg['caption'] ?? ''),
            ])], $token);
        } elseif (!empty($msg['video'])) {
            TelegramController::sendVideo(['message' => array_merge($base, [
                'video' => $msg['video']['file_id'],
                'text'  => $prefix . ($msg['caption'] ?? ''),
            ])], $token);
        } elseif (!empty($msg['voice'])) {
            TelegramController::sendVoice(['message' => array_merge($base, [
                'voice' => $msg['voice']['file_id'],
                'text'  => $prefix . ($msg['caption'] ?? ''),
            ])], $token);
        } elseif (!empty($msg['text'])) {
            TelegramController::sendMessage(['message' => array_merge($base, [
                'text' => $prefix . $msg['text'],
            ])], $token);
        } else {
            TelegramController::sendMessage(['message' => array_merge($base, [
                'text' => $prefix . Lang::get('zentrotraderbot::bot.chat.unsupported_media'),
            ])], $token);
        }
    }

    public function exitChat($bot)
    {
        $botTenant = app('active_bot');
        $chatKey   = "chat_{$botTenant->key}_{$bot->actor->user_id}";
        $chatData  = Cache::get($chatKey);

        if ($chatData && !empty($chatData['pinned_message_id'])) {
            $msgId = $chatData['pinned_message_id'];
            try {
                TelegramController::unpinChatMessage([
                    "message" => ["chat" => ["id" => $bot->actor->user_id], "message_id" => $msgId],
                ], $botTenant->token);
                TelegramController::deleteMessage([
                    "message" => ["chat" => ["id" => $bot->actor->user_id], "id" => $msgId],
                ], $botTenant->token);
            } catch (\Throwable $th) {
            }
        }

        Cache::forget($chatKey);

        return [
            "text" => "🚪 " . Lang::get("zentrotraderbot::bot.chat.exited"),
            "chat" => ["id" => $bot->actor->user_id],
        ];
    }

    // =========================================================
    // ARBITER BUTTONS — Teclado de acciones para el árbitro
    // =========================================================

    public static function arbiterButtons(string $code, string $userId): string
    {
        return json_encode([
            'inline_keyboard' => [
                [
                    ['text' => '✳️ ' . Lang::get('zentrotraderbot::bot.offer.disputed.btn_reqnew'), 'callback_data' => "/reqnewevi {$code} {$userId}"],
                ],
                [
                    ['text' => '🆚 ' . Lang::get('zentrotraderbot::bot.offer.disputed.btn_reqctr'), 'callback_data' => "/reqctrpart {$code} {$userId}"],
                ],
                [
                    ['text' => '🏅 ' . Lang::get('zentrotraderbot::bot.offer.disputed.btn_favor_buyer'), 'callback_data' => "confirmation|solvedispute-{$code}-buyer|deleteconfirmation"],
                ],
                [
                    ['text' => '🎖 ' . Lang::get('zentrotraderbot::bot.offer.disputed.btn_favor_seller'), 'callback_data' => "confirmation|solvedispute-{$code}-seller|deleteconfirmation"],
                ],
            ],
        ]);
    }

    // =========================================================
    // OFFER AUDIT LOG — Registra cronológicamente cada acción de comprador, vendedor y árbitro
    // =========================================================

    private function logOfferAction(Offers $offer, string $role, string $action, int $userId, string $callback = '', array $details = []): void
    {
        DB::transaction(function () use ($offer, $role, $action, $userId, $callback, $details) {
            $locked = Offers::lockForUpdate()->find($offer->id);
            $data = $locked->data ?? [];
            $data['audit'][] = [
                'user_id' => $userId,
                'role' => $role,
                'action' => $action,
                'callback' => $callback,
                'at' => now()->format("Y-m-d H:i:s"),
                'details' => $details,
            ];
            $locked->update(['data' => $data]);
        });
    }

    // =========================================================
    // REQUEST NEW EVIDENCE — Árbitro pide nuevas evidencias al usuario
    // =========================================================

    public function requestNewEvidenceFromUser($bot, $code, $userId)
    {
        $offer = Offers::findByCode($code);
        if (!$offer || strtoupper($offer->status) !== 'DISPUTED') {
            return ['text' => '❌ ' . Lang::get('zentrotraderbot::bot.sign_offer.not_found'), 'chat' => ['id' => $bot->actor->user_id]];
        }

        $botTenant = app('active_bot');
        $this->logOfferAction($offer, 'arbiter', 'request_new_evidence_from_user', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $code, 'target_tg_id' => $userId]);

        $this->seedEvidenceWizard($botTenant->key, (int) $userId, $offer->code);
        $cancelMenu = [[['text' => '❌ ' . Lang::get('zentrotraderbot::bot.options.cancel'), 'callback_data' => '/wizardcancel']]];
        TelegramController::sendMessage([
            'message' => [
                'text' => $this->buildEvidenceWizardPromptText($offer, 'more'),
                'chat' => ['id' => $userId],
                'reply_markup' => json_encode(['inline_keyboard' => $cancelMenu]),
            ],
        ], $botTenant->token);

        $threadId = $offer->data['dispute']['thread_id'] ?? null;
        $supportId = env('TRADER_BOT_SUPPORT');
        if ($threadId && $supportId) {
            TelegramController::sendMessage([
                'message' => [
                    'text' =>
                        "✅ " . Lang::get('zentrotraderbot::bot.offer.disputed.insufficient_thread_note') . "\n" .
                        "🆔 `{$userId}`",
                    'chat' => ['id' => $supportId],
                    'message_thread_id' => (int) $threadId,
                ],
            ], $botTenant->token);
        }

        return [
            'text' => '✅ ' . Lang::get('zentrotraderbot::bot.offer.disputed.insufficient_sent'),
            'chat' => ['id' => $bot->actor->user_id],
            'editprevious' => 1,
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '↖️ ' . Lang::get('telegrambot::bot.options.backtomainmenu'), 'callback_data' => 'menu']]]]),
        ];
    }

    // =========================================================
    // REQUEST EVIDENCE FROM COUNTERPART — Árbitro avisa a la contraparte
    // =========================================================

    public function requestEvidenceFromCounterpart($bot, $code, $userId)
    {
        $offer = Offers::findByCode($code);
        if (!$offer || strtoupper($offer->status) !== 'DISPUTED') {
            return ['text' => '❌ ' . Lang::get('zentrotraderbot::bot.sign_offer.not_found'), 'chat' => ['id' => $bot->actor->user_id]];
        }

        $botTenant = app('active_bot');
        $this->logOfferAction($offer, 'arbiter', 'request_evidence_from_counterpart', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $code, 'reference_tg_id' => $userId]);
        $buyerSub = Suscriptions::findByAddress($offer->buyer_address);
        $sellerSub = Suscriptions::findByAddress($offer->seller_address);
        $buyerTgId = $buyerSub ? (string) $buyerSub->user_id : null;
        $sellerTgId = $sellerSub ? (string) $sellerSub->user_id : null;

        $counterpartId = ((string) $userId === $buyerTgId) ? $sellerTgId : $buyerTgId;
        if (!$counterpartId) {
            return ['text' => '❌ ' . Lang::get('zentrotraderbot::bot.sign_offer.account_not_found'), 'chat' => ['id' => $bot->actor->user_id]];
        }

        $this->seedEvidenceWizard($botTenant->key, (int) $counterpartId, $offer->code);
        $cancelMenu = [[['text' => '❌ ' . Lang::get('zentrotraderbot::bot.options.cancel'), 'callback_data' => '/wizardcancel']]];
        TelegramController::sendMessage([
            'message' => [
                'text' => $this->buildEvidenceWizardPromptText($offer, 'more'),
                'chat' => ['id' => $counterpartId],
                'reply_markup' => json_encode(['inline_keyboard' => $cancelMenu]),
            ],
        ], $botTenant->token);

        $threadId = $offer->data['dispute']['thread_id'] ?? null;
        $supportId = env('TRADER_BOT_SUPPORT');
        if ($threadId && $supportId) {
            TelegramController::sendMessage([
                'message' => [
                    'text' =>
                        "✅ " . Lang::get('zentrotraderbot::bot.offer.disputed.ctrpart_thread_note') . "\n" .
                        "🆔 `{$counterpartId}`",
                    'chat' => ['id' => $supportId],
                    'message_thread_id' => (int) $threadId,
                ],
            ], $botTenant->token);
        }

        return [
            'text' => '✅ ' . Lang::get('zentrotraderbot::bot.offer.disputed.ctrpart_sent'),
            'chat' => ['id' => $bot->actor->user_id],
            'editprevious' => 1,
            'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '↖️ ' . Lang::get('telegrambot::bot.options.backtomainmenu'), 'callback_data' => 'menu']]]]),
        ];
    }

    // =========================================================
    // SOLVE DISPUTE — Árbitro resuelve la disputa on-chain
    // =========================================================

    public function solveDispute($bot, $code, $side)
    {
        $offer = Offers::findByCode($code);
        if (!$offer || strtoupper($offer->status) !== 'DISPUTED') {
            return ['text' => '❌ ' . Lang::get('zentrotraderbot::bot.sign_offer.not_found'), 'chat' => ['id' => $bot->actor->user_id]];
        }

        $botTenant = app('active_bot');
        $network = ConfigService::getNetworks(env('BASE_NETWORK'));
        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));
        $escrow = new EscrowController();
        $arbiterKey = decryptValue(env('ESCROW_ARBITER_KEY'));
        $winnerAddress = $side === 'buyer' ? $offer->buyer_address : $offer->seller_address;

        try {
            $txHash = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $arbiterKey, $network, $offer, $winnerAddress) {
                return $escrow->resolveDispute(
                    $rpc,
                    $arbiterKey,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $offer->id,
                    $winnerAddress,
                    env('ETHERSCAN_API_KEY')
                );
            });

            $sideLabel = $side === 'buyer'
                ? Lang::get('zentrotraderbot::bot.offer.disputed.btn_favor_buyer')
                : Lang::get('zentrotraderbot::bot.offer.disputed.btn_favor_seller');
            $this->logOfferAction($offer, 'arbiter', 'solve_dispute', $bot->actor->user_id, $bot->message['text'] ?? '', ['code' => $code, 'side' => $side, 'winner_address' => $winnerAddress, 'tx_hash' => $txHash]);
            $threadId = $offer->data['dispute']['thread_id'] ?? null;
            $supportId = env('TRADER_BOT_SUPPORT');

            if ($threadId && $supportId) {
                TelegramController::sendMessage([
                    'message' => [
                        'text' => "⚖️ *" . Lang::get('zentrotraderbot::bot.offer.disputed.solved_thread') . "*\n"
                            . "🆔 `{$offer->code}`\n"
                            . "🏆 {$sideLabel}\n"
                            . "🔗 `{$txHash}`",
                        'chat' => ['id' => $supportId],
                        'message_thread_id' => (int) $threadId,
                    ],
                ], $botTenant->token);
            }

            return [
                'text' => "✅ *" . Lang::get('zentrotraderbot::bot.offer.disputed.solved_done') . "*\n🔗 `{$txHash}`",
                'chat' => ['id' => $bot->actor->user_id],
                'editprevious' => 1,
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '↖️ ' . Lang::get('telegrambot::bot.options.backtomainmenu'), 'callback_data' => 'menu']]]]),
            ];
        } catch (\Throwable $e) {
            Log::error('solveDispute error: ' . $e->getMessage());
            return [
                'text' => '❌ ' . Lang::get('zentrotraderbot::bot.sign_offer.error') . ' ' . $e->getMessage(),
                'chat' => ['id' => $bot->actor->user_id],
            ];
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