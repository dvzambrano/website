<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Lang;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\Web3\Services\ConfigService;

class NotifyRampOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected $order;
    protected $bot;

    public function __construct($order, $bot)
    {
        // Convertimos a array para que Laravel no intente "re-hidratar" el modelo de la DB
        $this->order = $order->toArray();
        $this->bot = $bot->toArray();
    }

    public function handle()
    {
        $token = ConfigService::getToken(env('BASE_TOKEN'), env('BASE_NETWORK'));

        // Usamos los datos del array
        $orderId = $this->order['order_id'];
        $status = strtoupper($this->order['status']);
        $amount = number_format($this->order['amount'], 2);
        $currency = $this->order['currency'] ?? $token["symbol"];
        $userId = $this->order['user_id'];
        $statusemoji = $this->order['statusemoji'];
        $createdAt = $this->order['created_at'];

        if (env("DEBUG_MODE", false))
            Log::debug("🐞 NotifyRampOrder handle bot: " . json_encode($this->bot) . " order: " . json_encode($this->order));

        // Construimos un mensaje atractivo
        $message = "🔔 *" . Lang::get("zentrotraderbot::bot.prompts.buy.update.header") . "* \n\n";
        $message .= "🆔 `{$orderId}`\n";
        $message .= "💰 *{$amount} {$currency}*\n";
        $message .= "{$statusemoji} {$status}\n\n";
        $message .= "📅 " . $createdAt . "\n\n";

        if ($status === 'COMPLETED') {
            $message .= "✅ " . Lang::get("zentrotraderbot::bot.prompts.buy.update.completed");
        } elseif ($status === 'FAILED') {
            $message .= "❌ " . Lang::get("zentrotraderbot::bot.prompts.buy.update.failed");
        } else {
            $message .= "⏳ " . Lang::get("zentrotraderbot::bot.prompts.buy.update.processing");
        }

        TelegramController::sendMessage(
            array(
                "message" => array(
                    "text" => $message,
                    "chat" => array(
                        "id" => $userId,
                    ),
                ),
            ),
            $this->bot['token']
        );
    }
}