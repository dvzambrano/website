<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Lang;
use Modules\TelegramBot\Http\Controllers\TelegramController;

class NotifyRampOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $bot;

    public function __construct($order, $bot)
    {
        $this->order = $order;
        $this->bot = $bot;
    }

    public function handle()
    {
        Log::info("NotifyRampOrder handle bot: " . json_encode($this->bot) . " order: " . json_encode($this->order));

        $amount = number_format($this->order->amount, 2);

        // Construimos un mensaje atractivo
        $message = "🔔 *" . Lang::get("zentrotraderbot::bot.prompts.deposit.update.header") . "* \n\n";
        $message .= "🆔 `{$this->order->order_id}`\n";
        $message .= "💰 *{$amount} {$this->order->currency}*\n";
        $message .= "{$this->order->statusemoji} {$this->order->status}\n\n";
        $message .= "📅 " . $this->order->created_at->format('d/m/Y H:i') . "\n\n";

        if ($this->order->status === 'COMPLETED') {
            $message .= "✅ " . Lang::get("zentrotraderbot::bot.prompts.deposit.update.completed");
        } elseif ($this->order->status === 'FAILED') {
            $message .= "❌ " . Lang::get("zentrotraderbot::bot.prompts.deposit.update.failed");
        } else {
            $message .= "⏳ " . Lang::get("zentrotraderbot::bot.prompts.deposit.update.processing");
        }

        $array = array(
            "message" => array(
                "text" => $message,
                "chat" => array(
                    "id" => $this->order->user_id,
                ),
            ),
        );
        TelegramController::sendMessage($array, $this->bot->token);
    }
}