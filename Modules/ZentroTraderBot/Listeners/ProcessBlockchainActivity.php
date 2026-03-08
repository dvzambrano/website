<?php

namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\BlockchainActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;

class ProcessBlockchainActivity
{
    /**
     * Procesa eventos de actividad blockchain de manera agnóstica al proveedor.
     * Recibe datos normalizados (DTO) desde cualquier fuente (Moralis, Alchemy, etc.), 
     * identifica el tenant correspondiente y dispara las notificaciones al usuario.
     *
     * @param BlockchainActivityDetected $event Contiene el DTO normalizado con 
     * la estructura: network_id, tx_hash, from, to, value, token_symbol, tenant_code.
     * * @return void
     */
    public function handle(BlockchainActivityDetected $event)
    {
        // Ahora $data contiene nuestra estructura normalizada:
        // 'network_id', 'confirmed', 'tx_hash', 'from', 'to', 'value', 'token_symbol'...
        $data = $event->data;
        Log::debug("🐞 ProcessBlockchainActivity handle: " . json_encode($data));


        /*
        // 2. Filtro inteligente: ¿Ignoramos los no confirmados? 
        // Depende de tu negocio, pero generalmente solo procesamos el 'confirmed: true'
        if (!$data['confirmed']) {
            continue; 
        }
        */

        // 1. Identificar el Bot/Tenant
        // Nota: Asegúrate de que el extractor de cada Provider incluya el 'tenant_code'
        // Si no, añádelo en el controlador antes de disparar el evento.
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
            $botController = new ZentroTraderBotController();

            // Usamos las llaves normalizadas que definimos en extractRelevantData
            $botController->notifyDepositConfirmed(
                $suscriptor->user_id,
                $data['value'],
                $data['token_symbol']
            );

            Log::info("✅ Depósito procesado exitosamente en {$data['network_id']} para usuario {$suscriptor->user_id}");
        }
    }
}