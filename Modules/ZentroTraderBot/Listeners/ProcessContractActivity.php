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
        {
            "id": "af0c090f-1b52-4572-9c04-f79a9891a533",
            "confirmed": true,
            "data": {
                "network_id": 80002,
                "confirmed": true,
                "tx_hash": "0x312019fc22fb9c2bd9173b3e2ecc2f27155040c2ae1c5afbca25acb09fe25100",
                "contract": "0x8b0180f2101c8260d49339abfee87927412494b4",
                "topic0": "0x8c5be1e5ebec7d5bd14f71427d1e84f3dd0314c0f7b2291e5b200ac8c7c3b925",
                "block_number": "35268999",
                "timestamp": "1773665531",
                "from_address": "0x3e254e81106e19b4c961cbc800390aed2a8fe800",
                "decoded": {
                    "name": "Approval",
                    "params": {
                        "owner": "0x3e254e81106e19b4c961cbc800390aed2a8fe800",
                        "spender": "0xc15e5d5173966380fc2b297a59ed89019e4fea12",
                        "value": { "phpseclib\\Math\\BigInteger": "10000" }
                    }
                },
                "tenant_code": "59d5e7a3-dea0-4289-88f0-a39765f50bcf",
                "listener": "moralis",
                "trace_id": "af0c090f-1b52-4572-9c04-f79a9891a533"
            }
        }


        */


    }
}