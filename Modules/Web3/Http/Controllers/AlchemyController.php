<?php

namespace Modules\Web3\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Events\BlockchainActivityDetected;
use Illuminate\Support\Facades\Http;

class AlchemyController extends Controller
{
    public function webhook()
    {
        try {
            $payload = request()->all();
            // Logueamos siempre para auditoría interna
            /*
            [2026-02-03 16:40:53] local.INFO: Alchemy Webhook - Evento recibido:{"webhookId":"wh_2vk2jwuyidkm80xa","id":"whevt_zk9go6vjuvjwav7y","createdAt":"2026-02-03T16:40:53.403Z","type":"ADDRESS_ACTIVITY","event":{"network":"MATIC_MAINNET","activity":[{"fromAddress":"0xd2531438b90232f4aab4ddfc6f146474e84e1ea1","toAddress":"0x0000000000001ff3684f28c67538d4d072c22734","blockNum":"0x4eafa87","hash":"0x8542bfadf98f54778587710af7ee3c476d3ff2e2f772cfb8b41b99fefc01e115","value":1,"asset":"MATIC","category":"external","rawContract":{"rawValue":"0xde0b6b3a7640000","decimals":18},"blockTimestamp":"0x69822514"}],"source":"chainlake-kafka"}}  
            [2026-02-03 16:40:54] local.INFO: Alchemy Webhook - Evento recibido:{"webhookId":"wh_2vk2jwuyidkm80xa","id":"whevt_t3dhlkpqocswvmy8","createdAt":"2026-02-03T16:40:53.702Z","type":"ADDRESS_ACTIVITY","event":{"network":"MATIC_MAINNET","activity":[{"fromAddress":"0xb0873c46937d34e98615e8c868bd3580bc6dcd47","toAddress":"0xd2531438b90232f4aab4ddfc6f146474e84e1ea1","blockNum":"0x4eafa87","hash":"0x8542bfadf98f54778587710af7ee3c476d3ff2e2f772cfb8b41b99fefc01e115","value":0.111153,"asset":"USDC","category":"token","rawContract":{"rawValue":"0x000000000000000000000000000000000000000000000000000000000001b231","address":"0x3c499c542cef5e3811e1192ce70d8cc03d5c3359","decimals":6},"log":{"address":"0x3c499c542cef5e3811e1192ce70d8cc03d5c3359","topics":["0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef","0x000000000000000000000000b0873c46937d34e98615e8c868bd3580bc6dcd47","0x000000000000000000000000d2531438b90232f4aab4ddfc6f146474e84e1ea1"],"data":"0x000000000000000000000000000000000000000000000000000000000001b231","blockNumber":"0x4eafa87","transactionHash":"0x8542bfadf98f54778587710af7ee3c476d3ff2e2f772cfb8b41b99fefc01e115","transactionIndex":"0xfa","blockHash":"0x9d2932cfe732e9d8c3d612378012e43bd8989efdf899092a78c28463b191c7e5","blockTimestamp":"0x69822514","logIndex":"0xfcf","removed":false},"blockTimestamp":"0x69822514"}],"source":"chainlake-kafka"}}  
            */
            Log::info("Alchemy Webhook - Evento recibido:" . json_encode($payload));

            // 1. Validar que sea un evento de actividad (Address Activity)
            if (($payload['type'] ?? '') !== 'ADDRESS_ACTIVITY') {
                return response()->json(['status' => 'ignored'], 200);
            }

            $usdcContract = config('web3.tokens.USDC.address'); // USDC en Polygon: 0x3c499c542cef5e3811e1192ce70d8cc03d5c3359

            $activities = $payload['event']['activity'] ?? [];
            foreach ($activities as $activity) {
                // Validación básica de contrato USDC
                $contract = $activity['rawContract']['address'] ?? null;
                if (strtolower($contract) !== strtolower($usdcContract))
                    continue;
                // Disparamos el evento para que cualquier otro módulo lo capture
                event(new BlockchainActivityDetected($activity));
            }

        } catch (\Exception $e) {
            // Logueamos el error pero devolvemos 200 para que Alchemy no marque el webhook como "Caído"
            Log::error("Error procesando Webhook de Alchemy: " . $e->getMessage());
        }

        // Si el soporte de Alchemy está probando el endpoint, esto responderá con éxito
        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String()
        ], 200);
    }

    public static function updateWebhookAddresses($webhookId, $authToken, $addresses)
    {
        return Http::withHeaders([
            'X-Alchemy-Token' => $authToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->patch("https://dashboard.alchemy.com/api/update-webhook-addresses", [
                    'webhook_id' => $webhookId,
                    'addresses_to_add' => $addresses,
                    'addresses_to_remove' => [],
                ]);
    }
}
