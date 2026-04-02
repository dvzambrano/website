<?php

namespace Modules\ZentroTraderBot\Observers;

use Modules\ZentroTraderBot\Entities\Offers;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Http\Controllers\BlockchainController;
use Modules\Laravel\Services\DateService;
use Carbon\Carbon;
use Modules\ZentroTraderBot\Jobs\UpdateOfferInChannel;
use Illuminate\Support\Facades\Lang;
use Modules\TelegramBot\Entities\Actors;
use Modules\TelegramBot\Http\Controllers\ActorsController;

class OfferObserver
{
    /**
     * Manejar el evento "created" de la Oferta.
     */
    public function created(Offers $offer): void
    {
        $bot = app('active_bot');

        UpdateOfferInChannel::dispatch($bot->key, $offer->code);
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

        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();
        $diff = DateService::getTimeDifference(Carbon::now()->getTimestamp(), Carbon::now()->addSeconds($status["tradeTimeout"])->getTimestamp());

        switch (strtoupper($newStatus)) {

            case 'LOCKED':
                $amount = number_format($offer->amount, 2);
                $price = number_format($offer->amount * $offer->price_per_usd, 2);
                $text = "🛡 *¡Intercambio asegurado!*\n" .
                    "🆔 `{$offer->code}`\n" .
                    "🔒 Se han bloquedado *{$amount} USD* para Ud\n" .
                    "🟢 *Ahora es seguro proceder:*\n\n" .
                    "💳 Realice el pago de {$price} {$offer->currency} a:\n" .
                    "🏦 `{$offer->payment_details}`\n" .
                    "👉 y luego, entregue su comprobante para verificación.\n\n" .
                    "⏱️ *Tiene un margen de " . $diff["legible"] . " para completar su pago.*\n" .
                    "_Luego de ese tiempo los USD estarán disponibles para que el vendedor los recupere._";
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token,
                    [
                        [
                            ["text" => "🧾 Enviar Comprobante de Pago", "callback_data" => "menu"]
                        ],
                        [
                            ["text" => "❌ " . Lang::get("telegrambot::bot.options.cancel"), "callback_data" => "adminmenu"]
                        ]
                    ]
                );

                $text = "🛡 *¡Intercambio asegurado!*\n" .
                    "🆔 `{$offer->code}`\n" .
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


                break;

            case 'COMPLETED':
                $isDispute = !empty($offer->winner_address);
                // 1. Mensaje para el Vendedor (Seller)
                if ($isDispute) {
                    $msgSeller = "✅ *TRANSACCIÓN COMPLETADA* \n" .
                        "🆔 `{$offer->code}`\n" .
                        "⚖️ _La transacción ha sido finalizada tras el arbitraje._\n" .
                        "📦 *Estado final:* Fondos procesados.";
                } else {
                    $msgSeller = "🎉 *¡FELICIDADES: transacción completada!* \n" .
                        "🆔 `{$offer->code}`\n" .
                        "✅ El intercambio se realizó con éxito.\n" .
                        "💵 _Se han descontado {$amount} USD de su cuenta._";
                }

                // 2. Mensaje para el Comprador (Buyer)
                if ($isDispute) {
                    $msgBuyer = "✅ *TRANSACCIÓN COMPLETADA* \n" .
                        "🆔 `{$offer->code}`\n" .
                        "⚖️ _La transacción ha sido finalizada tras el arbitraje._\n" .
                        "💰 *Estado final:* Saldo actualizado.";
                } else {
                    $msgBuyer = "🎉 *¡FELICIDADES: transacción completada!* \n" .
                        "🆔 `{$offer->code}`\n" .
                        "✅ El intercambio se realizó con éxito.\n" .
                        "💵 _Se han liberado {$amount} USD a su cuenta._";
                }

                // 3. Notificaciones finales
                $this->notifyByAddress(
                    $offer->seller_address,
                    $msgSeller,
                    $bot->token,
                    [
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                    ]
                );
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $msgBuyer,
                    $bot->token,
                    [
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                    ]
                );

                break;

            case 'DISPUTED':
                // Se abrió una disputa (DisputeOpened)
                $generaltext = "🙇🏻 *¡Transacción en DISPUTA!* \n" .
                    "🆔 `{$offer->code}`\n" .
                    "👉 _Se ha iniciado una reclamación de esta operación._\n";
                $text = $generaltext .
                    "👮‍♀️ *Un administrador revisará el caso pronto*.\n\n" .
                    "⚠️ *Envíe evidencia* de que cumplió con su parte del acuerdo.";
                $this->notifyByAddress(
                    $offer->seller_address,
                    $text,
                    $bot->token,
                    [
                        [
                            ["text" => "🧾 Enviar evidencias del intercambio", "callback_data" => "menu"],
                        ]
                    ]
                );
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token,
                    [
                        [
                            ["text" => "🧾 Enviar evidencias del intercambio", "callback_data" => "menu"],
                        ]
                    ]
                );

                // Notificar a los administradores
                $controller = new ActorsController();
                $admins = $controller->getData(Actors::class, [
                    [
                        "contain" => true,
                        "name" => "admin_level",
                        "value" => [1, "1"],
                    ],
                ], $bot->code);
                for ($i = 0; $i < count($admins); $i++) {
                    TelegramController::sendMessage(array(
                        "message" => array(
                            "text" => $generaltext,
                            "chat" => array(
                                "id" => $admins[$i]->user_id,
                            ),
                            "reply_markup" => json_encode([
                                "inline_keyboard" => [
                                    [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                                ],
                            ]),
                        ),
                    ), $bot->token);
                }
                break;

            case 'CANCELLED':
                $text = "❌ *Oferta Cancelada!* \n" .
                    "🆔 `{$offer->code}`\n\n" .
                    "👉 _La Oferta ha sido cancelada por el comprador._\n" .
                    "💵 Se han *devuelto *{$amount} USD* a su cuenta.";
                $this->notifyByAddress(
                    $offer->seller_address,
                    $text,
                    $bot->token,
                    [
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                    ]
                );
                break;

            case 'SIGNED':
                // 1. Identificamos quién es el que falta por firmar
                $json = $offer->data;

                $signer = $offer->buyer_address;
                $pending = $offer->seller_address;
                $menu = [
                    [["text" => "👍 He recibido el Pago", "callback_data" => "menu"]]
                ];
                if (strtolower($json["signer"]) == strtolower($offer->seller_address)) {
                    $signer = $offer->seller_address;
                    $pending = $offer->buyer_address;
                    $menu = [
                        [
                            ["text" => "🧾 Enviar Comprobante de Pago", "callback_data" => "menu"],
                        ]
                    ];
                }

                $text = "⚠️ *¡Firma Pendiente!* \n" .
                    "🆔 `{$offer->code}`\n" .
                    "☑️ La contraparte ya ha firmado y depositado su confianza en esta transacción.\n\n" .
                    "✍️ *Proceda a firmar*; evite que entre en disputa o haya retrasos.\n" .
                    "⏳ _Estamos esperando por Ud..._";
                $this->notifyByAddress(
                    $pending,
                    $text,
                    $bot->token,
                    $menu
                );
                break;

            case 'SOLVED':
                // mandar mensaje a winner felicitando y a perdedor informando
                $winner = $offer->buyer_address;
                $looser = $offer->seller_address;
                if (strtolower($offer->winner_address) == strtolower($offer->seller_address)) {
                    $winner = $offer->seller_address;
                    $looser = $offer->buyer_address;
                }
                $text = "👩‍💻 *¡Transacción REVISADA!*\n" .
                    "🆔 `{$offer->code}`\n" .
                    "⚖️ _Un adminstrador ha revisado las evidencias presentadas._\n";
                $this->notifyByAddress(
                    $winner,
                    $text .
                    "🏆 *La disputa ha finalizado a su favor.*\n\n" .
                    "💵 _Se han liberado {$amount} USD a su cuenta._\n" .
                    "🙏 _¡Gracias por confiar en nosotros!_\n",
                    $bot->token,
                    [
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                    ]
                );
                $this->notifyByAddress(
                    $looser,
                    $text .
                    "🛑 *Le informamos que el arbitraje ha concluido a favor de la contraparte*.\n\n" .
                    "🤝 _Si tiene dudas, contacte a soporte con el ID único de la transacción._\n" .
                    "🙏 _¡Gracias por confiar en nosotros!_\n",
                    $bot->token,
                    [
                        [["text" => "👩‍💻 Hablar con un Árbitro", "callback_data" => "menu"]],
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                    ]
                );
                // cambiar estado a COMPLETED
                $offer->updateStatus('COMPLETED', [
                    'updated_at' => now()
                ]);
                break;

            case 'EXPIRED':
                // mandar mensaje al comprador de q se le ha vencido el tiempo y entramos en disputa
                $text = "⏱️ *¡Transacción en EXPIRADA!* \n" .
                    "🆔 `{$offer->code}`\n" .
                    "👉 _El vendedor ha informado que esta operación no fue pagada en " . $diff["legible"] . "._\n" .
                    "🚨 *Se abrirá una DISPUTA automáticamente*.\n\n" .
                    "🔒 _Los fondos estarán congelados hasta que la administración revise el caso._";
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token,
                    [
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                    ]
                );
                // cambiar estado a DISPUTED
                $offer->updateStatus('DISPUTED', [
                    'updated_at' => now()
                ]);
                break;
        }

        UpdateOfferInChannel::dispatch($bot->key, $offer->code, $offer->updated_at->getTimestamp());
    }

    /**
     * Helper para enviar mensajes directos vía TelegramController
     */
    private function notifyUser($telegramId, $text, $token, $menu = [])
    {
        if (!$telegramId || !$token)
            return;

        $payload = [
            'message' => [
                'chat' => ['id' => $telegramId],
                'text' => $text,

            ],
        ];
        if (count($menu) > 0)
            $payload["message"]["reply_markup"] = json_encode([
                "inline_keyboard" => $menu,
            ]);
        TelegramController::sendMessage($payload, $token);
    }

    /**
     * Helper para notificar buscando al usuario por su wallet address
     */
    private function notifyByAddress($address, $text, $token, $menu = [])
    {
        $suscriptor = Suscriptions::on('tenant')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, "$.wallet.address"))) = ?', [$address])
            ->first();
        if ($suscriptor && $suscriptor->user_id) {
            $this->notifyUser($suscriptor->user_id, $text, $token, $menu);
        }
    }
}