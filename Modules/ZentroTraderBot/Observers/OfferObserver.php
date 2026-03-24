<?php

namespace Modules\ZentroTraderBot\Observers;

use Modules\ZentroTraderBot\Entities\Offers;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Http\Controllers\BlockchainController;
use Modules\Laravel\Http\Controllers\MathController;
use Carbon\Carbon;

class OfferObserver
{
    /**
     * Manejar el evento "created" de la Oferta.
     */
    public function created(Offers $offer): void
    {
        $bot = app('active_bot');

        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();


        $diff = MathController::getTimeDifference(Carbon::now()->getTimestamp(), Carbon::now()->addSeconds($status["tradeTimeout"])->getTimestamp());
        //

        $amount = number_format($offer->amount, 2);
        $price = number_format($offer->amount * $offer->price_per_usd, 2);
        $text = "🛡 *¡Intercambio asegurado!*\n" .
            "🆔 `{$offer->uuid}`\n" .
            "🔒 Se han bloquedado *{$amount} USD* para Ud\n" .
            "🟢 _Ahora es seguro proceder:_\n\n" .
            "💳 _Realice el pago de {$price} {$offer->currency} a:_\n" .
            "🏦 `{$offer->payment_details}`\n" .
            "👉 _y luego, entregue su comprobante para verificación._\n\n" .
            "⏱️ *Tiene un margen de " . $diff["legible"] . " para completar su pago.* Luego de ese tiempo los {$amount} USD estarán disponibles para que el vendedor los recupere.";
        $this->notifyByAddress(
            $offer->buyer_address,
            $text,
            $bot->token
        );

        $text = "🛡 *¡Intercambio asegurado!*\n" .
            "🆔 `{$offer->uuid}`\n" .
            "🔒 Se han bloquedado *{$amount} USD* de su cuenta\n\n" .
            "💳 _El comprador realizará el pago de {$price} {$offer->currency} a:_\n" .
            "🏦 _{$offer->payment_details}_\n" .
            "📋 _Y luego, enviará su comprobante para verificación._\n\n" .
            "🚨 *Nunca libere los fondos sin comprobar el recibo de los {$price} {$offer->currency} en su cuenta*";
        $this->notifyByAddress(
            $offer->seller_address,
            $text,
            $bot->token
        );
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
        $amount = number_format($offer->amount, 2);

        switch (strtoupper($newStatus)) {
            case 'COMPLETED':
                $text = "🎉 *¡FELICIDADES: transacción completada!* \n" .
                    "🆔 `{$offer->uuid}`\n" .
                    "✅ Ambas partes han confirmado el intercambio satisfactorio.\n\n";
                $this->notifyByAddress(
                    $offer->seller_address,
                    $text .
                    "💵 _Se han descontado {$amount} USD de su cuenta._",
                    $bot->token
                );
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text .
                    "💵 _Se han liberado {$amount} USD a su cuenta._",
                    $bot->token
                );
                break;

            case 'DISPUTED':
                // Se abrió una disputa (DisputeOpened)
                $text = "🙇🏻 *¡Transacción en DISPUTA!* \n" .
                    "🆔 `{$offer->uuid}`\n" .
                    "👉 _Se ha iniciado una reclamación de esta operación._\n" .
                    "👮‍♀️ *Un administrador revisará el caso pronto*.\n\n" .
                    "⚠️ *Tenga a mano evidencia* de que cumplió con su parte del acuerdo.";
                $this->notifyByAddress(
                    $offer->seller_address,
                    $text,
                    $bot->token
                );
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token
                );
                break;

            case 'CANCELLED':
                $text = "❌ *Oferta Cancelada!* \n" .
                    "🆔 `{$offer->uuid}`\n\n" .
                    "👉 _La Oferta ha sido cancelada por el comprador._\n" .
                    "💵 Se han *devuelto *{$amount} USD* a su cuenta.";
                $this->notifyByAddress(
                    $offer->seller_address,
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