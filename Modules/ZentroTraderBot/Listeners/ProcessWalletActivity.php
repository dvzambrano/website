<?php

namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\WalletActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\ZentroTraderBot\Http\Controllers\TraderWalletController;
use Dvzambrano\TelegramBot\Entities\TelegramBots;
use Dvzambrano\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Lang;
use Modules\Web3\Services\ConfigService;
use Illuminate\Support\Facades\Cache;
use Modules\Laravel\Services\NumberService;
use Modules\Laravel\Services\TextService;
use Modules\Laravel\Services\BehaviorService;

class ProcessWalletActivity
{
    /**
     * Procesa eventos de actividad blockchain de manera agnóstica al proveedor.
     * Recibe datos normalizados (DTO) desde cualquier fuente (Moralis, Alchemy, etc.), 
     * identifica el tenant correspondiente y dispara las notificaciones al usuario.
     *
     * @param WalletActivityDetected $event Contiene el DTO normalizado con 
     * la estructura: 'network_id', 'confirmed', 'tx_hash', 'from', 'to', 'value', 'token_symbol', 'tenant_code'.
     * * @return void
     */
    public function handle(WalletActivityDetected $event)
    {
        $data = $event->data;

        if (env("DEBUG_MODE", false))
            Log::debug("🐞 ProcessWalletActivity handle: ", [
                "id" => $data['trace_id'],
                "confirmed" => $data['confirmed'],
                "data" => $data,
            ]);

        /*

            {
                "network_id": 80002,
                "confirmed": true,
                "block_number": "35278590",
                "timestamp": "1773684713",
                "tenant_code": "59d5e7a3-dea0-4289-88f0-a39765f50bcf",
                "listener": "moralis",
                "type": "native_tx",
                "tx_hash": "0x22d6fc283e705c7695819c9782dba7d103eaae89e82325355c435c853ad5051b",
                "from": "0x3e254e81106e19b4c961cbc800390aed2a8fe800",
                "to": "0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d",
                "value": 0.1,
                "token_symbol": "POLYGONAMOY",
                "token_address": "",
                "trace_id": "7edf328d-ffaf-4f3c-b106-9cfa0aa61b45"
            }
        */

        // 1. Normalización de Token Nativo (MATIC, BNB, ETH...)
        if (empty($data['token_symbol']) && is_numeric($data['network_id'])) {
            try {
                $network = ConfigService::getNetworks((int) $data['network_id']);
                if ($network) {
                    $data['token_symbol'] = strtoupper($network["shortName"]);
                    if (env("DEBUG_MODE", false))
                        Log::debug("🐞 ProcessWalletActivity handle update native token_symbol: ", [
                            "network_id" => $data['network_id'],
                            "token_symbol" => $data['token_symbol'],
                            "data" => $data,
                        ]);
                } else {
                    // ERROR: Si es una red desconocida y no tenemos símbolo, 
                    // no podemos notificar correctamente. Abortamos.
                    if (env("DEBUG_MODE", false))
                        Log::debug("🐞 ProcessWalletActivity handle escaped by !network: ", [
                            "data" => $data,
                        ]);
                    return;
                }
            } catch (\Throwable $th) {
            }
        }

        // 2. Filtro Anti-Scam (Solo para Tokens ERC20)
        if (!empty($data['token_address'])) {
            // CASO A: Es un Token (ERC20). 
            // Si no existe en el listado oficial provisto por 1inch lo desechamos
            try {
                $token = ConfigService::getToken(strtolower($data['token_address']), $data['network_id']);
                if (!$token) {
                    if (env("DEBUG_MODE", false))
                        Log::debug("🐞 ProcessWalletActivity handle escaped by !token: ", [
                            "network_id" => $data['network_id'],
                            "token_address" => $data['token_address'],
                            "data" => $data,
                        ]);
                    return;
                }
                // IMPORTANTE: Sobreescribimos el symbol con el que nos da nuestra fuente de confianza
                $data['token_symbol'] = strtoupper($token['symbol']);
            } catch (\Throwable $th) {
                Log::error("🆘 ProcessWalletActivity handle Anti-Scam: ", [
                    "network_id" => $data['network_id'],
                    "token_address" => $data['token_address'],
                    "data" => $data,
                ]);
            }
        } else {
            // CASO B: Es moneda Nativa (MATIC/POL, ETH...).
            // Obligatorio: El símbolo debe haberse resuelto en el paso 2 (ConfigService::getNetworks).
            if (empty($data['token_symbol'])) {
                if (env("DEBUG_MODE", false))
                    Log::debug("🐞 ProcessWalletActivity handle escaped by !token_symbol: ", [
                        "data" => $data,
                    ]);
                return;
            }
        }

        // 3. Identificar el Bot/Tenant (necesario para ambos paths)
        $bot = BehaviorService::cache('tenant_' . $data['tenant_code'], function () use ($data) {
            return TelegramBots::where('key', $data['tenant_code'])->first();
        });
        if (!$bot) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessWalletActivity handle escaped by !bot: ", [
                    "tenant_code" => $data['tenant_code'],
                    "data" => $data,
                ]);
            return;
        }
        $bot->connectToThisTenant();

        // PATH A: Transferencia ERC20 saliente desde una wallet registrada (incluso sin confirmar)
        // Se ejecuta antes del filtro de confirmación para reaccionar lo antes posible.
        if ($data['type'] === 'erc20_transfer' && !empty($data['from'])) {
            $fromAddress = strtolower($data['from']);
            $fromSuscriptor = Suscriptions::on('tenant')
                ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, "$.wallet.address"))) = ?', [$fromAddress])
                ->first();
            if ($fromSuscriptor) {
                $this->handleOutgoingTransfer($fromSuscriptor, $data, $bot);
            }
        }

        // PATH B: Notificación de depósito entrante (solo transacciones confirmadas)
        if (!($data['confirmed'] ?? false)) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessWalletActivity handle escaped by !confirmed: ", [
                    "data" => $data,
                ]);
            return;
        }

        // 4. Buscar al suscriptor destinatario
        $toAddress = strtolower($data['to']);
        $suscriptor = Suscriptions::on('tenant')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, "$.wallet.address"))) = ?', [$toAddress])
            ->first();
        if (!$suscriptor) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessWalletActivity handle escaped by !suscriptor: ", [
                    "address" => $toAddress,
                    "data" => $data,
                ]);
            return;
        }

        // Idempotencia Atómica: Evita procesar 2 veces el mismo depósito
        $cacheKey = 'tx_processed_' . $data['tx_hash'];
        // Cache::add solo retorna true si la llave NO existía.
        if (!Cache::add($cacheKey, true, now()->addDays(2))) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessWalletActivity handle escaped by tx_processed: ", [
                    "key" => $cacheKey,
                    "data" => $data,
                ]);
            return;
        }

        try {
            $botController = app(ZentroTraderBotController::class);
            $botController->notifyDepositConfirmed(
                $suscriptor,
                NumberService::round($data['value'], 4, false),
                $data['token_address']
            );

            Log::info("✅ Depósito procesado exitosamente: ", [
                "id" => $data['trace_id'],
                "data" => $data,
            ]);
        } catch (\Exception $e) {
            // Si falla el envío, borramos el candado para que el re-intento de Moralis 
            Cache::forget($cacheKey);
            Log::error("🆘 ProcessWalletActivity handle Notification: ", [
                "message" => $e->getMessage()
            ]);
        }
    }

    /**
     * Detecta una salida de BASE_TOKEN y cancela las ofertas OPEN del vendedor
     * que ya no están respaldadas por saldo suficiente. Se ejecuta incluso para
     * transacciones no confirmadas para proteger al comprador potencial lo antes posible.
     */
    private function handleOutgoingTransfer(Suscriptions $suscriptor, array $data, $bot): void
    {
        // Solo actuar sobre el token base del sistema (USDC)
        $baseToken = strtolower(env('BASE_TOKEN', ''));
        if (!empty($baseToken) && strtolower($data['token_address']) !== $baseToken)
            return;

        $transferAmount = (float) ($data['value'] ?? 0);
        if ($transferAmount <= 0)
            return;

        // Idempotencia: procesar la salida una sola vez (primer pass: unconfirmed)
        $cacheKey = 'outgoing_transfer_handled_' . $data['tx_hash'];
        if (!Cache::add($cacheKey, true, now()->addDays(2)))
            return;

        // Balance actual en blockchain (aún no descontada la transferencia)
        try {
            $walletCtrl = new TraderWalletController();
            $currentBalance = (float) $walletCtrl->getBalance($suscriptor);
        } catch (\Throwable $th) {
            $currentBalance = 0.0;
        }

        $balanceAfter = max(0.0, $currentBalance - $transferAmount);

        // Obtener ofertas SELL en estado OPEN, de más nueva a más antigua
        $openOffers = Offers::on('tenant')
            ->where('user_id', $suscriptor->user_id)
            ->where('type', 'sell')
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($openOffers->isEmpty())
            return;

        $openSum = (float) $openOffers->sum('amount');
        if ($balanceAfter >= $openSum)
            return; // El balance posterior cubre todas las ofertas, nada que cancelar

        // Cancelar las ofertas más nuevas hasta cubrir el déficit
        $deficit = $openSum - $balanceAfter;
        $toCancel = [];
        foreach ($openOffers as $offer) {
            if ($deficit <= 0)
                break;
            $toCancel[] = $offer;
            $deficit -= (float) $offer->amount;
        }

        if (empty($toCancel))
            return;

        $cancelledCodes = [];
        foreach ($toCancel as $offer) {
            // Eliminar del canal de Telegram
            if (isset($offer->data['channel']['message_id'])) {
                try {
                    TelegramController::deleteMessage([
                        'message' => [
                            'chat' => ['id' => env('TRADER_BOT_CHANNEL')],
                            'id' => $offer->data['channel']['message_id'],
                        ]
                    ], $bot->token);
                } catch (\Throwable $th) {
                }
            }

            $offer->updateStatus('CANCELLED', ['updated_at' => now()]);
            $cancelledCodes[] = $offer->code;
        }

        // Notificar al usuario por DM
        $count = count($cancelledCodes);
        $codesText = implode(', ', array_map(fn($c) => "`{$c}`", $cancelledCodes));
        $amountText = number_format($transferAmount, 2);

        $msg = "⚠️ *" . TextService::mdv2(Lang::get("zentrotraderbot::bot.offer.cancelled_by_withdrawal.title")) . "*\n"
            . "💸 " . TextService::mdv2(Lang::get("zentrotraderbot::bot.offer.cancelled_by_withdrawal.reason", ['amount' => $amountText])) . "\n\n"
            . "📴 " . TextService::mdv2(Lang::choice("zentrotraderbot::bot.offer.cancelled_by_withdrawal.offers", $count, ['count' => $count])) . "\n"
            . $codesText . "\n\n"
            . "ℹ️ _" . TextService::mdv2(Lang::get("zentrotraderbot::bot.offer.cancelled_by_withdrawal.info")) . "_";

        try {
            TelegramController::sendMessage([
                'message' => [
                    'chat' => ['id' => $suscriptor->user_id],
                    'text' => $msg,
                    'parse_mode' => 'MarkdownV2',
                ]
            ], $bot->token);
        } catch (\Throwable $th) {
        }

        Log::info("✅ Ofertas auto-canceladas por retiro externo: ", [
            'user_id' => $suscriptor->user_id,
            'from' => $data['from'],
            'tx_hash' => $data['tx_hash'],
            'transfer_amount' => $transferAmount,
            'balance_after' => $balanceAfter,
            'cancelled' => $cancelledCodes,
        ]);
    }
}