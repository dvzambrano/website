<?php

namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\ContractActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\Laravel\Services\BehaviorService;
use Modules\TelegramBot\Jobs\DeleteTelegramMessage;

class ProcessContractActivity
{
    /**
     * Procesa eventos del contrato Escrow con doble pass: unconfirmed + confirmed.
     *
     * Unconfirmed pass:
     *   - Registra el evento en offer->data['blockchain_events'] (pending_at).
     *   - Realiza limpieza de UX (borra mensajes de Telegram obsoletos).
     *   - Envía una notificación breve "⌛️ Procesando..." a las partes implicadas.
     *   - NO cambia offer->status (evita brechas de seguridad ante reorgs).
     *
     * Confirmed pass:
     *   - Registra confirmed_at en offer->data['blockchain_events'].
     *   - Cambia offer->status → dispara el OfferObserver con el mensaje completo.
     *
     * @param ContractActivityDetected $event DTO normalizado con estructura:
     *   network_id, confirmed, tx_hash, contract, decoded{name, params}, tenant_code, trace_id
     */
    public function handle(ContractActivityDetected $event): void
    {
        $data = $event->data;

        if (env("DEBUG_MODE", false))
            Log::debug("🐞 ProcessContractActivity handle:", [
                "id" => $data['trace_id'],
                "confirmed" => $data['confirmed'],
                "event_name" => $data['decoded']['name'] ?? 'Unknown',
                "data" => $data,
            ]);

        /*
        {
            "network_id": 80002,
            "confirmed": true,
            "block_number": "35278403",
            "timestamp": "1773684339",
            "tenant_code": "59d5e7a3-dea0-4289-88f0-a39765f50bcf",
            "listener": "moralis",
            "type": "contract_log",
            "tx_hash": "0x4292f181957c0bc1d3adf640d785f8c79594a9c4c65c85a6591b6eb698d92a5b",
            "contract": "0xc15e5d5173966380fc2b297a59ed89019e4fea12",
            "topic0": "0x71cb61698242bdadd31b3db5d7d28894a712a6b311e7cc08164207ed15e0d055",
            "from_address": "0x3e254e81106e19b4c961cbc800390aed2a8fe800",
            "decoded": {
                "name": "TradeCreated",
                "params": {
                    "tradeId": "30",
                    "seller": "0x3e254e81106e19b4c961cbc800390aed2a8fe800"
                }
            },
            "trace_id": "4f3e9b62-3020-4cd5-95d5-731bbb05af4f"
        }
        */

        // Ignorar eventos que no son de nuestro contrato Escrow
        if (strtolower($data['contract']) !== strtolower(env('ESCROW_CONTRACT')))
            return;

        $eventName = strtoupper($data['decoded']['name']);
        $isConfirmed = $data['confirmed'] ?? false;

        // Idempotencia: cada (eventName, tx_hash, confirmed/unconfirmed) se procesa exactamente una vez
        $statusSuffix = $isConfirmed ? 'confirmed' : 'unconfirmed';
        $cacheKey = "escrow_ev_proc_{$eventName}_{$data['tx_hash']}_{$statusSuffix}";
        if (!Cache::add($cacheKey, true, now()->addDays(2))) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity escaped by idempotency ({$statusSuffix}):", ['key' => $cacheKey]);
            return;
        }

        // Identificar bot/tenant
        $bot = BehaviorService::cache('tenant_' . $data['tenant_code'], function () use ($data) {
            return TelegramBots::where('key', $data['tenant_code'])->first();
        });
        if (!$bot) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity escaped by !bot:", ['tenant_code' => $data['tenant_code']]);
            return;
        }
        $bot->connectToThisTenant();

        $params = $data['decoded']['params'];
        if (!isset($params['tradeId']))
            return;

        try {
            $offer = Offers::on('tenant')->where('id', $params['tradeId'])->first();
            if (!$offer) {
                if (env("DEBUG_MODE", false))
                    Log::debug("🐞 ProcessContractActivity escaped by !offer:", ['tradeId' => $params['tradeId']]);
                return;
            }

            // 1. Registrar estado del evento en la oferta (no cambia status, Observer no dispara)
            $offer->recordBlockchainEvent($eventName, $data['tx_hash'], $isConfirmed);

            // 2. Limpieza de UX — segura en cualquier pass, pero solo ejecutamos en unconfirmed
            //    para no reintentar borrar un mensaje ya eliminado
            if (!$isConfirmed)
                $this->handleUxCleanup($offer, $eventName, $bot, $params);

            // 3. Pass UNCONFIRMED: notificación instantánea + retornar (no tocar status)
            if (!$isConfirmed) {
                $this->sendPendingNotification($offer, $eventName, $params, $bot);
                return;
            }

            // 4. Pass CONFIRMED: actualizar estado canónico → dispara OfferObserver
            $this->processConfirmedEvent($offer, $eventName, $params, $data);

        } catch (\Exception $e) {
            // Si falló en confirmed, liberar cache para permitir reintento
            if ($isConfirmed)
                Cache::forget($cacheKey);

            Log::error("🆘 ProcessContractActivity handle:", [
                "event" => $eventName,
                "tx_hash" => $data['tx_hash'],
                "message" => $e->getMessage(),
            ]);
        }
    }

    // =========================================================
    // SECCIÓN 1: LIMPIEZA DE UX (solo en unconfirmed)
    // =========================================================

    /**
     * Elimina mensajes de Telegram que ya no son relevantes cuando se detecta una TX
     * (el mensaje de "Aplicando..." o "Recuperando...").
     * Se ejecuta en el pass unconfirmed para que el usuario no vea el spinner colgado.
     */
    private function handleUxCleanup(Offers $offer, string $eventName, $bot, array $params): void
    {
        // TRADECREATED: borrar el mensaje de "⌛️ Aplicando a la oferta..."
        if ($eventName === 'TRADECREATED') {
            $applyData = $offer->data['apply'] ?? null;
            if ($applyData && isset($applyData['message_id'], $applyData['user_id'])) {
                try {
                    DeleteTelegramMessage::dispatch(
                        (string) $bot->token,
                        (int) $applyData['user_id'],
                        (int) $applyData['message_id']
                    );
                    // Limpiar para no reintentar en el pass confirmed
                    $currentData = $offer->data;
                    unset($currentData['apply']['message_id']);
                    $offer->update(['data' => $currentData]);
                } catch (\Exception $e) {
                }
            }
        }

        // TRADEEXPIRED: borrar el mensaje de "⌛️ Recuperando fondos..."
        if ($eventName === 'TRADEEXPIRED') {
            $recoverData = $offer->data['recover'] ?? null;
            if ($recoverData && isset($recoverData['message_id'], $recoverData['user_id'])) {
                try {
                    DeleteTelegramMessage::dispatch(
                        (string) $bot->token,
                        (int) $recoverData['user_id'],
                        (int) $recoverData['message_id']
                    );
                } catch (\Exception $e) {
                }
            }
        }
    }

    // =========================================================
    // SECCIÓN 2: NOTIFICACIÓN INSTANTÁNEA (unconfirmed)
    // Mensaje breve que informa que la TX fue detectada.
    // No incluye instrucciones financieras (seguridad ante reorgs).
    // =========================================================

    private function sendPendingNotification(Offers $offer, string $eventName, array $params, $bot): void
    {
        $code = $offer->code;
        $header = "⌛️ *" . Lang::get("zentrotraderbot::bot.offer.pending.title") . "*\n🆔 `{$code}`\n";

        switch ($eventName) {
            case 'TRADECREATED':
                $seller = $params['seller'] ?? $offer->seller_address;
                $buyer  = $params['buyer']  ?? $offer->buyer_address;
                $msgSeller = $header
                    . Lang::get("zentrotraderbot::bot.offer.pending.creating_seller.line1") . "\n"
                    . Lang::get("zentrotraderbot::bot.offer.pending.creating_seller.line2") . "\n"
                    . Lang::get("zentrotraderbot::bot.offer.pending.creating_seller.line3");
                $msgBuyer = $header
                    . Lang::get("zentrotraderbot::bot.offer.pending.creating_buyer.line1") . "\n"
                    . Lang::get("zentrotraderbot::bot.offer.pending.creating_buyer.line2") . "\n"
                    . Lang::get("zentrotraderbot::bot.offer.pending.creating_buyer.line3");
                $this->notifyByAddress($seller, $msgSeller, $bot->token, [], $offer);
                $this->notifyByAddress($buyer,  $msgBuyer,  $bot->token, [], $offer);
                break;

            case 'TRADECANCELLED':
                $msg = $header . Lang::get("zentrotraderbot::bot.offer.pending.cancelling.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.cancelling.line2");
                $this->notifyByAddress($offer->seller_address, $msg, $bot->token, [], $offer);
                $this->notifyByAddress($offer->buyer_address, $msg, $bot->token, [], $offer);
                break;

            case 'TRADESIGNED':
                $signer = $params['signer'] ?? null;
                // Determinar si quien firma es el comprador o el vendedor para contextualizar el mensaje
                $signerIsBuyer = $signer && strtolower($signer) === strtolower($offer->buyer_address ?? '');
                if ($signerIsBuyer) {
                    // El comprador envió su comprobante → le confirmamos y le explicamos que espera al vendedor
                    $msg = $header . "✅ " . Lang::get("zentrotraderbot::bot.offer.pending.signing_proof.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.signing_proof.line2");
                    $this->notifyByAddress($offer->buyer_address, $msg, $bot->token, [], $offer);
                } else {
                    // El vendedor confirmó la recepción → le indicamos que la TX se está cerrando
                    $msg = $header . "✅ " . Lang::get("zentrotraderbot::bot.offer.pending.signing_confirm.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.signing_confirm.line2");
                    $this->notifyByAddress($signer ?: $offer->seller_address, $msg, $bot->token, [], $offer);
                }
                break;

            case 'TRADECLOSED':
                $msgBuyer = $header . "🎉 " . Lang::get("zentrotraderbot::bot.offer.pending.closing_buyer.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.closing_buyer.line2") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.closing_buyer.line3");
                $msgSeller = $header . "🎉 " . Lang::get("zentrotraderbot::bot.offer.pending.closing_seller.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.closing_seller.line2") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.closing_seller.line3");
                $this->notifyByAddress($offer->buyer_address, $msgBuyer, $bot->token, [], $offer);
                $this->notifyByAddress($offer->seller_address, $msgSeller, $bot->token, [], $offer);
                break;

            case 'TRADEEXPIRED':
                $msg = $header . Lang::get("zentrotraderbot::bot.offer.pending.expiring.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.expiring.line2");
                // Notificamos al vendedor que inició la expiración
                $userId = $offer->data['recover']['user_id'] ?? null;
                if ($userId) {
                    $suscriptor = Suscriptions::where('user_id', $userId)->first();
                    if ($suscriptor)
                        $this->notifyByAddress($suscriptor->data['wallet']['address'] ?? '', $msg, $bot->token, [], $offer);
                }
                break;

            case 'DISPUTEOPENED':
                $msg = $header . Lang::get("zentrotraderbot::bot.offer.pending.dispute.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.dispute.line2");
                $this->notifyByAddress($offer->seller_address, $msg, $bot->token, [], $offer);
                $this->notifyByAddress($offer->buyer_address, $msg, $bot->token, [], $offer);
                break;

            case 'DISPUTERESOLVED':
                $msg = $header . Lang::get("zentrotraderbot::bot.offer.pending.resolving.line1") . "\n" . Lang::get("zentrotraderbot::bot.offer.pending.resolving.line2");
                $this->notifyByAddress($offer->seller_address, $msg, $bot->token, [], $offer);
                $this->notifyByAddress($offer->buyer_address, $msg, $bot->token, [], $offer);
                break;
        }
    }

    // =========================================================
    // SECCIÓN 3: PROCESADO CONFIRMADO
    // Actualiza offer->status → dispara OfferObserver con mensaje completo.
    // =========================================================

    private function processConfirmedEvent(Offers $offer, string $eventName, array $params, array $data): void
    {
        switch ($eventName) {
            case 'TRADECREATED':
                $offer->updateStatus('LOCKED', [
                    'seller_address' => strtolower($params['seller']),
                    'buyer_address' => strtolower($params['buyer']),
                    'tx_hash_deposit' => $data['tx_hash'],
                    'updated_at' => now(),
                ]);
                Log::info("✅ Oferta {$offer->id} bloqueada en ESCROW (CONFIRMADO):", [
                    'tx_hash' => $data['tx_hash'],
                ]);
                break;

            case 'TRADECANCELLED':
                $offer->updateStatus('CANCELLED', ['updated_at' => now()]);
                break;

            case 'DISPUTEOPENED':
                $offer->updateStatus('DISPUTED', ['updated_at' => now()]);
                break;

            case 'DISPUTERESOLVED':
                $offer->updateStatus('SOLVED', [
                    'winner_address' => $params['winner'],
                    'tx_hash_release' => $data['tx_hash'],
                    'updated_at' => now(),
                ]);
                break;

            case 'TRADESIGNED':
                $currentData = $offer->data;
                $currentData['signer'] = $params['signer'];
                $offer->update(['data' => $currentData]);
                $offer->updateStatus('SIGNED', ['updated_at' => now()]);
                break;

            case 'TRADECLOSED':
                $offer->updateStatus('COMPLETED', [
                    'tx_hash_release' => $data['tx_hash'],
                    'updated_at' => now(),
                ]);
                break;

            case 'TRADEEXPIRED':
                $offer->updateStatus('EXPIRED', ['updated_at' => now()]);
                break;
        }
    }

    // =========================================================
    // SECCIÓN 4: HELPERS
    // =========================================================

    /**
     * Envía un mensaje de Telegram buscando al usuario por su dirección de wallet.
     * Elimina el mensaje de estado anterior del usuario (si existe) y almacena el nuevo message_id.
     */
    private function notifyByAddress(?string $address, string $text, string $token, array $menu = [], ?Offers $offer = null): void
    {
        if (!$address)
            return;

        $suscriptor = Suscriptions::findByAddress($address);
        if (!$suscriptor || !$suscriptor->user_id)
            return;

        $telegramId = $suscriptor->user_id;

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

        if (!empty($menu))
            $payload['message']['reply_markup'] = json_encode(['inline_keyboard' => $menu]);

        $response = TelegramController::sendMessage($payload, $token);

        // Guardar el message_id del mensaje enviado para poder eliminarlo en el siguiente estado
        if ($offer) {
            $arr = json_decode($response, true);
            $msgId = $arr['result']['message_id'] ?? null;
            if ($msgId && (int) $msgId > 0) {
                $data = $offer->data ?? [];
                $data['last_status_messages'][$telegramId] = (int) $msgId;
                $offer->update(['data' => $data]);
            }
        }
    }
}
