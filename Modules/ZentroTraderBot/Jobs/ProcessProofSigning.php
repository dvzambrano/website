<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Services\BehaviorService;
use Modules\Laravel\Services\TextService;
use Dvzambrano\TelegramBot\Entities\TelegramBots;
use Dvzambrano\TelegramBot\Http\Controllers\TelegramController;
use Modules\Web3\Http\Controllers\EscrowController;
use Modules\Web3\Services\ConfigService;
use Modules\Web3\Traits\BlockchainTools;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Suscriptions;

class ProcessProofSigning implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BlockchainTools;

    public function __construct(
        protected string $tenantKey,
        protected string $offerCode,
        protected int    $buyerUserId
    ) {}

    public function handle(): void
    {
        $bot = BehaviorService::cache('tenant_' . $this->tenantKey, function () {
            return TelegramBots::where('key', $this->tenantKey)->first();
        });
        if (!$bot) return;
        $bot->connectToThisTenant();

        $offer = Offers::findByCode($this->offerCode);
        if (!$offer || !in_array(strtoupper($offer->status), ['LOCKED', 'SIGNED'])) return;

        $buyer = Suscriptions::on('tenant')->where('user_id', $this->buyerUserId)->first();
        if (!$buyer) return;

        $network    = ConfigService::getNetworks(env("BASE_NETWORK"));
        $rpcUrls    = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));
        $buyerKey   = decryptValue($buyer->data['wallet']['private_key']);
        $relayerKey = decryptValue(env('TRADER_BOT_KEY'));
        $deadline   = time() + 3600;

        try {
            $escrow  = new EscrowController();
            $txHash  = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $relayerKey, $buyerKey, $network, $offer, $deadline) {
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
                TelegramController::sendMessage(["message" => [
                    "text" => "❌ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.sign_offer.no_confirm_payment")),
                    "chat" => ["id" => $this->buyerUserId],
                    "parse_mode" => "MarkdownV2",
                ]], $bot->token);
            }

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ID already exists')) return;

            Log::error("🆘 ProcessProofSigning handle:", [
                'offer' => $this->offerCode,
                'buyer' => $this->buyerUserId,
                'error' => $e->getMessage(),
            ]);

            TelegramController::sendMessage(["message" => [
                "text" => "❌ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.sign_offer.error")) . "\n" . TextService::mdv2($e->getMessage()),
                "chat" => ["id" => $this->buyerUserId],
                "parse_mode" => "MarkdownV2",
            ]], $bot->token);
        }
    }
}
