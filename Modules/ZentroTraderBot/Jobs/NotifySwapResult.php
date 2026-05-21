<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Services\TextService;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Entities\TronDealerDeposit;

class NotifySwapResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int    $depositId;
    protected string $tenantKey;

    public function __construct(int $depositId, string $tenantKey)
    {
        $this->depositId = $depositId;
        $this->tenantKey = $tenantKey;
    }

    public function handle(): void
    {
        $tenant = TelegramBots::where('key', $this->tenantKey)->first();
        if (!$tenant) {
            Log::error('[NotifySwapResult] Tenant not found', ['key' => $this->tenantKey]);
            return;
        }
        $tenant->connectToThisTenant();

        $deposit = TronDealerDeposit::find($this->depositId);
        if (!$deposit) {
            Log::error('[NotifySwapResult] Deposit not found', ['id' => $this->depositId]);
            return;
        }

        $t = fn(string $s) => TextService::mdv2($s);

        $amountIn  = number_format((float) $deposit->amount, 2);
        $assetIn   = strtoupper($deposit->asset ?? '');
        $chainIn   = strtoupper($deposit->network ?? '');
        $amountOut = number_format((float) $deposit->amount_out, 2);

        switch ($deposit->status) {
            case 'completed':
                $msg  = "✅ *Swap completado\!*\n\n";
                $msg .= "💸 Enviaste: `{$amountIn} {$assetIn}` \\({$chainIn}\\)\n";
                $msg .= "📥 Recibido en contrato: `{$amountOut} USDC` \\(Polygon\\)\n\n";
                $msg .= "_Tu saldo estará disponible en breve\\._";
                break;

            case 'expired':
                $msg  = "⌛ *Swap expirado*\n\n";
                $msg .= "No se recibió ningún depósito dentro del tiempo límite para tu swap de `{$amountIn} {$assetIn}`\\.\n\n";
                $msg .= "_Puedes iniciar un nuevo depósito cuando quieras\\._";
                break;

            case 'failed':
                $msg  = "❌ *Swap fallido*\n\n";
                $msg .= "Hubo un problema procesando tu swap de `{$amountIn} {$assetIn}`\\.\n\n";
                $msg .= "_Si enviaste fondos, TronDealer los devolverá automáticamente\\. Contacta soporte si no recibes el reembolso\\._";
                break;

            case 'refund_required':
            case 'refunded':
                $status = $deposit->status === 'refunded' ? 'realizado' : 'en proceso';
                $msg  = "🔄 *Reembolso {$status}*\n\n";
                $msg .= "Tu swap de `{$amountIn} {$assetIn}` no pudo completarse\\.\n\n";
                $msg .= "_Los fondos serán devueltos a la dirección de origen\\._";
                break;

            default:
                return;
        }

        TelegramController::sendMessage([
            'message' => [
                'text'       => $msg,
                'chat'       => ['id' => $deposit->user_id],
                'parse_mode' => 'MarkdownV2',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => '↖️ Menú principal', 'callback_data' => 'menu'],
                    ]],
                ]),
            ],
        ], $tenant->token);
    }
}
