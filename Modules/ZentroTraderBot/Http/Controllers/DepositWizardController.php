<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Http\Controllers\Controller;
use Modules\Laravel\Services\TextService;
use Modules\TelegramBot\Http\Controllers\WizardController;
use Modules\Web3\Services\ConfigService;
use Modules\ZentroTraderBot\Jobs\CheckSwapStatus;
use Modules\ZentroTraderBot\Services\DepositService;


class DepositWizardController extends Controller
{
    private DepositService $service;

    public function __construct()
    {
        $this->service = new DepositService();
    }

    public function wizard($bot): mixed
    {
        $steps = [
            [
                'name' => 'SELECT_PAIR',
                'handler' => fn($bot, $state) => $this->stepSelectPair($bot, $state),
            ],
            [
                'name' => 'ENTER_AMOUNT',
                'handler' => fn($bot, $state) => $this->stepEnterAmount($bot, $state),
            ],
            [
                'name' => 'CONFIRM_QUOTE',
                'handler' => fn($bot, $state) => $this->stepConfirmQuote($bot, $state),
            ],
        ];

        return (new WizardController())->run($bot, $steps, [
            'controller' => self::class,
            'method' => 'wizard',
            'initialData' => [],
            'onComplete' => fn($bot, $state) => $this->onComplete($bot, $state),
            'onCancel' => fn($bot) => $this->onCancel($bot),
        ]);
    }

    // ─── Step 1: choose network/token ───────────────────────────────────────

    private function stepSelectPair($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;

        // If user tapped a pair button
        if ($text && str_starts_with($text, 'tdeposit_pair_')) {
            $parts = explode('_', $text); // tdeposit_pair_USDT_bsc
            if (count($parts) === 4) {
                return [
                    '__advance' => true,
                    'merge' => [
                        'asset_in' => strtoupper($parts[2]),
                        'chain_in' => strtolower($parts[3]),
                    ]
                ];
            }
        }

        // Show available pairs
        try {
            $pairs = $this->service->getAvailableInputPairs();
        } catch (\Throwable $e) {
            Log::error('[DepositWizard] getAvailableInputPairs failed', ['error' => $e->getMessage()]);
            return ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.error_no_pairs'))];
        }

        if (empty($pairs)) {
            return ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.error_unavailable'))];
        }

        // Build one button per pair, two per row
        $keyboard = [];
        $row = [];
        foreach ($pairs as $i => $pair) {
            $row[] = [
                'text' => $pair['label'],
                'callback_data' => "tdeposit_pair_{$pair['asset_in']}_{$pair['chain_in']}",
            ];
            if (count($row) === 2 || $i === count($pairs) - 1) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        $keyboard[] = [['text' => '❌ ' . Lang::get('zentrotraderbot::bot.deposit_wizard.btn_cancel'), 'callback_data' => '/wizardcancel']];

        return [
            'text' => "💱 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.header')) . "*\n\n" .
                TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.select_pair')) . "\n\n" .
                "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.polygon_notice', [
                    'token'   => DepositService::assetOut(),
                    'network' => ConfigService::getActiveNetwork()['chain'] ?? ConfigService::getActiveNetwork()['shortName'] ?? '',
                ])) . "_",
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ];
    }

    // ─── Step 2: enter amount ────────────────────────────────────────────────

    private function stepEnterAmount($bot, array $state): array
    {
        $data = $state['data'];
        $assetIn = $data['asset_in'] ?? '';
        $chainIn = strtoupper($data['chain_in'] ?? '');
        $text = $bot->message['text'] ?? null;

        // Always load pair limits so we can show them and validate
        $pairInfo = null;
        try {
            foreach ($this->service->getAvailableInputPairs() as $pair) {
                if ($pair['asset_in'] === $assetIn && $pair['chain_in'] === strtolower($data['chain_in'] ?? '')) {
                    $pairInfo = $pair;
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::error('[DepositWizard] getAvailableInputPairs in stepEnterAmount', ['error' => $e->getMessage()]);
        }

        $limitsLine = '';
        if ($pairInfo) {
            $min = number_format($pairInfo['min'], 2);
            $max = number_format($pairInfo['max'], 2);
            $limitsLine =
                "🤏 _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.min_amount', ['amount' => $min, 'asset' => $assetIn])) . "_\n" .
                "🫰 _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.max_amount', ['amount' => $max, 'asset' => $assetIn])) . "_\n\n";
        }

        // Validate if user sent a number
        if ($text !== null && is_numeric($text)) {
            $amount = (float) $text;

            if ($pairInfo) {
                if ($amount < $pairInfo['min']) {
                    $min = number_format($pairInfo['min'], 2);
                    $error = "⚠️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.error_below_min', ['amount' => $amount, 'asset' => $assetIn, 'min' => $min])) . "_\n";
                    return $this->renderEnterAmountStep($assetIn, $chainIn, $limitsLine, $error);
                }
                if ($amount > $pairInfo['max']) {
                    $max = number_format($pairInfo['max'], 2);
                    $error = "⚠️ _" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.error_above_max', ['amount' => $amount, 'asset' => $assetIn, 'max' => $max])) . "_\n";
                    return $this->renderEnterAmountStep($assetIn, $chainIn, $limitsLine, $error);
                }
            }

            return ['__advance' => true, 'merge' => ['amount_in' => $amount]];
        }

        return $this->renderEnterAmountStep($assetIn, $chainIn, $limitsLine);
    }

    private function renderEnterAmountStep(string $assetIn, string $chainIn, string $limitsLine, string $error = ''): array
    {
        return [
            'text' => "💰 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.ask_amount', ['asset' => $assetIn])) . "*\n" .
                "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.selected_network', ['asset' => $assetIn, 'chain' => $chainIn])) . "_\n\n" .
                $limitsLine .
                $error .
                TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.amount_hint')),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '⬅️ ' . Lang::get('zentrotraderbot::bot.deposit_wizard.btn_back'), 'callback_data' => '/wizardprevious'],
                    ],
                    [
                        ['text' => '❌ ' . Lang::get('zentrotraderbot::bot.deposit_wizard.btn_cancel'), 'callback_data' => '/wizardcancel'],
                    ],
                ]
            ]),
        ];
    }

    // ─── Step 3: fetch quote and confirm ────────────────────────────────────

    private function stepConfirmQuote($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;
        $data = $state['data'];

        if ($text === 'tdeposit_confirm') {
            return ['__advance' => true, 'merge' => []];
        }

        // Fetch a fresh quote from TronDealer
        try {
            $response = $this->service->getQuote(
                $data['asset_in'],
                $data['chain_in'],
                (float) $data['amount_in']
            );
        } catch (\Throwable $e) {
            Log::error('[DepositWizard] getQuote failed', ['error' => $e->getMessage()]);
            return ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.error_quote'))];
        }

        if (!($response['success'] ?? false) || empty($response['quote'])) {
            return ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.error_quote_now'))];
        }

        $quote = $response['quote'];
        $adjusted = $this->service->computeAdjustedAmountOut(
            (float) $quote['amount_out'],
            (float) $quote['fee_pct']
        );

        $amountIn = number_format((float) $quote['amount_in'], 2);
        $assetIn = strtoupper($quote['asset_in']);
        $chainIn = strtoupper($quote['chain_in']);
        $amountOut = number_format($adjusted, 2);
        $assetOut = DepositService::assetOut();
        $chainOutLabel = ConfigService::getActiveNetwork()['chain'] ?? ConfigService::getActiveNetwork()['shortName'] ?? '';

        $msg = "📋 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.quote_header')) . "*\n\n";
        $msg .= "📤 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.quote_you_send')) . "* `{$amountIn} {$assetIn}` \\({$chainIn}\\)\n";
        $msg .= "📥 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.quote_you_receive')) . "* `{$amountOut} {$assetOut}` \\(" . TextService::mdv2($chainOutLabel) . "\\)\n\n";
        $msg .= "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.quote_disclaimer')) . "_\n\n";
        $msg .= TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.quote_confirm'));

        return [
            '__update' => true,
            'merge' => [
                'quote_id' => $quote['id'],
                'amount_out' => (float) $quote['amount_out'],
                'fee_pct' => (float) $quote['fee_pct'],
            ],
            'response' => [
                'text' => $msg,
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '⬅️ ' . Lang::get('zentrotraderbot::bot.deposit_wizard.btn_back'), 'callback_data' => '/wizardprevious'],
                        ],
                        [
                            ['text' => '✅ ' . Lang::get('zentrotraderbot::bot.deposit_wizard.btn_confirm'), 'callback_data' => 'tdeposit_confirm'],
                            ['text' => '❌ ' . Lang::get('zentrotraderbot::bot.deposit_wizard.btn_cancel'), 'callback_data' => '/wizardcancel'],
                        ],
                    ]
                ]),
            ],
        ];
    }

    // ─── On wizard complete ──────────────────────────────────────────────────

    private function onComplete($bot, array $state): array
    {
        $data = $state['data'];

        try {
            $deposit = $this->service->createSwap(
                $bot->actor->user_id,
                $data['asset_in'],
                $data['chain_in'],
                (float) $data['amount_in'],
                $data['quote_id'],
                (float) $data['amount_out'],
                (float) $data['fee_pct']
            );
        } catch (\Throwable $e) {
            Log::error('[DepositWizard] createSwap failed', ['error' => $e->getMessage()]);
            return ['text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.error_create'))];
        }

        CheckSwapStatus::dispatch($deposit->id, $bot->tenant->key)
            ->delay(now()->addMinute());

        $address = TextService::mdv2($deposit->wallet_address);
        $amountIn = number_format((float) $deposit->amount, 2);
        $assetIn = strtoupper($deposit->asset ?? '');
        $chainIn = strtoupper($deposit->network ?? '');

        if ($deposit->expires_at) {
            $tzOffset = $bot->actor->data[$bot->tenant->code]['time_zone'] ?? null;
            $localTime = $tzOffset !== null
                ? $deposit->expires_at->copy()->addHours(\intval($tzOffset))->format('Y-m-d H:i')
                : $deposit->expires_at->format('Y-m-d H:i');
            $expiresAt = TextService::mdv2($localTime);
        } else {
            $expiresAt = TextService::mdv2('~30 min');
        }

        $swapId = TextService::mdv2($deposit->swap_id ?? '');

        $msg = "✅ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.success_header')) . "*\n\n";
        $msg .= "🆔 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.swap_id_label')) . "* `{$swapId}`\n\n";
        $msg .= "📬 *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.send_to', ['amount' => $amountIn, 'asset' => $assetIn, 'chain' => $chainIn])) . "*\n";
        $msg .= "`{$address}`\n\n";
        $msg .= "⌛ *" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.expires_label')) . "* {$expiresAt}\n\n";
        $msg .= "_" . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.monitor_notice')) . "_";

        return [
            'text' => $msg,
            'photo' => 'https://quickchart.io/qr?text=' . urlencode($deposit->wallet_address) . '&size=220',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
                ]
            ]),
        ];
    }

    // ─── On cancel ───────────────────────────────────────────────────────────

    private function onCancel($bot): array
    {
        return [
            'text' => '❌ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.deposit_wizard.cancelled')),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]
                    ],
                ]
            ]),
        ];
    }
}
