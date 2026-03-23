<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\BehaviorService;
use Modules\ZentroTraderBot\Entities\Suscriptions;

class GetMoralisStreamAdresses extends Command
{
    protected $signature = 'zentrotraderbot:moralis-get-stream-addresses {bot=KashioBot}';
    protected $description = 'Obtiene todas las direcciones de un Stream de Moralis';

    public function handle()
    {

        $tenant = TelegramBots::where('name', '@' . $this->argument('bot'))->first();
        // --- CONFIGURACIÓN DINÁMICA DE LA CONEXIÓN ---
        $tenant->connectToThisTenant();
        $data = $tenant->data;

        $localAddresses = [env('ESCROW_CONTRACT')];
        $suscriptors = Suscriptions::all();
        foreach ($suscriptors as $suscriptor)
            $localAddresses[] = $suscriptor->getWallet()["address"];

        $apiKey = env("MORALIS_API_KEY");
        $addressesFromMoralis = [];
        if (isset($data["moralis_stream_id"])) {
            $streamId = $data["moralis_stream_id"];
            $cursor = null;

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

            // 2. Fusionamos con las respaldadas de Moralis ($addressesFromMoralis viene del bucle anterior)
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

            // var_dump($addresses);
            $this->info("📦 Total de wallets respaldadas: " . count($addresses));
        }
    }
}