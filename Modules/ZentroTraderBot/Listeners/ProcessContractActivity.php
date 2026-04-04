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

        // 1. Filtro de Confirmación (Seguridad Blockchain)
        if (!($data['confirmed'] ?? false)) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity handle escaped by !confirmed: ", [
                    "data" => $data,
                ]);
            return;
        }

        // 2. Identificar el Bot/Tenant
        $bot = TelegramBots::where('key', $data['tenant_code'])->first();
        if (!$bot) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity handle escaped by !bot: ", [
                    "tenant_code" => $data['tenant_code'],
                    "data" => $data,
                ]);
            return;
        }
        $bot->connectToThisTenant();

        // Idempotencia: tx_hash + log_index (por si una TX dispara varios eventos)
        $cacheKey = 'escrow_ev_processed_' . $data['tx_hash'] . '_' . ($data['log_index'] ?? 0);
        if (!Cache::add($cacheKey, true, now()->addDays(2))) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity handle escaped by tx_processed: ", [
                    "key" => $cacheKey,
                    "data" => $data,
                ]);
            return;
        }

        try {
            $eventName = strtoupper($data['decoded']['name']);
            $params = $data['decoded']['params'];
            $tradeId = $params['tradeId'];
            $offer = Offers::on('tenant')->where('id', $tradeId)->first();

            switch ($eventName) {
                case 'TRADECREATED':
                    // En el nuevo contrato, el trade ya nace LOCKED (bloqueado)
                    $this->syncTradeCreated($tradeId, $params, $data);
                    break;

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
                    $offer->data = $json;
                    $offer->save();
                    $offer->refresh();

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
            Cache::forget($cacheKey);
            Log::error("🆘 ProcessContractActivity handle Listener: ", [
                "message" => $e->getMessage()
            ]);
        }
    }

    /**
     * Vincula el evento de creación con un anuncio existente o crea uno nuevo.
     */
    private function syncTradeCreated($blockchainId, $params, $rawData)
    {
        // 1. Identificar el token directamente desde el evento
        // Usamos el ConfigService con la dirección que el evento nos da ahora
        $tokenAddress = strtolower($params['token']);
        $token = ConfigService::getToken($tokenAddress, $rawData['network_id']);


        $seller = strtolower($params['seller']);
        $buyer = strtolower($params['buyer']);

        // 2. Conversión a humano usando el amount
        $amount = $params['amount'] / pow(10, $token['decimals'] ?? 18);
        $amount = NumberService::round($amount, 4, false);

        // 3. Buscar suscriptor (Vendedor)
        $suscriptor = Suscriptions::on('tenant')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, "$.wallet.address"))) = ?', [$seller])
            ->first();

        if (!$suscriptor) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 ProcessContractActivity syncTradeCreated escaped by !suscriptor: ", [
                    "address" => $seller,
                    "params" => $params,
                ]);
            return;
        }

        $offer = Offers::on('tenant')->where('id', $blockchainId)->first();
        $offer->updateStatus('LOCKED', [
            'seller_address' => $seller,
            'buyer_address' => $buyer,
            'updated_at' => now()
        ]);

        /*
            // Aprovechamos el timeoutAt para saber cuándo expira
            'expires_at' => date('Y-m-d H:i:s', $params['timeoutAt']),
             */

        Log::info("✅ Oferta {$offer->id} bloqueada en ESCROW: ", [
            "id" => $offer->id,
            "blockchainId" => $blockchainId,
            "data" => $offer,
        ]);

    }
}