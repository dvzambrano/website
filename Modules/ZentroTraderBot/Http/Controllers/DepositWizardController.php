<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Modules\Laravel\Http\Controllers\Controller;
use Modules\Laravel\Services\TextService;
use Modules\TelegramBot\Http\Controllers\WizardController;
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
            return ['text' => '❌ ' . TextService::mdv2('No se pudieron obtener los pares de swap disponibles. Intenta más tarde.')];
        }

        if (empty($pairs)) {
            return ['text' => '❌ ' . TextService::mdv2('No hay pares de swap disponibles en este momento.')];
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
        $keyboard[] = [['text' => '❌ Cancelar', 'callback_data' => '/wizardcancel']];

        return [
            'text' => "💱 *" . TextService::mdv2('Depósito vía Swap') . "*\n\n" .
                TextService::mdv2('Selecciona la red y moneda desde donde enviarás los fondos:') . "\n\n" .
                "_" . TextService::mdv2('Los fondos llegarán como USDC en Polygon.') . "_",
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
            $limitsLine = "🤏 _" . TextService::mdv2("Mínimo: {$min} {$assetIn}") . "_ | 🫰 _" . TextService::mdv2("Máximo: {$max} {$assetIn}") . "_\n\n";
        }

        // Validate if user sent a number
        if ($text !== null && is_numeric($text)) {
            $amount = (float) $text;

            if ($pairInfo) {
                if ($amount < $pairInfo['min']) {
                    $min = number_format($pairInfo['min'], 2);
                    $error = "⚠️ _" . TextService::mdv2("El valor {$amount} {$assetIn} es menor al mínimo permitido ({$min}).") . "_\n\n";
                    return $this->renderEnterAmountStep($assetIn, $chainIn, $limitsLine, $error);
                }
                if ($amount > $pairInfo['max']) {
                    $max = number_format($pairInfo['max'], 2);
                    $error = "⚠️ _" . TextService::mdv2("El valor {$amount} {$assetIn} supera el máximo permitido ({$max}).") . "_\n\n";
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
            'text' => "💰 *" . TextService::mdv2("¿Cuánto {$assetIn} deseas enviar?") . "*\n" .
                "_" . TextService::mdv2("Red seleccionada: {$assetIn} ({$chainIn})") . "_\n\n" .
                $limitsLine .
                $error .
                TextService::mdv2('Escribe el monto a depositar (solo números, ej: 50):'),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '⬅️ Volver', 'callback_data' => '/wizardprevious'],
                    ],
                    [
                        ['text' => '❌ Cancelar', 'callback_data' => '/wizardcancel'],
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
            return ['text' => '❌ ' . TextService::mdv2('No se pudo obtener la cotización. Intenta de nuevo.')];
        }

        if (!($response['success'] ?? false) || empty($response['quote'])) {
            return ['text' => '❌ ' . TextService::mdv2('Cotización no disponible en este momento.')];
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

        $msg = "📋 *" . TextService::mdv2('Resumen del Swap') . "*\n\n";
        $msg .= "📤 *" . TextService::mdv2('Envías:') . "* `{$amountIn} {$assetIn}` \\({$chainIn}\\)\n";
        $msg .= "📥 *" . TextService::mdv2('Recibes aprox:') . "* `{$amountOut} USDC` \\(Polygon\\)\n\n";
        $msg .= "_" . TextService::mdv2('El monto recibido es estimado e incluye las comisiones del servicio.') . "_\n\n";
        $msg .= TextService::mdv2('¿Confirmas el depósito?');

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
                            ['text' => '⬅️ Volver', 'callback_data' => '/wizardprevious'],
                        ],
                        [
                            ['text' => '✅ Confirmar', 'callback_data' => 'tdeposit_confirm'],
                            ['text' => '❌ Cancelar', 'callback_data' => '/wizardcancel'],
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
            return ['text' => '❌ ' . TextService::mdv2('No se pudo crear el swap. Intenta de nuevo más tarde.')];
        }

        CheckSwapStatus::dispatch($deposit->id, $bot->tenant->key)
            ->delay(now()->addMinute());

        $address = TextService::mdv2($deposit->wallet_address);
        $amountIn = number_format((float) $deposit->amount, 2);
        $assetIn = strtoupper($deposit->asset ?? '');
        $chainIn = strtoupper($deposit->network ?? '');
        $expiresAt = $deposit->expires_at
            ? TextService::mdv2($deposit->expires_at->format('Y-m-d H:i') . ' UTC')
            : TextService::mdv2('~30 min');

        $msg = "✅ *" . TextService::mdv2('Swap creado exitosamente') . "*\n\n";
        $msg .= "📬 *" . TextService::mdv2("Envía {$amountIn} {$assetIn} ({$chainIn}) a esta dirección:") . "*\n";
        $msg .= "`{$address}`\n\n";
        $msg .= "⌛ *" . TextService::mdv2('Expira:') . "* {$expiresAt}\n\n";
        $msg .= "_" . TextService::mdv2('Monitorearemos el depósito automáticamente y te notificaremos al completarse.') . "_";

        return [
            'text' => $msg,
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '↖️ Menú principal', 'callback_data' => 'menu']],
                ]
            ]),
        ];
    }

    // ─── On cancel ───────────────────────────────────────────────────────────

    private function onCancel($bot): array
    {
        return [
            'text' => '❌ ' . TextService::mdv2('Depósito cancelado.'),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '↖️ Menú principal', 'callback_data' => 'menu']],
                ]
            ]),
        ];
    }
}
