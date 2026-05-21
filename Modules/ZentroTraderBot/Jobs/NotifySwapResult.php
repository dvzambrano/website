<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Services\TextService;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Entities\TronDealerDeposit;

class NotifySwapResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $depositId;
    protected string $tenantKey;

    public function __construct(int $depositId, string $tenantKey)
    {
        $this->depositId = $depositId;
        $this->tenantKey = $tenantKey;
    }

    public function handle(): void
    {
        if (env("DEBUG_MODE", false))
            Log::debug("🐞 [NotifySwapResult] Executing", [
                'deposit_id' => $this->depositId,
                'tenant_key' => $this->tenantKey,
            ]);

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

        $amountIn  = number_format((float) $deposit->amount, 2);
        $assetIn   = strtoupper($deposit->asset ?? '');
        $chainIn   = strtoupper($deposit->network ?? '');
        $amountOut = number_format((float) $deposit->amount_out, 2);

        $s = $deposit->status;

        switch ($s) {
            case 'deposit_detected':
                $title  = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.deposit_detected.title'));
                $body   = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.deposit_detected.body', ['amount' => $amountIn, 'asset' => $assetIn, 'chain' => $chainIn]));
                $footer = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.deposit_detected.footer'));
                $msg    = "🔵 *{$title}\\!*\n\n{$body}\n\n_{$footer}_";
                break;

            case 'processing':
                $title  = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.processing.title'));
                $body   = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.processing.body', ['amount' => $amountIn, 'asset' => $assetIn]));
                $footer = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.processing.footer'));
                $msg    = "🔄 *{$title}*\n\n{$body}\n\n_{$footer}_";
                break;

            case 'completed':
                $title    = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.completed.title'));
                $sent     = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.completed.sent', ['amount' => $amountIn, 'asset' => $assetIn, 'chain' => $chainIn]));
                $received = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.completed.received', ['amount_out' => $amountOut]));
                $footer   = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.completed.footer'));
                $msg      = "✅ *{$title}\\!*\n\n💸 {$sent}\n📥 {$received}\n\n_{$footer}_";
                break;

            case 'expired':
                $title  = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.expired.title'));
                $body   = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.expired.body', ['amount' => $amountIn, 'asset' => $assetIn]));
                $footer = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.expired.footer'));
                $msg    = "⌛ *{$title}*\n\n{$body}\n\n_{$footer}_";
                break;

            case 'failed':
                $title  = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.failed.title'));
                $body   = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.failed.body', ['amount' => $amountIn, 'asset' => $assetIn]));
                $footer = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.failed.footer'));
                $msg    = "❌ *{$title}*\n\n{$body}\n\n_{$footer}_";
                break;

            case 'rejected':
                $title  = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.rejected.title'));
                $body   = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.rejected.body', ['amount' => $amountIn, 'asset' => $assetIn]));
                $footer = TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.rejected.footer'));
                $msg    = "🚫 *{$title}*\n\n{$body}\n\n_{$footer}_";
                break;

            case 'refund_required':
            case 'refunded':
                $title  = TextService::mdv2(Lang::get("zentrotraderbot::bot.swap.{$s}.title"));
                $body   = TextService::mdv2(Lang::get("zentrotraderbot::bot.swap.{$s}.body", ['amount' => $amountIn, 'asset' => $assetIn]));
                $footer = TextService::mdv2(Lang::get("zentrotraderbot::bot.swap.{$s}.footer"));
                $msg    = "🔄 *{$title}*\n\n{$body}\n\n_{$footer}_";
                break;

            default:
                return;
        }

        $btnLabel = '↖️ ' . TextService::mdv2(Lang::get('zentrotraderbot::bot.swap.btn_mainmenu'));

        TelegramController::sendMessage([
            'message' => [
                'text'         => $msg,
                'chat'         => ['id' => $deposit->user_id],
                'parse_mode'   => 'MarkdownV2',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        ['text' => $btnLabel, 'callback_data' => 'menu'],
                    ]],
                ]),
            ],
        ], $tenant->token);
    }
}
