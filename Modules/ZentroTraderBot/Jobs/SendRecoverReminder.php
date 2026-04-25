<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\Laravel\Services\BehaviorService;
use Modules\Laravel\Services\TextService;

/**
 * Enviado cuando el vendedor intenta reclamar antes de que expire el plazo.
 * Se despacha con un delay igual al tiempo restante para que, al ejecutarse,
 * el plazo ya haya expirado y se pueda enviar un nuevo mensaje con el boton de reclamar.
 */
class SendRecoverReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $offerCode;
    protected $sellerUserId;

    public function __construct(string $tenant, string $offerCode, int $sellerUserId)
    {
        $this->tenant       = $tenant;
        $this->offerCode    = $offerCode;
        $this->sellerUserId = $sellerUserId;
    }

    public function handle(): void
    {
        try {
            $bot = BehaviorService::cache('tenant_' . $this->tenant, function () {
                return TelegramBots::where('key', $this->tenant)->first();
            });

            if (!$bot) {
                Log::error("❌ [SendRecoverReminder] Tenant no encontrado: {$this->tenant}");
                return;
            }

            $bot->connectToThisTenant();

            $offer = Offers::findByCode($this->offerCode);

            // Solo enviamos el recordatorio si el intercambio sigue bloqueado.
            // Si ya fue completado, cancelado, disputado, etc., no es necesario.
            if (!$offer || strtoupper($offer->status) !== 'LOCKED') {
                return;
            }

            $t = fn(string $key, array $r = []) => TextService::mdv2(Lang::get($key, $r));
            $text = "⏰ *" . $t("zentrotraderbot::bot.recover_offer.ready_title") . "*\n"
                . "🆔 `{$offer->code}`\n\n"
                . "📋 " . $t("zentrotraderbot::bot.recover_offer.ready_body");

            $payload = [
                'message' => [
                    'chat' => ['id' => $this->sellerUserId],
                    'text' => $text,
                    'parse_mode' => 'MarkdownV2',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [["text" => "⏱️ " . Lang::get("zentrotraderbot::bot.recover_offer.ready_button"), "callback_data" => "/recoveroffer {$offer->code}"]],
                        ],
                    ]),
                ],
            ];

            TelegramController::sendMessage($payload, $bot->token);

        } catch (\Throwable $th) {
            Log::error("🆘 SendRecoverReminder handle error: " . $th->getMessage(), [
                'tenant'        => $this->tenant,
                'offer_code'    => $this->offerCode,
                'seller_user_id' => $this->sellerUserId,
            ]);
        }
    }
}
