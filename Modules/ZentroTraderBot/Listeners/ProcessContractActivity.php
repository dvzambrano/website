<?php

namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\ContractActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Services\ConfigService;
use Illuminate\Support\Facades\Cache;
use Modules\Laravel\Http\Controllers\MathController;

class ProcessContractActivity
{
    /**
     * Procesa eventos de actividad blockchain de manera agnóstica al proveedor.
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

        Log::debug("🐞 ProcessContractActivity handle: ", [
            "id" => $data['trace_id'],
            "confirmed" => $data['confirmed'],
            "data" => $data,
        ]);

        /*


        */


    }
}