<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\BehaviorService;

class RegisterMoralisStreams extends Command
{
    protected $signature = 'zentrotraderbot:moralis-init-streams {module=ZentroTraderBot} {--domain=micalme.com}';
    protected $description = 'Crea los Streams en Moralis y guarda sus IDs en los bots';

    public function handle()
    {
        $apiKey = env("MORALIS_API_KEY");
        $domain = $this->option('domain');

        $bots = TelegramBots::where('module', $this->argument('module'))->get();

        foreach ($bots as $tenant) {
            /*
            if (isset($tenant->data["moralis_stream_id"])) {
                $this->info("🗑 Eliminando Stream existente: " . $tenant->data["moralis_stream_id"]);
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(BehaviorService::timeout())
                    ->delete('https://api.moralis-streams.com/streams/evm/' . $tenant->data["moralis_stream_id"]);

                if ($response->successful()) {
                    $this->info("🪦 Stream eliminado!");
                } else {
                    $this->error("❌ Error eliminando Stream: " . $response->body());
                }
            }
            */

            $this->info("🚀 Creando Stream en Moralis para: {$tenant->code}");
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
                ],
                'tag' => $tenant->token,
                'allAddresses' => false, // IMPORTANTE: Ponemos false para ir agregando direcciones luego
                'includeNativeTxs' => true,
                'includeContractLogs' => true,
                'includeInternalTxs' => true,
                'filterPossibleSpamAddresses' => true,
                'abi' => [
                    [
                        'name' => 'Transfer',
                        'type' => 'event',
                        'anonymous' => false,
                        'inputs' => [
                            [
                                'type' => 'address',
                                'name' => 'from',
                                'indexed' => true,
                            ],
                            [
                                'type' => 'address',
                                'name' => 'to',
                                'indexed' => true,
                            ],
                            [
                                'type' => 'uint256',
                                'name' => 'value',
                                'indexed' => false,
                            ],
                        ],
                    ],
                ],
                'topic0' => ['Transfer(address,address,uint256)'],
            ];

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout(BehaviorService::timeout())
                ->put('https://api.moralis-streams.com/streams/evm', $payload);

            if ($response->successful()) {
                $streamId = $response->json('id'); // Moralis devuelve 'id'

                $data = $tenant->data;
                $data["moralis_stream_id"] = $streamId;
                $tenant->data = $data;
                $tenant->save();

                $this->info("✅ Stream creado con ID: {$streamId}");
            } else {
                $this->error("❌ Error creando Stream para {$tenant->code}: " . $response->body());
            }
        }
    }
}