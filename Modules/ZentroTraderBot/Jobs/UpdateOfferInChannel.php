<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Services\DateService;

class UpdateOfferInChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $code;

    public function __construct($tenant, $code)
    {
        $this->tenant = $tenant;
        $this->code = $code;
    }

    public function handle()
    {
        try {
            // 1. Conectar al Tenant para obtener el token del bot
            $tenant = TelegramBots::where('key', $this->tenant)->first();
            if (!$tenant)
                return;
            $tenant->connectToThisTenant();

            $offer = Offers::findByCode($this->code);

            // 1. Si la oferta ya no existe o está cerrada, no hacemos nada
            if (!$offer || in_array($offer->status, ['completed', 'cancelled'])) {
                return;
            }

            // 3. Ejecutamos la edición en Telegram
            $messageData = $offer->getAsChannelMessage($tenant);

            $payload = [
                "message" => [
                    "message_id" => $offer->data['channel']['message_id'],
                    "chat" => ["id" => env("TRADER_BOT_CHANNEL")],
                    "text" => $messageData['message']['text'],
                    "reply_markup" => $messageData['message']['reply_markup']
                ]
            ];
            TelegramController::editMessageText($payload, $tenant->token);

            // 4. LA MAGIA: Si la oferta sigue abierta, re-programamos este job 
            // Dentro del handle del Job
            $diff = DateService::getTimeDifference($offer->created_at->getTimestamp(), now()->getTimestamp());
            if ($offer->status === 'open' && $diff['days'] < 7) {
                self::dispatch($this->tenant, $this->code)->delay(now()->addMinutes(5));
            }

        } catch (\Throwable $th) {
            Log::error('🆘 UpdateOfferInChannel handle error', [
                'tenant' => $this->tenant,
                'code' => $this->code,
                'message' => $th->getMessage(),
            ]);
        }

    }
}