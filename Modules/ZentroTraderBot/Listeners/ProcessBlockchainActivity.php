<?php
namespace Modules\ZentroTraderBot\Listeners;

use Modules\Web3\Events\BlockchainActivityDetected;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Entities\Ramporders;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;

class ProcessBlockchainActivity
{
    public function handle(BlockchainActivityDetected $event)
    {
        $activity = $event->data;

        /*
        {
            "webhookId": "wh_2vk2jwuyidkm80xa",
            "id": "whevt_t3dhlkpqocswvmy8",
            "createdAt": "2026-02-03T16:40:53.702Z",
            "type": "ADDRESS_ACTIVITY",
            "event": {
                "network": "MATIC_MAINNET",
                "activity": [
                    {
                        "fromAddress": "0xb0873c46937d34e98615e8c868bd3580bc6dcd47",
                        "toAddress": "0xd2531438b90232f4aab4ddfc6f146474e84e1ea1",
                        "blockNum": "0x4eafa87",
                        "hash": "0x8542bfadf98f54778587710af7ee3c476d3ff2e2f772cfb8b41b99fefc01e115",
                        "value": 0.111153,
                        "asset": "USDC",
                        "category": "token",
                        "rawContract": {
                            "rawValue": "0x000000000000000000000000000000000000000000000000000000000001b231",
                            "address": "0x3c499c542cef5e3811e1192ce70d8cc03d5c3359",
                            "decimals": 6
                        },
                        "log": {
                            "address": "0x3c499c542cef5e3811e1192ce70d8cc03d5c3359",
                            "topics": [
                                "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef",
                                "0x000000000000000000000000b0873c46937d34e98615e8c868bd3580bc6dcd47",
                                "0x000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1"
                            ],
                            "data": "0x000000000000000000000000000000000000000000000000000000000001b231",
                            "blockNumber": "0x4eafa87",
                            "transactionHash": "0x8542bfadf98f54778587710af7ee3c476d3ff2e2f772cfb8b41b99fefc01e115",
                            "transactionIndex": "0xfa",
                            "blockHash": "0x9d2932cfe732e9d8c3d612378012e43bd8989efdf899092a78c28463b191c7e5",
                            "blockTimestamp": "0x69822514",
                            "logIndex": "0xfcf",
                            "removed": false
                        },
                        "blockTimestamp": "0x69822514"
                    }
                ],
                "source": "chainlake-kafka"
            }
        }
         */
        $toAddress = strtolower($activity['toAddress']);

        // Aquí sí conocemos a Suscriptions porque estamos dentro del módulo del Bot
        $suscriptor = Suscriptions::where('data->wallet->address', $toAddress)->first();
        if ($suscriptor) {
            // aqui podriamos actualizar una orden entrante, pero el deposito no necesariamente viene por esa via
            if (!Ramporders::where('tx_hash', $activity['hash'])->exists()) {

            }

            $bot = new ZentroTraderBotController('Kashio'); // O dinámico
            $bot->notifyDepositConfirmed(
                $suscriptor->user_id,
                $activity['value'],
                $activity['asset']
            );
        }
    }
}