<?php

namespace Modules\ZentroTraderBot\Observers;

use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\OffersAlerts;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Entities\Suscriptions;

class OfferObserver
{
    /**
     * Manejar el evento "created" de la Oferta.
     */
    public function created(Offers $offer): void
    {
        $bot = app('active_bot');

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

                $text = "🚀 Nueva oferta detectada: {$offer->amount} USD vía {$offer->payment_method}";
                $payload = [
                    'message' => [
                        'chat' => ['id' => $communityChat],
                        'text' => $text,
                    ],
                ];

                // Intentamos enviar si tenemos token configurado
                if ($bot->token) {
                    TelegramController::sendMessage($payload, $bot->token);
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
                        TelegramController::sendMessage($personalPayload, $bot->token);
                    }
                }


            }
        }
    }

    /**
     * Manejar el evento "updated" de la Oferta.
     */
    public function updated(Offers $offer): void
    {
        // Solo actuamos si el status ha cambiado
        if (!$offer->isDirty('status')) {
            return;
        }

        $bot = app('active_bot');

        $newStatus = $offer->status;
        $oldStatus = $offer->getOriginal('status');

        // Identificamos a los interesados (User es el creador, Buyer es quien aplicó)
        $ownerTelegramId = $offer->user ? $offer->user->telegram_id : null;

        switch (strtoupper($newStatus)) {
            case 'LOCKED':
                // Alguien aplicó al Escrow (TradeApplied)
                $amount = $offer->amount * $offer->price_per_usd;
                $text = "🔑 *¡Intercambio asegurado!*\n" .
                    "🔒 Se han bloquedado *{$offer->amount} USD* de la Oferta `{$offer->blockchain_trade_id}.`\n" .
                    "🟢 _En este momento es seguro para Ud proceder con el intercambio FIAT._\n\n" .
                    "👉 Realice el pago de {$amount} {$offer->currency} y entregue su comprobante para verificación.";
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token
                );


                $text = "🔑 *¡Intercambio asegurado!*\n" .
                    "🔒 Se han bloquedado *{$offer->amount} USD* de su cuenta para cumplir con la Oferta `{$offer->blockchain_trade_id}.`\n" .
                    "👉 Se ha instruido a comprador para que realice el pago de {$amount} {$offer->currency} y entregue su comprobante para verificación.";
                $this->notifyByAddress(
                    $offer->seller_address,
                    $text,
                    $bot->token
                );
                break;

            case 'COMPLETED':
                $text = "✅ *¡Transacción Completada!* \n" .
                    "👉 La Oferta `{$offer->blockchain_trade_id}` ha terminado.\n" .
                    "💵 Se han *liberado {$offer->amount} USD* a la cuenta del comprador.";
                $this->notifyByAddress(
                    $offer->seller_address,
                    $text,
                    $bot->token
                );

                $text = "✅ *¡Transacción Completada!* \n" .
                    "👉 La Oferta `{$offer->blockchain_trade_id}` ha terminado.\n" .
                    "💵 Se han *liberado {$offer->amount} USD* a su cuenta.";
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token
                );
                break;

            case 'DISPUTED':
                // Se abrió una disputa (DisputeOpened)
                $text = "⚠️ ATENCIÓN: Se ha abierto una DISPUTA en tu trade #{$offer->blockchain_trade_id}.\n" .
                    "Un administrador revisará el caso pronto.";
                $this->notifyUser($ownerTelegramId, $text, $bot->token);
                break;

            case 'CANCELLED':
                $text = "❌ *Oferta Cancelada!* \n" .
                    "👉 La Oferta `{$offer->blockchain_trade_id}` ha sido cancelada satisfactoriamente.\n" .
                    "💵 Se han *devuelto *{$offer->amount} USD* a su cuenta.";
                $this->notifyByAddress(
                    $offer->seller_address,
                    $text,
                    $bot->token
                );

                $text = "❌ *Oferta Cancelada!* \n" .
                    "👉 La Oferta `{$offer->blockchain_trade_id}` ha sido cancelada por el vendedor.\n" .
                    "🟢 _Hemos permitido la cancelación porque no se había efectuado ningún pago aún_";
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token
                );
                break;
        }
    }

    /**
     * Helper para enviar mensajes directos vía TelegramController
     */
    private function notifyUser($telegramId, $text, $token)
    {
        if (!$telegramId || !$token)
            return;

        $payload = [
            'message' => [
                'chat' => ['id' => $telegramId],
                'text' => $text,
            ],
        ];
        TelegramController::sendMessage($payload, $token);
    }

    /**
     * Helper para notificar buscando al usuario por su wallet address
     */
    private function notifyByAddress($address, $text, $token)
    {
        $suscriptor = Suscriptions::on('tenant')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, "$.wallet.address"))) = ?', [$address])
            ->first();
        if ($suscriptor && $suscriptor->user_id) {
            $this->notifyUser($suscriptor->user_id, $text, $token);
        }
    }
}