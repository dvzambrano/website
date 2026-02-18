<?php

namespace Modules\ZentroTraderBot\Observers;

use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\OffersAlerts; // Asumiendo que OffersAlerts también está en el módulo
use Modules\TelegramBot\Http\Controllers\TelegramController;

class OfferObserver
{
    /**
     * Manejar el evento "created" de la Oferta.
     */
    public function created(Offers $offer): void
    {
        // Buscamos las alertas que coincidan
        $matchingAlerts = OffersAlerts::where('type', $offer->type)
            ->where('payment_method', $offer->payment_method)
            ->where('is_active', true)
            ->get();

        // mandarlo al canal y al grupo

        // mandarlo a los usurios q estan buscando algo asi
        foreach ($matchingAlerts as $alert) {
            // Validamos que el usuario tenga un telegram_id antes de intentar enviar
            if ($alert->user && $alert->user->telegram_id) {
                /*
                Telegram::sendMessage([
                    'chat_id' => $alert->user->telegram_id,
                    'text' => "🚀 Nueva oferta detectada: {$offer->amount} USD vía {$offer->payment_method}"
                ]);
                */

                // Enviar notificación directamente (evita usar variables indefinidas en closure)
                $communityChat = config('metadata.system.app.zentrotraderbot.telegram.community.group');
                $botToken = config('metadata.system.app.zentrotraderbot.telegram.bot_token') ?? null;

                $text = "🚀 Nueva oferta detectada: {$offer->amount} USD vía {$offer->payment_method}";
                $payload = [
                    'message' => [
                        'chat' => ['id' => $communityChat],
                        'text' => $text,
                    ],
                ];

                // Intentamos enviar si tenemos token configurado
                if ($botToken) {
                    TelegramController::sendMessage($payload, $botToken);
                } else {
                    // Fallback: intentar enviar al usuario directamente si no hay token global
                    $userChat = $alert->user->telegram_id;
                    if ($userChat) {
                        $personalPayload = [
                            'message' => [
                                'chat' => ['id' => $userChat],
                                'text' => $text,
                            ],
                        ];
                        TelegramController::sendMessage($personalPayload, $botToken);
                    }
                }


            }
        }
    }
}