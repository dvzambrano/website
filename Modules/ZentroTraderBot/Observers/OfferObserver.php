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
use Modules\TelegramBot\Jobs\DeleteTelegramMessage;
use Modules\ZentroTraderBot\Jobs\SendRecoverReminder;

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
        if (strtolower($offer->status) === strtolower($offer->getOriginal('status'))) {
            return;
        }

        $bot = app('active_bot');

        $newStatus = $offer->status;
        $amount = number_format($offer->amount, 2);

        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();
        $diff = DateService::getTimeDifference(Carbon::now()->getTimestamp(), Carbon::now()->addSeconds($status["tradeTimeout"])->getTimestamp());
        $result = $offer->getNetProceeds($status);
        $net = $result['net'];

        switch (strtoupper($newStatus)) {

            case 'LOCKED':
                $amount = number_format($offer->amount, 2);
                $price = number_format($offer->amount * $offer->price_per_usd, 2);

                // Mensaje al COMPRADOR
                $text = "🛡 *" . Lang::get("zentrotraderbot::bot.offer.locked.title") . "*\n"
                    . "🆔 `{$offer->code}`\n"
                    . "🔒 " . Lang::get("zentrotraderbot::bot.offer.locked.buyer.funds_blocked", ['amount' => $amount]) . "\n"
                    . "💵 _" . Lang::get("zentrotraderbot::bot.offer.locked.buyer.you_receive", ['net' => $net]) . "_\n\n"
                    . "🟢 *" . Lang::get("zentrotraderbot::bot.offer.locked.buyer.proceed") . "*\n"
                    . "💳 " . Lang::get("zentrotraderbot::bot.offer.locked.buyer.make_payment", ['price' => $price, 'currency' => $offer->currency]) . "\n"
                    . "🏦 {$offer->payment_method}: `{$offer->payment_details}`\n"
                    . "👉 " . Lang::get("zentrotraderbot::bot.offer.locked.buyer.then_proof") . "\n\n"
                    . "⏱️ *" . Lang::get("zentrotraderbot::bot.offer.locked.buyer.time_margin", ['time' => $diff["legible"]]) . "*\n"
                    . "_" . Lang::get("zentrotraderbot::bot.offer.locked.buyer.after_timeout") . "_";

                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token,
                    [
                        [["text" => "🧾 " . Lang::get("zentrotraderbot::bot.options.send_proof"), "callback_data" => "/comprobantoffer " . $offer->code]],
                        [["text" => "❌ " . Lang::get("zentrotraderbot::bot.options.cancel"), "callback_data" => "/canceloffer " . $offer->code]],
                    ],
                    $offer
                );

                // Mensaje al VENDEDOR — sin botón de reclamar; se envía via job cuando expire el plazo
                $text = "🛡 *" . Lang::get("zentrotraderbot::bot.offer.locked.title") . "*\n"
                    . "🆔 `{$offer->code}`\n"
                    . "🔒 " . Lang::get("zentrotraderbot::bot.offer.locked.seller.funds_blocked", ['amount' => $amount]) . "\n\n"
                    . "💳 _" . Lang::get("zentrotraderbot::bot.offer.locked.seller.buyer_will_pay", ['price' => $price, 'currency' => $offer->currency]) . "_\n"
                    . "🏦 _{$offer->payment_method}: {$offer->payment_details}_\n"
                    . "📋 _" . Lang::get("zentrotraderbot::bot.offer.locked.seller.then_proof") . "_\n\n"
                    . "🚨 *" . Lang::get("zentrotraderbot::bot.offer.locked.seller.never_confirm", ['price' => $price, 'currency' => $offer->currency]) . "*";

                $this->notifyByAddress($offer->seller_address, $text, $bot->token, [], $offer);

                // Despachar recordatorio con el botón de reclamar para cuando expire el plazo
                $sellerSub = Suscriptions::findByAddress($offer->seller_address);
                if ($sellerSub && $sellerSub->user_id) {
                    SendRecoverReminder::dispatch($bot->key, $offer->code, (int) $sellerSub->user_id)
                        ->delay(now()->addSeconds($status["tradeTimeout"]));
                }
                break;

            case 'COMPLETED':
                $isDispute = !empty($offer->winner_address);

                // Mensaje al VENDEDOR
                if ($isDispute) {
                    $msgSeller = "✅ *" . Lang::get("zentrotraderbot::bot.offer.completed.title_dispute") . "*\n"
                        . "🆔 `{$offer->code}`\n"
                        . "⚖️ _" . Lang::get("zentrotraderbot::bot.offer.completed.finalized_by_arbitrage") . "_\n"
                        . "📦 *" . Lang::get("zentrotraderbot::bot.offer.completed.seller_dispute_status") . "*";
                } else {
                    $msgSeller = "🎉 *" . Lang::get("zentrotraderbot::bot.offer.completed.title") . "*\n"
                        . "🆔 `{$offer->code}`\n"
                        . "✅ " . Lang::get("zentrotraderbot::bot.offer.completed.success") . "\n"
                        . "💵 _" . Lang::get("zentrotraderbot::bot.offer.completed.deducted_from_seller", ['amount' => $amount]) . "_";
                }

                // Mensaje al COMPRADOR
                if ($isDispute) {
                    $msgBuyer = "✅ *" . Lang::get("zentrotraderbot::bot.offer.completed.title_dispute") . "*\n"
                        . "🆔 `{$offer->code}`\n"
                        . "⚖️ _" . Lang::get("zentrotraderbot::bot.offer.completed.finalized_by_arbitrage") . "_\n"
                        . "💰 *" . Lang::get("zentrotraderbot::bot.offer.completed.buyer_dispute_status") . "*";
                } else {
                    $msgBuyer = "🎉 *" . Lang::get("zentrotraderbot::bot.offer.completed.title") . "*\n"
                        . "🆔 `{$offer->code}`\n"
                        . "✅ " . Lang::get("zentrotraderbot::bot.offer.completed.success") . "\n"
                        . "💵 _" . Lang::get("zentrotraderbot::bot.offer.completed.released_to_buyer", ['net' => $net]) . "_";
                }

                $msgeval = "🙏 " . Lang::get("zentrotraderbot::bot.offer.completed.rate_invite") . "\n"
                    . "👇 " . Lang::get("zentrotraderbot::bot.offer.completed.rate_instruction");
                $msgSeller .= "\n\n{$msgeval}";
                $msgBuyer  .= "\n\n{$msgeval}";

                $evalMenu = [
                    ['text' => '😡', 'callback_data' => "/rateoffer {$offer->code} 1"],
                    ['text' => '😟', 'callback_data' => "/rateoffer {$offer->code} 2"],
                    ['text' => '😐', 'callback_data' => "/rateoffer {$offer->code} 3"],
                    ['text' => '🙂', 'callback_data' => "/rateoffer {$offer->code} 4"],
                    ['text' => '🤩', 'callback_data' => "/rateoffer {$offer->code} 5"],
                ];

                $this->notifyByAddress(
                    $offer->seller_address,
                    $msgSeller,
                    $bot->token,
                    [$evalMenu, [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]],
                    $offer
                );
                $this->notifyByAddress(
                    $offer->buyer_address,
                    $msgBuyer,
                    $bot->token,
                    [$evalMenu, [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]],
                    $offer
                );
                break;

            case 'DISPUTED':
                $generaltext = "🙇🏻 *" . Lang::get("zentrotraderbot::bot.offer.disputed.title") . "*\n"
                    . "🆔 `{$offer->code}`\n"
                    . "👉 _" . Lang::get("zentrotraderbot::bot.offer.disputed.claim_started") . "_\n";

                $text = $generaltext
                    . "👮‍♀️ *" . Lang::get("zentrotraderbot::bot.offer.disputed.arbiter_will_review") . "*\n\n"
                    . "⚠️ *" . Lang::get("zentrotraderbot::bot.offer.disputed.send_evidence_note") . "*";

                $evidenceMenu = [[["text" => "🧾 " . Lang::get("zentrotraderbot::bot.options.send_evidence"), "callback_data" => "/evidenceoffer " . $offer->code]]];

                $this->notifyByAddress($offer->seller_address, $text, $bot->token, $evidenceMenu, $offer);
                $this->notifyByAddress($offer->buyer_address,  $text, $bot->token, $evidenceMenu, $offer);

                // Notificar a los administradores
                $controller = new ActorsController();
                $admins = $controller->getData(Actors::class, [
                    ["contain" => true, "name" => "admin_level", "value" => [1, "1"]],
                ], $bot->code);
                foreach ($admins as $admin) {
                    TelegramController::sendMessage([
                        "message" => [
                            "text" => $generaltext,
                            "chat" => ["id" => $admin->user_id],
                            "reply_markup" => json_encode([
                                "inline_keyboard" => [
                                    [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                                ],
                            ]),
                        ],
                    ], $bot->token);
                }
                break;

            case 'CANCELLED':
                $text = "❌ *" . Lang::get("zentrotraderbot::bot.offer.cancelled.title") . "*\n"
                    . "🆔 `{$offer->code}`\n\n"
                    . "👉 _" . Lang::get("zentrotraderbot::bot.offer.cancelled.cancelled_by_buyer") . "_\n"
                    . "💵 " . Lang::get("zentrotraderbot::bot.offer.cancelled.funds_returned", ['amount' => $amount]);

                $this->notifyByAddress(
                    $offer->seller_address,
                    $text,
                    $bot->token,
                    [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]],
                    $offer
                );
                break;

            case 'SIGNED':
                $json   = $offer->data;
                $signer = strtolower($json['signer'] ?? '');

                // Si quien firmó fue el COMPRADOR, el vendedor ya recibió el botón de confirmar
                // en el pass unconfirmed de TRADESIGNED. No duplicamos el mensaje.
                if ($signer !== strtolower($offer->seller_address)) {
                    break;
                }

                // Si quien firmó fue el VENDEDOR, el comprador aún necesita enviar su comprobante
                $text = "⚠️ *" . Lang::get("zentrotraderbot::bot.offer.signed.pending_title") . "*\n"
                    . "🆔 `{$offer->code}`\n"
                    . "👍 " . Lang::get("zentrotraderbot::bot.offer.signed.counterpart_confirmed") . "\n\n"
                    . "☑️ *" . Lang::get("zentrotraderbot::bot.offer.signed.proceed_confirm") . "*\n"
                    . "⏳ _" . Lang::get("zentrotraderbot::bot.offer.signed.waiting") . "_";
                $menu = [[["text" => "🧾 " . Lang::get("zentrotraderbot::bot.options.send_proof"), "callback_data" => "/comprobantoffer {$offer->code}"]]];
                $this->notifyByAddress($offer->buyer_address, $text, $bot->token, $menu, $offer);
                break;

            case 'SOLVED':
                $winner = $offer->buyer_address;
                $looser = $offer->seller_address;
                if (strtolower($offer->winner_address) == strtolower($offer->seller_address)) {
                    $winner = $offer->seller_address;
                    $looser = $offer->buyer_address;
                }

                $textBase = "👩‍💻 *" . Lang::get("zentrotraderbot::bot.offer.solved.title") . "*\n"
                    . "🆔 `{$offer->code}`\n"
                    . "⚖️ _" . Lang::get("zentrotraderbot::bot.offer.solved.admin_reviewed") . "_\n";

                $this->notifyByAddress(
                    $winner,
                    $textBase
                        . "🏆 *" . Lang::get("zentrotraderbot::bot.offer.solved.winner") . "*\n\n"
                        . "💵 _" . Lang::get("zentrotraderbot::bot.offer.solved.funds_released", ['amount' => $amount]) . "_\n"
                        . "🙏 _" . Lang::get("zentrotraderbot::bot.offer.solved.thanks") . "_\n",
                    $bot->token,
                    [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]],
                    $offer
                );
                $this->notifyByAddress(
                    $looser,
                    $textBase
                        . "🛑 *" . Lang::get("zentrotraderbot::bot.offer.solved.loser") . "*\n\n"
                        . "🤝 _" . Lang::get("zentrotraderbot::bot.offer.solved.contact_support") . "_\n"
                        . "🙏 _" . Lang::get("zentrotraderbot::bot.offer.solved.thanks") . "_\n",
                    $bot->token,
                    [
                        [["text" => "👩‍💻 " . Lang::get("zentrotraderbot::bot.options.talk_arbiter"), "callback_data" => "menu"]],
                        [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]],
                    ],
                    $offer
                );
                $offer->updateStatus('COMPLETED', ['updated_at' => now()]);
                break;

            case 'EXPIRED':
                $text = "⏱️ *" . Lang::get("zentrotraderbot::bot.offer.expired.title") . "*\n"
                    . "🆔 `{$offer->code}`\n"
                    . "👉 _" . Lang::get("zentrotraderbot::bot.offer.expired.seller_reported", ['time' => $diff["legible"]]) . "_\n"
                    . "🚨 *" . Lang::get("zentrotraderbot::bot.offer.expired.auto_dispute") . "*\n\n"
                    . "🔒 _" . Lang::get("zentrotraderbot::bot.offer.expired.funds_frozen") . "_";

                $this->notifyByAddress(
                    $offer->buyer_address,
                    $text,
                    $bot->token,
                    [[["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]],
                    $offer
                );
                $offer->updateStatus('DISPUTED', ['updated_at' => now()]);
                break;
        }

        UpdateOfferInChannel::dispatch($bot->key, $offer->code, $offer->updated_at->getTimestamp());
    }

    /**
     * Helper para enviar mensajes directos vía TelegramController.
     * Elimina el mensaje de estado anterior del usuario (si existe) antes de enviar el nuevo,
     * y almacena el message_id resultante en offer->data['last_status_messages'].
     */
    private function notifyUser($telegramId, $text, $token, $menu = [], ?Offers $offer = null): void
    {
        if (!$telegramId || !$token)
            return;

        // Eliminar el mensaje de estado anterior para este usuario
        if ($offer) {
            $prevMsgId = $offer->data['last_status_messages'][$telegramId] ?? null;
            if ($prevMsgId && (int) $prevMsgId > 0) {
                DeleteTelegramMessage::dispatch($token, (int) $telegramId, (int) $prevMsgId);
            }
        }

        $payload = [
            'message' => [
                'chat' => ['id' => $telegramId],
                'text' => $text,
            ],
        ];
        if (count($menu) > 0)
            $payload["message"]["reply_markup"] = json_encode(["inline_keyboard" => $menu]);

        $response = TelegramController::sendMessage($payload, $token);

        // Guardar el message_id del mensaje enviado para poder eliminarlo en el siguiente estado.
        // Se usa saveQuietly() para evitar re-disparar el Observer mientras syncOriginal() aún
        // no ha sido invocado por el save() padre (evita recursión y spam de mensajes).
        if ($offer) {
            $arr = json_decode($response, true);
            $msgId = $arr['result']['message_id'] ?? null;
            if ($msgId && (int) $msgId > 0) {
                $data = $offer->data ?? [];
                $data['last_status_messages'][$telegramId] = (int) $msgId;
                $offer->data = $data;
                $offer->saveQuietly();
            }
        }
    }

    /**
     * Helper para notificar buscando al usuario por su wallet address
     */
    private function notifyByAddress($address, $text, $token, $menu = [], ?Offers $offer = null): void
    {
        $suscriptor = Suscriptions::findByAddress($address);
        if ($suscriptor && $suscriptor->user_id) {
            $this->notifyUser($suscriptor->user_id, $text, $token, $menu, $offer);
        }
    }
}
