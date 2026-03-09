<?php

namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\BlockchainActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Services\ConfigService;

class ProcessBlockchainActivity
{
    /**
     * Procesa eventos de actividad blockchain de manera agnóstica al proveedor.
     * Recibe datos normalizados (DTO) desde cualquier fuente (Moralis, Alchemy, etc.), 
     * identifica el tenant correspondiente y dispara las notificaciones al usuario.
     *
     * @param BlockchainActivityDetected $event Contiene el DTO normalizado con 
     * la estructura: 'network_id', 'confirmed', 'tx_hash', 'from', 'to', 'value', 'token_symbol', 'tenant_code'.
     * * @return void
     */
    public function handle(BlockchainActivityDetected $event)
    {
        $data = $event->data;
        Log::debug("🐞 ProcessBlockchainActivity handle: ", [
            "id" => $data['trace_id'],
            "confirmed" => $data['confirmed'],
            "data" => $data,
        ]);

        /*
           {
                "network_id": "0x89",
                "confirmed": false,
                "block_number": "83945304",
                "tx_hash": "0x19d00883c41d12bae05844ed76b5521fd897afe1ec825ee2cbf6fb2550530b63",
                "from": "0x697b45689e4b4cce3c316f21ed6c8a6cb053873d",
                "to": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                "value": "1000",
                "token_symbol": "pedgy",
                "token_address": "0x1f7aee83037ae9979e6d5b8bc4581e007da53981",
                "timestamp": "1773010641",
                "tenant_code": "59d5e7a3-dea0-4289-88f0-a39765f50bcf"
            }
        */

        // Si no esta confirmada la desechamos
        if (!$data['confirmed'])
            return;

        // Si no trae token_symbol no esta estandarizada para este evento por lo q se desestima
        if (isset($data['token_symbol'])) {
            // 1. Identificar el Bot/Tenant
            $bot = TelegramBots::where('key', $data['tenant_code'])->first();
            if (!$bot) {
                Log::error("🆘 ProcessBlockchainActivity: Bot no encontrado para tenant: " . ($data['tenant_code'] ?? 'N/A'));
                return;
            }
            $bot->connectToThisTenant();

            // 2. Buscar al suscriptor usando la dirección estandarizada
            $toAddress = strtolower($data['to']);
            $suscriptor = Suscriptions::on('tenant')->where('data->wallet->address', $toAddress)->first();
            if ($suscriptor) {
                if ($data['token_symbol'] == "" && is_numeric($data['network_id'])) {
                    try {
                        $network = ConfigService::getNetworks((int) $data['network_id']);
                        if ($network)
                            $data['token_symbol'] = strtoupper($network["shortName"]);
                    } catch (\Throwable $th) {

                    }
                }
                $botController = new ZentroTraderBotController();
                $botController->notifyDepositConfirmed(
                    $suscriptor->user_id,
                    $data['value'],
                    $data['token_symbol']
                );

                Log::info("✅ Depósito procesado exitosamente en {$data['network_id']} para usuario {$suscriptor->user_id}");
            }
        }
    }
}