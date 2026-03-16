<?php

namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\WalletActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Services\ConfigService;
use Illuminate\Support\Facades\Cache;
use Modules\Laravel\Http\Controllers\MathController;

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

        // 1. Filtro de Confirmación: Si no esta confirmada la desechamos
        if (!($data['confirmed'] ?? false))
            return;

        // 2. Normalización de Token Nativo (MATIC, BNB, ETH...)
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
                }
            } catch (\Throwable $th) {
            }
        }

        // 3. Filtro Anti-Scam (Solo para Tokens ERC20/BEP20): Si no existe en el listado oficial provisto por 1inch lo desechamos
        if (!empty($data['token_address'])) {
            try {
                $token = ConfigService::getToken(strtolower($data['token_address']), $data['network_id']);
                if (!$token)
                    return;
            } catch (\Throwable $th) {
                Log::error("🆘 ProcessWalletActivity handle Anti-Scam: ", [
                    "network_id" => $data['network_id'],
                    "token_address" => $data['token_address'],
                    "data" => $data,
                ]);
            }
        }

        // Si no trae token_symbol no esta estandarizada para este evento por lo q se desestima
        if (isset($data['token_symbol'])) {
            // 4. Identificar el Bot/Tenant
            $bot = TelegramBots::where('key', $data['tenant_code'])->first();
            if (!$bot) {
                Log::error("🆘 ProcessWalletActivity handle: Bot no encontrado para tenant: " . ($data['tenant_code'] ?? 'N/A'));
                return;
            }
            $bot->connectToThisTenant();

            // 5. Buscar al suscriptor usando la dirección estandarizada
            $toAddress = strtolower($data['to']);
            $suscriptor = Suscriptions::on('tenant')->where('data->wallet->address', $toAddress)->first();
            if (!$suscriptor) {
                Log::error("🆘 ProcessWalletActivity handle: Suscriptor no encontrado para wallet = {$toAddress}");
                return;
            }

            //  Validación de duplicidad: Verificar si el tx_hash ya fue procesado
            $cacheKey = 'tx_processed_' . $data['tx_hash'];
            if (Cache::has($cacheKey)) {
                return;
            }

            $botController = new ZentroTraderBotController();
            $botController->notifyDepositConfirmed(
                $suscriptor->user_id,
                MathController::round($data['value'], 4, false),
                $data['token_symbol']
            );

            // Marcar como procesado en caché por 24 horas
            Cache::put($cacheKey, true, now()->addHours(24));

            Log::info("✅ Depósito procesado exitosamente: ", [
                "id" => $data['trace_id'],
                "data" => $data,
            ]);

        }
    }
}