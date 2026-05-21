<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Http\Controllers\Controller;
use Modules\Laravel\Services\TextService;
use Modules\ZentroTraderBot\Entities\TronDealerDeposit;
use Modules\ZentroTraderBot\Services\DepositService;

class DepositsViewController extends Controller
{
    private const STATUS_LABELS = [
        'pending'          => '⏳ Pendiente',
        'waiting_deposit'  => '🟡 Esperando depósito',
        'deposit_detected' => '🔵 Depósito detectado',
        'processing'       => '🔄 Procesando',
        'completed'        => '✅ Completado',
        'expired'          => '⏰ Expirado',
        'failed'           => '❌ Fallido',
        'refund_required'  => '⚠️ Requiere reembolso',
        'refunded'         => '💸 Reembolsado',
        'rejected'         => '🚫 Rechazado',
    ];

    public function listDeposits($bot): array
    {
        $deposits = TronDealerDeposit::where('user_id', $bot->actor->user_id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($deposits->isEmpty()) {
            return [
                'text' => "📋 *" . TextService::mdv2('Mis Swaps') . "*\n\n" .
                    "_" . TextService::mdv2('No tienes swaps registrados.') . "_",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => '↖️ ' . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), 'callback_data' => 'menu']],
                    ],
                ]),
            ];
        }

        $tzOffset = $bot->actor->data[$bot->tenant->code]['time_zone'] ?? null;

        $msg = "📋 *" . TextService::mdv2('Mis Swaps') . "*\n\n";

        foreach ($deposits as $deposit) {
            $label     = self::STATUS_LABELS[$deposit->status] ?? ('❓ ' . $deposit->status);
            $amountIn  = number_format((float) $deposit->amount, 2);
            $assetIn   = strtoupper($deposit->asset ?? '');
            $chainIn   = strtoupper($deposit->network ?? '');

            $msg .= "*" . TextService::mdv2($label) . "*\n";
            $msg .= "   `{$amountIn} {$assetIn}` \\(" . TextService::mdv2($chainIn) . "\\) → `USDC`\n";

            if (in_array($deposit->status, DepositService::ACTIVE_STATUSES)) {
                $address = $deposit->wallet_address ?? '—';
                $msg .= "   📬 `" . TextService::mdv2($address) . "`\n";

                if ($deposit->expires_at) {
                    $expiresStr = $this->formatDate($deposit->expires_at, $tzOffset);
                    $msg .= "   ⌛ " . TextService::mdv2('Expira: ' . $expiresStr) . "\n";
                }
            } else {
                $dateField = $deposit->confirmed_at ?? $deposit->detected_at ?? $deposit->created_at;
                if ($dateField) {
                    $dateStr = $this->formatDate($dateField, $tzOffset);
                    $msg .= "   📅 " . TextService::mdv2($dateStr) . "\n";
                }
            }

            $msg .= "\n";
        }

        return [
            'text' => rtrim($msg),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '🔄 ' . TextService::mdv2('Actualizar'), 'callback_data' => '/myswaps']],
                    [['text' => '↖️ ' . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), 'callback_data' => 'menu']],
                ],
            ]),
        ];
    }

    private function formatDate($date, ?string $tzOffset): string
    {
        if ($tzOffset !== null) {
            return $date->copy()->addHours(\intval($tzOffset))->format('Y-m-d H:i') . ' UTC' . $tzOffset;
        }

        return $date->format('Y-m-d H:i') . ' UTC';
    }
}
