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

            // 4. LA MAGIA: Re-programación con Decaimiento de Frecuencia
            if ($offer->status === 'open') {
                $diff = DateService::getTimeDifference($offer->created_at->getTimestamp(), now()->getTimestamp());

                // Total de minutos transcurridos para simplificar el cálculo
                $totalMinutes = ($diff['days'] * 1440) + ($diff['hours'] * 60) + $diff['minutes'];

                // Determinamos el siguiente intervalo de actualización
                if ($totalMinutes < 5) {
                    // Menos de 5 minutos: Actualizar cada minuto
                    $nextDelay = now()->addMinute();
                } elseif ($totalMinutes < 30) {
                    // Entre 5 y 30 minutos: Actualizar cada 5 minutos
                    $nextDelay = now()->addMinutes(5);
                } elseif ($totalMinutes < 60) {
                    // Entre 30 y 60 minutos: Actualizar cada 10 minutos
                    $nextDelay = now()->addMinutes(10);
                } elseif ($diff['days'] < 7) {
                    // Más de 1 hora pero menos de 7 días: Actualizar cada hora
                    $nextDelay = now()->addHour();
                } else {
                    // Más de 7 días: No re-programar más (la oferta "muere" visualmente)
                    return;
                }

                self::dispatch($this->tenant, $this->code)->delay($nextDelay);
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