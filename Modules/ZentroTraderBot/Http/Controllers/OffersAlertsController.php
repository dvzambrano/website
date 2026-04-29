<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Http\Controllers\WizardController;
use Modules\ZentroTraderBot\Entities\Currencies;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\OffersAlerts;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\NumberService;
use Modules\Laravel\Services\TextService;

class OffersAlertsController extends Controller
{
    private const TRIGGER_COMMAND = '/p2palertcreate';
    private const CONFIRM_COMMAND = '/alertconfirm';

    // =========================================================
    // WIZARD — Asistente de creación de alertas
    // =========================================================

    public function wizard($bot): array
    {
        $self  = $this;
        $steps = [
            ['name' => 'STEP_TYPE',    'handler' => fn($b, $s) => $self->stepType($b, $s)],
            ['name' => 'STEP_METHOD',  'handler' => fn($b, $s) => $self->stepMethod($b, $s)],
            ['name' => 'STEP_PRICE',   'handler' => fn($b, $s) => $self->stepPrice($b, $s)],
            ['name' => 'STEP_CONFIRM', 'handler' => fn($b, $s) => $self->stepConfirm($b, $s)],
        ];

        return (new WizardController())->run($bot, $steps, [
            'controller'  => self::class,
            'method'      => 'wizard',
            'initialData' => [],
            'onComplete'  => fn($b, $s) => $self->publishAlert($b, $s),
            'onCancel'    => fn($b) => $self->cancelWizardResponse($b),
        ]);
    }

    private function stepType($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text   = $bot->message['text'] ?? null;
        $userId = $bot->actor->user_id;

        if ($text !== null && !in_array($text, [self::TRIGGER_COMMAND])) {
            if (in_array($text, ['buy', 'sell'])) {
                return ['__advance' => true, 'merge' => ['type' => $text]];
            }
        }

        return [
            'text' =>
                "🔔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.title')) . "*\n" .
                "◾️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step', ['n' => TextService::getNumberAsEmoji(1), 'total' => TextService::getNumberAsEmoji(3)])) . "_\n" .
                "▫️ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step1.subtitle')) . "*\n" .
                "▫️ \n" .
                "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step1.ask')) . "_\n" .
                "◾️ " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step1.select')) . " 👇",
            'chat'         => ['id' => $userId],
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '🟩 ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step1.option_buy')),  'callback_data' => 'buy'],
                        ['text' => '🟥 ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step1.option_sell')), 'callback_data' => 'sell'],
                    ],
                    [['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.cancel')), 'callback_data' => '/wizardcancel']],
                ],
            ]),
            'editprevious' => $text === null ? 1 : 0,
        ];
    }

    private function stepMethod($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text   = $bot->message['text'] ?? null;
        $userId = $bot->actor->user_id;

        $navButtons = [
            ['text' => '⬅️ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.back')),   'callback_data' => '/wizardprevious'],
            ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.cancel')), 'callback_data' => '/wizardcancel'],
        ];

        if ($text !== null) {
            $method = $text === '/alertmethodany' ? null : $text;
            return ['__advance' => true, 'merge' => ['method' => $method]];
        }

        // Construir botones con todos los métodos activos (agrupados de a 2)
        $methods = $this->getAllActiveMethods();
        $buttons = [];
        foreach (array_chunk($methods, 2) as $chunk) {
            $row = [];
            foreach ($chunk as $m) {
                $row[] = ['text' => "{$m['icon']} {$m['name']}", 'callback_data' => $m['identifier']];
            }
            $buttons[] = $row;
        }
        $buttons[] = [['text' => '🔓 ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step2.any')), 'callback_data' => '/alertmethodany']];
        $buttons[] = $navButtons;

        return [
            'text' =>
                "🔔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.title')) . "*\n" .
                "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step', ['n' => TextService::getNumberAsEmoji(2), 'total' => TextService::getNumberAsEmoji(3)])) . "_\n" .
                "◾️ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step2.subtitle')) . "*\n" .
                "▫️ \n" .
                "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step2.ask')) . "_\n" .
                "◾️ " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step2.select')) . " 👇",
            'chat'         => ['id' => $userId],
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
            'editprevious' => 1,
        ];
    }

    private function stepPrice($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text   = $bot->message['text'] ?? null;
        $userId = $bot->actor->user_id;

        $navButtons = [
            ['text' => '⬅️ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.back')),   'callback_data' => '/wizardprevious'],
            ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.cancel')), 'callback_data' => '/wizardcancel'],
        ];

        if ($text !== null) {
            if ($text === '/alertpricenone') {
                return ['__advance' => true, 'merge' => ['max_price' => null]];
            }

            try {
                $parsed = NumberService::parse($text);
                if (is_numeric($parsed)) $text = $parsed;
            } catch (\Throwable $th) {
            }

            if (!is_numeric($text) || $text <= 0) {
                return [
                    'text' =>
                        "🔔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.title')) . "*\n" .
                        "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step', ['n' => TextService::getNumberAsEmoji(3), 'total' => TextService::getNumberAsEmoji(3)])) . "_\n" .
                        "▫️ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.subtitle')) . "*\n" .
                        "❌ " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.invalid', ['value' => $text])) . "\n" .
                        "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.ask')) . "_\n" .
                        "▫️ " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.example')) . " `1\.03`",
                    'chat'         => ['id' => $userId],
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '🔓 ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.any')), 'callback_data' => '/alertpricenone']],
                            $navButtons,
                        ],
                    ]),
                    'editprevious' => 1,
                ];
            }

            return ['__advance' => true, 'merge' => ['max_price' => $text]];
        }

        return [
            'text' =>
                "🔔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.title')) . "*\n" .
                "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step', ['n' => TextService::getNumberAsEmoji(3), 'total' => TextService::getNumberAsEmoji(3)])) . "_\n" .
                "◾️ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.subtitle')) . "*\n" .
                "▫️ \n" .
                "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.ask')) . "_\n" .
                "▫️ " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.example')) . " `1\.03`",
            'chat'         => ['id' => $userId],
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '🔓 ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.step3.any')), 'callback_data' => '/alertpricenone']],
                    $navButtons,
                ],
            ]),
            'editprevious' => 1,
        ];
    }

    private function stepConfirm($bot, array $state): array
    {
        $this->deleteUserText($bot);
        $text   = $bot->message['text'] ?? null;
        $userId = $bot->actor->user_id;

        if ($text === self::CONFIRM_COMMAND) {
            return ['__advance' => true];
        }

        $data      = $state['data'];
        $typeLabel = ($data['type'] ?? 'buy') === 'buy'
            ? Lang::get('zentrotraderbot::bot.alerts_wizard.step1.option_buy')
            : Lang::get('zentrotraderbot::bot.alerts_wizard.step1.option_sell');

        $methodLabel = isset($data['method']) && $data['method'] !== null
            ? TextService::mdv2($data['method'])
            : TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.any'));

        $priceLabel = isset($data['max_price']) && $data['max_price'] !== null
            ? TextService::mdv2(number_format((float) $data['max_price'], 2))
            : TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.unlimited'));

        return [
            'text' =>
                "🔔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.confirm.title')) . "*\n" .
                "▫️ \n" .
                "📌 " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.confirm.type'))   . ": *{$typeLabel}*\n" .
                "💳 " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.confirm.method')) . ": *{$methodLabel}*\n" .
                "💲 " . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.confirm.max_price')) . ": *{$priceLabel}*\n" .
                "▫️ \n" .
                "👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            'chat'         => ['id' => $userId],
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '✅ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.confirm.save')), 'callback_data' => self::CONFIRM_COMMAND]],
                    [
                        ['text' => '⬅️ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.back')),   'callback_data' => '/wizardprevious'],
                        ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.cancel')), 'callback_data' => '/wizardcancel'],
                    ],
                ],
            ]),
            'editprevious' => 1,
        ];
    }

    private function publishAlert($bot, array $state): array
    {
        $userId = $bot->actor->user_id;
        $data   = $state['data'];

        OffersAlerts::create([
            'user_id'        => $userId,
            'type'           => $data['type'],
            'payment_method' => $data['method'] ?? null,
            'max_price'      => $data['max_price'] ?? null,
            'is_active'      => true,
        ]);

        return [
            'text' =>
                "✅ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.saved')) . "*\n" .
                "▫️ \n" .
                "👁 _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.watching')) . "_",
            'chat'         => ['id' => $userId],
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '🔔 ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.view_mine')), 'callback_data' => '/p2palerts']],
                    [['text' => '⬅️ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.backtop2pmenu')), 'callback_data' => '/p2pmenu']],
                ],
            ]),
            'editprevious' => 1,
        ];
    }

    private function cancelWizardResponse($bot): array
    {
        $userId     = $bot->actor->user_id;
        $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);
        return [
            'text' =>
                "❌ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.cancelled_title')) . "*\n" .
                "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts_wizard.cancelled')) . "_\n\n" .
                "👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            'chat'         => ['id' => $userId],
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '⬅️ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.backtop2pmenu')), 'callback_data' => '/p2pmenu']],
                    [['text' => '↖️ ' . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), 'callback_data' => 'menu']],
                ],
            ]),
            'editprevious' => $isCallback ? 1 : 0,
        ];
    }

    // =========================================================
    // LISTADO — Ver alertas del usuario
    // =========================================================

    public function listAlerts($bot): array
    {
        $userId = $bot->actor->user_id;
        $alerts = OffersAlerts::where('user_id', $userId)->where('is_active', true)->get();

        $keyboard = [];

        if ($alerts->isEmpty()) {
            $body = "▫️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.empty')) . "_\n";
        } else {
            $body = '';
            foreach ($alerts as $alert) {
                $typeLabel = $alert->type === 'buy'
                    ? TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.type_buy'))
                    : TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.type_sell'));

                $methodLabel = $alert->payment_method
                    ? TextService::mdv2($alert->payment_method)
                    : TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.method_any'));

                $priceLabel = $alert->max_price
                    ? TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.price_max', ['price' => number_format((float) $alert->max_price, 2)]))
                    : TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.price_unlimited'));

                $body .= "▫️ {$typeLabel} · {$methodLabel} · {$priceLabel}\n";

                $keyboard[] = [
                    [
                        'text'          => '🗑 ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.delete')) . " #{$alert->id}",
                        'callback_data' => "confirmation|p2palertdelete-{$alert->id}|/p2palerts",
                    ],
                ];
            }
        }

        $keyboard[] = [['text' => '➕ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.create')), 'callback_data' => '/p2palertcreate']];
        $keyboard[] = [['text' => '⬅️ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.options.backtop2pmenu')), 'callback_data' => '/p2pmenu']];

        return [
            'text' =>
                "🔔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alerts.title')) . "*\n" .
                "▫️ \n" .
                $body,
            'chat'         => ['id' => $userId],
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'editprevious' => 1,
        ];
    }

    // =========================================================
    // ELIMINAR alerta
    // =========================================================

    public function deleteAlert($bot, int $alertId): array
    {
        $userId = $bot->actor->user_id;
        OffersAlerts::where('id', $alertId)->where('user_id', $userId)->delete();

        return $this->listAlerts($bot);
    }

    // =========================================================
    // NOTIFICAR — Usuarios con alertas coincidentes
    // =========================================================

    /**
     * Cuando se publica una oferta de COMPRA, busca las 3 mejores ofertas de
     * VENTA compatibles (mismo método de pago, estado open, otro usuario) y
     * notifica al comprador por DM para que pueda aplicar directamente.
     */
    public static function notifyBestSellMatches(Offers $offer, string $token): void
    {
        if (strtolower($offer->type) !== 'buy' || strtolower($offer->status) !== 'open') {
            return;
        }

        $sellOffers = Offers::where('status', 'open')
            ->where('type', 'sell')
            ->where('payment_method', $offer->payment_method)
            ->where('user_id', '!=', $offer->user_id)
            ->orderBy('price_per_usd', 'asc')
            ->limit(3)
            ->get();

        if ($sellOffers->isEmpty()) {
            return;
        }

        $text = "🎯 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.buy_match.title')) . "*\n"
            . "▫️ \n"
            . "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.buy_match.subtitle')) . "_\n"
            . "▫️ \n";

        $buttons = [];

        foreach ($sellOffers as $index => $sellOffer) {
            $num    = $index + 1;
            $amount = number_format($sellOffer->amount, 2);
            $price  = number_format($sellOffer->price_per_usd, 2);

            $text .= "🔴 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.buy_match.offer_label', ['n' => $num])) . "* — `{$sellOffer->code}`\n"
                . "💵 " . TextService::mdv2($amount) . " USD · 🔖 " . TextService::mdv2($price) . " " . TextService::mdv2($sellOffer->currency) . "/USD\n"
                . "💳 " . TextService::mdv2($sellOffer->payment_method) . "\n"
                . "▫️ \n";

            $buttons[] = [['text' => "👉 #$num — $amount USD @ $price " . $sellOffer->currency, 'callback_data' => '/showoffer ' . $sellOffer->code]];
        }

        $text .= "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.buy_match.hint')) . "_";
        $buttons[] = [['text' => '📋 ' . Lang::get('zentrotraderbot::bot.buy_match.view_all'), 'callback_data' => '/p2pmenu']];

        TelegramController::sendMessage([
            'message' => [
                'chat'         => ['id' => $offer->user_id],
                'text'         => $text,
                'parse_mode'   => 'MarkdownV2',
                'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
            ],
        ], $token);
    }

    public static function notifyMatchingAlerts(Offers $offer, string $token): void
    {
        if (strtolower($offer->status) !== 'open') {
            return;
        }

        $alerts = OffersAlerts::where('is_active', true)
            ->where('type', $offer->type)
            ->where(function ($q) use ($offer) {
                $q->whereNull('payment_method')
                  ->orWhere('payment_method', $offer->payment_method);
            })
            ->where(function ($q) use ($offer) {
                $q->whereNull('max_price')
                  ->orWhere('max_price', '>=', $offer->price_per_usd);
            })
            ->get();

        foreach ($alerts as $alert) {
            // No notificar al propio autor de la oferta
            if ((int) $alert->user_id === (int) $offer->user_id) {
                continue;
            }

            $title = "🔔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alert_match.title')) . "*\n"
                . "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.alert_match.line1')) . "_";

            $text = $offer->renderAsTelegramMessage($title, false, "", true);

            TelegramController::sendMessage([
                'message' => [
                    'chat'         => ['id' => $alert->user_id],
                    'text'         => $text,
                    'parse_mode'   => 'MarkdownV2',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '👀 ' . Lang::get('zentrotraderbot::bot.alert_match.view'), 'callback_data' => '/showoffer ' . $offer->code]],
                            [['text' => '🔔 ' . Lang::get('zentrotraderbot::bot.alerts.view_mine'), 'callback_data' => '/p2palerts']],
                        ],
                    ]),
                ],
            ], $token);
        }
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private function getAllActiveMethods(): array
    {
        $result = [];
        $seen   = [];

        $currencies = Currencies::where('is_active', true)->with(['paymentmethods' => function ($q) {
            $q->wherePivot('is_active', true);
        }])->get();

        foreach ($currencies as $currency) {
            foreach ($currency->paymentmethods as $method) {
                if (!isset($seen[$method->identifier])) {
                    $seen[$method->identifier] = true;
                    $result[] = [
                        'identifier' => $method->identifier,
                        'name'       => $method->name,
                        'icon'       => $method->icon ?? '💳',
                    ];
                }
            }
        }

        return $result;
    }

    private function deleteUserText($bot): void
    {
        $isCallback = isset($bot->callback_query) || isset($bot->message['reply_markup']);
        if (!$isCallback && !empty($bot->message['message_id'])) {
            try {
                TelegramController::deleteMessage([
                    'message' => [
                        'id'   => $bot->message['message_id'],
                        'chat' => ['id' => $bot->message['chat']['id']],
                    ],
                ], $bot->tenant->token);
            } catch (\Throwable $th) {
            }
        }
    }
}
