<?php

namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\ContractActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Services\ConfigService;
use Illuminate\Support\Facades\Cache;
use Modules\Laravel\Services\NumberService;
use Modules\ZentroTraderBot\Entities\Offers;
use Illuminate\Support\Str;
use Modules\Laravel\Services\BehaviorService;
use Modules\TelegramBot\Jobs\DeleteTelegramMessage;

class ProcessContractActivity
{
    /**
     * Procesa eventos provenientes del contrato de Escrow.
     * Maneja: TradeCreated, TradeCancelled, DisputeOpened, DisputeResolved, TradeSigned, TradeClosed
     * Recibe datos normalizados (DTO) desde cualquier fuente (Moralis, Alchemy, etc.), 
     * identifica el tenant correspondiente y dispara las notificaciones al usuario.
     *
     * @param ContractActivityDetected $event Contiene el DTO normalizado con 
     * la estructura: 'network_id', 'confirmed', 'tx_hash', 'from', 'to', 'value', 'token_symbol', 'tenant_code'.
     * * @return void
     */
    public function handle(ContractActivityDetected $event)
    {
        $data = $event->data;

        if (env("DEBUG_MODE", false))
            Log::debug("🐞 ProcessContractActivity handle: ", [
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

        // Ignoramos transferencias de tokens que no son nuestro Escrow
        if (strtolower($data['contract']) !== strtolower(env('ESCROW_CONTRACT'))) {
            return;
        }

        $eventName = strtoupper($data['decoded']['name']);
        $isConfirmed = $data['confirmed'] ?? false;

        // Idempotencia: tx_hash + eventName + estado de confirmación: permite que el flujo pase una vez por 'unconfirmed' y una vez por 'confirmed'
        $statusSuffix = $isConfirmed ? 'confirmed' : 'unconfirmed';
        $cacheKey = 'escrow_ev_proc_' . $eventName . '_' . $data['tx_hash'] . '_' . $statusSuffix;
        if (!Cache::add($cacheKey, true, now()->addDays(2))) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity handle escaped by tx_processed ($statusSuffix): ", [
                    "key" => $cacheKey
                ]);
            return;
        }

        // --- Identificar el Bot siempre para poder usar el Tenant ---
        $bot = BehaviorService::cache('tenant_' . $data['tenant_code'], function () use ($data) {
            return TelegramBots::where('key', $data['tenant_code'])->first();
        });
        if (!$bot) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity handle escaped by !bot: ", [
                    "tenant_code" => $data['tenant_code'],
                    "data" => $data,
                ]);
            return;
        }
        $bot->connectToThisTenant();

        $params = $data['decoded']['params'];
        if (isset($params['tradeId'])) {
            try {
                $tradeId = $params['tradeId'];
                $offer = Offers::on('tenant')->where('id', $tradeId)->first();

                // --- MANEJO DE TRADECREATED (UNCONFIRMED & CONFIRMED) ---
                if ($eventName === 'TRADECREATED') {
                    // 1. Recuperar el message_id del proceso de aplicación (si existe)
                    $applyData = $offer->data['apply'] ?? null;
                    if (!$isConfirmed && $applyData && isset($applyData['message_id'])) {
                        try {
                            // El chat_id es el del comprador (quien inició el apply)
                            $userId = $applyData['user_id'] ?? null;
                            if ($userId) {
                                DeleteTelegramMessage::dispatch(
                                    (string) $bot->token,
                                    (int) $userId,
                                    (int) $applyData['message_id']
                                );

                                // Limpiamos el message_id de la DB para no intentar borrarlo dos veces
                                $currentData = $offer->data;
                                unset($currentData['apply']['message_id']);
                                $offer->data = $currentData;
                                $offer->save();
                            }
                        } catch (\Exception $e) {
                        }
                    }

                    // Datos base de la actualización
                    $updateData = [
                        'seller_address' => strtolower($params['seller']),
                        'buyer_address' => strtolower($params['buyer']),
                        'updated_at' => now()
                    ];

                    if ($isConfirmed) {
                        // Cuando llega confirmado, grabamos el hash y logueamos
                        $updateData['tx_hash_deposit'] = $data['tx_hash'];

                        Log::info("✅ Oferta {$offer->id} bloqueada en ESCROW (CONFIRMADO): ", [
                            "tx_hash" => $data['tx_hash']
                        ]);
                    }

                    // updateStatus disparará el Observer tanto en unconfirmed como en confirmed
                    // El Observer debería ser inteligente para no repetir mensajes si ya se enviaron
                    $offer->updateStatus('LOCKED', $updateData);


                    return; // Terminamos aquí para TRADECREATED
                }

                // --- RESTO DE EVENTOS (SOLO CONFIRMADOS) ---
                if (!$isConfirmed)
                    return;

                switch ($eventName) {
                    case 'TRADEEXPIRED':
                        // El tiempo se agotó y el vendedor ejecutó la reclamación
                        $offer->updateStatus('EXPIRED', [
                            'updated_at' => now()
                        ]);
                        break;
                    case 'TRADECANCELLED':
                        // El vendedor canceló antes de que el comprador firmara
                        $offer->updateStatus('CANCELLED', [
                            'updated_at' => now()
                        ]);
                        break;
                    case 'DISPUTEOPENED':
                        $offer->updateStatus('DISPUTED', [
                            'updated_at' => now()
                        ]);
                        break;
                    case 'DISPUTERESOLVED':
                        // El arbitro decidió un ganador
                        $offer->updateStatus('SOLVED', [
                            'winner_address' => $params['winner'],
                            'tx_hash_release' => $data['tx_hash'],
                            'updated_at' => now()
                        ]);
                        break;
                    case 'TRADESIGNED':
                        // Útil para avisar al otro: "¡Oye, ya firmaron, solo faltas tú!"
                        $json = $offer->data;
                        $json["signer"] = $params['signer'];
                        $offer->update(['data' => $json]);
                        $offer->updateStatus('SIGNED', [
                            'updated_at' => now()
                        ]);
                        break;
                    case 'TRADECLOSED':
                        // Ambos firmaron y los fondos volaron al comprador
                        $offer->updateStatus('COMPLETED', [
                            'tx_hash_release' => $data['tx_hash'],
                            'updated_at' => now()
                        ]);
                        break;

                    default:
                        break;
                }

            } catch (\Exception $e) {
                if ($isConfirmed)
                    Cache::forget($cacheKey);

                Log::error("🆘 ProcessContractActivity handle Listener: ", [
                    "message" => $e->getMessage()
                ]);
            }
        }
    }
}