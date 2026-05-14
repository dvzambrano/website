<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\BehaviorService;
use Modules\Web3\Http\Controllers\ScanController;
use Modules\ZentroTraderBot\Entities\Suscriptions;

class RegisterMoralisStreams extends Command
{
    protected $signature = 'zentrotraderbot:moralis-init-streams {module=ZentroTraderBot} {--bot=all} {--domain=micalme.com}';
    protected $description = 'Crea los Streams en Moralis y guarda sus IDs en los bots';

    public function handle()
    {
        $apiKey = env("MORALIS_API_KEY");
        $bot = $this->option('bot');
        $domain = $this->option('domain');

        $bots = [];
        if (strtolower($bot) == "all")
            $bots = TelegramBots::where('module', $this->argument('module'))->get();
        else {
            $tenant = TelegramBots::where('name', "@" . $bot)->first();
            $bots[] = $tenant;
        }

        foreach ($bots as $tenant) {
            // --- CONFIGURACIÓN DINÁMICA DE LA CONEXIÓN ---
            $tenant->connectToThisTenant();
            $data = $tenant->data;


            $localAddresses = [env('ESCROW_CONTRACT')];
            $suscriptors = Suscriptions::all();
            foreach ($suscriptors as $suscriptor)
                $localAddresses[] = $suscriptor->getWallet()["address"];

            $addressesFromMoralis = [];
            if (isset($data["moralis_stream_id"])) {
                $streamId = $data["moralis_stream_id"];
                $cursor = null;

                $this->info("📋 Recuperando wallets suscritas a: " . $data["moralis_stream_id"]);
                do {
                    // Preparamos los parámetros de la consulta
                    $queryParams = ['limit' => 100]; // El máximo permitido por Moralis
                    if ($cursor) {
                        $queryParams['cursor'] = $cursor;
                    }

                    $getAddressesResponse = Http::withHeaders(['x-api-key' => $apiKey])
                        ->timeout(BehaviorService::timeout())
                        ->get("https://api.moralis-streams.com/streams/evm/{$streamId}/address", $queryParams);

                    if ($getAddressesResponse->successful()) {
                        $body = $getAddressesResponse->json();

                        // Extraemos las direcciones de esta página
                        $pageAddresses = collect($body['result'])->pluck('address')->toArray();
                        $addressesFromMoralis = array_merge($addressesFromMoralis, $pageAddresses);

                        // Verificamos si hay más páginas
                        $cursor = $body['cursor'] ?? null;

                        if ($cursor) {
                            $this->info("⏬ Cargando siguiente página de wallets...");
                        }
                    } else {
                        $this->error("❌ Falló el respaldo de wallets: " . $getAddressesResponse->body());
                        $cursor = null; // Rompemos el bucle en caso de error
                    }
                } while ($cursor !== null);
                $this->info("✅ Recuperadas " . count($addressesFromMoralis) . " addresses suscritas a: " . $data["moralis_stream_id"]);


                $this->info("🗑 Eliminando Stream existente: " . $data["moralis_stream_id"]);
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(BehaviorService::timeout())
                    ->delete('https://api.moralis-streams.com/streams/evm/' . $data["moralis_stream_id"]);

                if ($response->successful()) {
                    $this->info("🪦 Stream eliminado!");
                    unset($data["moralis_stream_id"]);
                } else {
                    $this->error("❌ Error eliminando Stream: " . $response->body());
                }
            }

            $this->info("🚀 Creando Stream en Moralis para: {$tenant->code}");
            // Obtener el ABI completo del contrato y pasarlo al stream
            $contractAbi = ScanController::getAbi(env('ETHERSCAN_API_KEY'), env('ESCROW_CONTRACT'), env('BASE_NETWORK'));
            $events = array_filter($contractAbi, fn($item) => ($item['type'] ?? '') === 'event');
            $abiEvents = array_values($events);

            $topic0 = array_map(function ($event) {
                $inputs = array_map(fn($input) => $input['type'], $event['inputs'] ?? []);
                return $event['name'] . '(' . implode(',', $inputs) . ')';
            }, $abiEvents);

            // Agregar el Transfer ERC-20 si no está ya incluido
            $transferAbi = [
                'name' => 'Transfer',
                'type' => 'event',
                'anonymous' => false,
                'inputs' => [
                    ['type' => 'address', 'name' => 'from', 'indexed' => true],
                    ['type' => 'address', 'name' => 'to', 'indexed' => true],
                    ['type' => 'uint256', 'name' => 'value', 'indexed' => false],
                ],
            ];
            $transferTopic = 'Transfer(address,address,uint256)';

            if (!in_array($transferTopic, $topic0)) {
                $abiEvents[] = $transferAbi;
                $topic0[] = $transferTopic;
            }

            $payload = [
                'webhookUrl' => "https://" . rtrim($domain, '/') . "/webhook/blockchain/moralis/{$tenant->key}",
                'description' => $tenant->code,
                'chainIds' => [
                    '0x1', // Ethereum
                    '0x89', // Polygon
                    '0x38', // Binance Smart Chain
                    '0xa4b1', // Arbitrum
                    '0x2105', // Base
                    '0xa', // Optimism
                    '0xe708', // Linea
                    '0xa86a', // Avalanche
                    '0xfa', // Fantom
                    '0x15b38', // Chiliz
                    '0x2eb', // Flow
                    '0x7e4', // Ronin
                    '0x46f', // Lisk
                    '0x3e7', // HyperEVM
                    '0x8f', // Monad
                    '0x13882', // Amoy (Test net polygon)
                ],
                'tag' => $tenant->key,
                'includeNativeTxs' => true,        // ✅ Para transferencias nativas (ETH, MATIC, etc.)
                'includeContractLogs' => true,      // ✅ Necesario para detectar ERC-20 transfers
                'allAddresses' => false,            // ✅ Correcto, agregas wallets después
                'filterPossibleSpamAddresses' => true, // ✅ Filtra spam
                'includeInternalTxs' => false, // No necesitas txs internas si solo quieres envíos/recepciones directas
                'abi' => $abiEvents,  // Solo eventos
                'topic0' => $topic0,     // Signatures generadas
            ];

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(BehaviorService::timeout())
                ->put('https://api.moralis-streams.com/streams/evm', $payload);

            if ($response->successful()) {
                $streamId = $response->json('id'); // Moralis devuelve 'id'

                $data["moralis_stream_id"] = $streamId;
                $tenant->data = $data;
                $tenant->save();

                $this->info("✅ Stream creado con ID: {$streamId}");

                // Después de crear el stream exitosamente...

                // Agregar wallets y contratos al stream
                $addresses = collect($localAddresses)
                    ->merge($addressesFromMoralis)
                    // Filtramos posibles valores nulos o vacíos
                    ->filter()
                    // Convertimos a minúsculas para comparar manzanas con manzanas
                    //->map(fn($address) => strtolower(trim($address)))
                    // Eliminamos duplicados
                    ->unique()
                    // Reindexamos el array para que sea un JSON válido [0,1,2...]
                    ->values()
                    ->toArray();

                if (!empty($addresses)) {
                    $addResponse = Http::withHeaders([
                        'x-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                        ->timeout(BehaviorService::timeout())
                        ->post("https://api.moralis-streams.com/streams/evm/{$streamId}/address", [
                            'address' => $addresses,
                        ]);

                    if ($addResponse->successful()) {
                        $this->info("✅ " . count($addresses) . " direcciones agregadas al stream");
                    } else {
                        $this->error("❌ Error agregando direcciones: " . $addResponse->body());
                    }
                }

            } else {
                $this->error("❌ Error creando Stream para {$tenant->code}: " . $response->body());
            }
        }
    }
}