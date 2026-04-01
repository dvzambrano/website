<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\Web3\Services\ConfigService;
use Modules\ZentroTraderBot\Services\ScrowMockService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\TelegramBot\Http\Controllers\TelegramController;

class SimulateScrowAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $bot;
    protected $token;
    protected $seller;
    protected $buyer;

    public function __construct($tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle()
    {
        // 1. Aseguramos que el bot existe y conectamos
        $this->bot = TelegramBots::where('key', $this->tenant)->first();
        $this->bot->connectToThisTenant();

        //{"token":{"chainId":137,"address":"0x8f3cf7ad23cd3cadbd9735aff958023239c6a063","decimals":18,"symbol":"DAI","name":"(PoS) Dai Stablecoin","logoURI":"https://tokens.1inch.io/0x6b175474e89094c44da98b954eedeac495271d0f.png","eip2612":true,"tags":[]}} 
        $this->token = ConfigService::getToken(env('BASE_TOKEN'), env('BASE_NETWORK'));

        // Asumimos que los IDs 1 y 2 existen en la DB de pruebas del tenant
        $seller = rand(1, 2);
        $buyer = 1;
        if ($seller == 1)
            $buyer = 2;
        $this->seller = Suscriptions::on('tenant')->find($seller);
        $this->buyer = Suscriptions::on('tenant')->find($buyer);


        // --- NUEVO PASO: SIMULAR CREACIÓN DE OFERTA EN DB ---
        // Esto es lo que antes hacía el Wizard y ahora el Job inicia aquí.
        $type = rand(1, 2) == 1 ? 'sell' : 'buy';
        $amountHuman = rand(10, 100);

        $offer = new Offers([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->seller->user_id,
            'type' => $type,
            'amount' => $amountHuman,
            'price_per_usd' => rand(150, 200), // Ejemplo para CUP/MLC o similar
            'currency' => 'CUP',
            'payment_method' => 'Transferencia Bancaria',
            'payment_details' => "Cuenta: 9223 " . rand(1000, 9999) . " " . rand(1000, 9999) . " " . rand(1000, 9999),
            'status' => 'open',
            'network_id' => env('BASE_NETWORK'),
            'token_address' => env('BASE_TOKEN'),
        ]);
        // Asignamos los componentes aleatorios del código de soporte
        $offer->data = [
            "code" => [
                "prefix" => collect(range('A', 'Z'))->random(),
                "suffix" => Str::upper(Str::random(1))
            ]
        ];
        // Guardamos para disparar el ID autoincremental
        $offer->save();

        // 3. ENVÍO A TELEGRAM
        $response = TelegramController::sendMessage(
            $offer->getAsChannelMessage($this->bot->code),
            $this->bot->token
        );
        if ($response) {
            $array = json_decode($response, true);
            $messageId = $array["result"]["message_id"] ?? null;

            // 4. SEGUNDO GUARDADO (Actualización): Guardamos el ID del mensaje y activamos la oferta
            $currentData = $offer->data;
            $currentData["channel"] = ["message_id" => $messageId];
            $offer->update([
                'data' => $currentData
            ]);
        }

        // 2. Simulamos el Payload de TradeCreated (Como si el contrato detectara el bloqueo)
        $payload = ScrowMockService::getTradeCreatedPayload(
            $this->tenant,
            $this->seller->getWallet()["address"],
            $this->buyer->getWallet()["address"],
            $this->token['decimals'],
            false,
            $offer->id
        );

        $tradeId = $payload['decoded']['params']['tradeId'];

        // Ejecutamos el procesamiento del Scrow (esto activará notificaciones en el bot)
        ProcessScrowAction::dispatch($payload)->delay(now()->addSeconds(30));

        // 3. FLUJO DE ACCIONES ALEATORIAS (Igual que antes pero usando el tradeId generado)
        $action = rand(1, 10);
        switch ($action) {
            // 10% de probabilidad: El Comprador se arrepiente y cancela
            case 1:
                $payload = ScrowMockService::getTradeCancelledPayload(
                    $this->tenant,
                    $tradeId
                );
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(2));
                break;

            // 10% de probabilidad: El trade expira
            case 2:
                $payload = ScrowMockService::getTradeExpiredPayload(
                    $this->tenant,
                    $tradeId
                );
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(65));
                break;

            // 10% de probabilidad: Disputa
            case 8:
                $address = rand(1, 2) == 1 ? $this->seller->getWallet()["address"] : $this->buyer->getWallet()["address"];

                // Abrir la disputa
                $payload = ScrowMockService::getDisputeOpenedPayload($this->tenant, $address, $tradeId);
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(10));

                // Resolverla a favor de uno al azar
                $winner = rand(1, 2) == 1 ? $this->seller->getWallet()["address"] : $this->buyer->getWallet()["address"];
                $payload = ScrowMockService::getDisputeResolvedPayload($this->tenant, $winner, $tradeId);
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(15));
                break;

            // Flujo normal: Firmas de ambas partes y cierre
            default:
                if (rand(1, 2) == 1) {
                    // Firma vendedor -> Comprador
                    $p1 = ScrowMockService::getTradeSignedPayload($this->tenant, $this->seller->getWallet()["address"], $tradeId);
                    $p2 = ScrowMockService::getTradeSignedPayload($this->tenant, $this->buyer->getWallet()["address"], $tradeId);
                } else {
                    // Firma comprador -> Vendedor
                    $p1 = ScrowMockService::getTradeSignedPayload($this->tenant, $this->buyer->getWallet()["address"], $tradeId);
                    $p2 = ScrowMockService::getTradeSignedPayload($this->tenant, $this->seller->getWallet()["address"], $tradeId);
                }

                ProcessScrowAction::dispatch($p1)->delay(now()->addMinutes(2));
                ProcessScrowAction::dispatch($p2)->delay(now()->addMinutes(4));

                $payload = ScrowMockService::getTradeClosedPayload(
                    $this->tenant,
                    $this->seller->getWallet()["address"],
                    $this->buyer->getWallet()["address"],
                    $this->token['decimals'],
                    $amountHuman,
                    $tradeId
                );
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(6));
                break;
        }

        // --- LÓGICA DE RECURSIVIDAD Y PARADA ---
        $jobName = strtolower(class_basename($this));
        $stopKey = "stop_job_{$jobName}_{$this->tenant}";

        if (Cache::has($stopKey)) {
            Log::info("🛑 Cadena interrumpida para {$jobName} en Tenant {$this->tenant}");
            Cache::forget($stopKey);
            return;
        }

        // Re-despachamos el Job para mantener la simulación viva
        self::dispatch($this->tenant)->delay(now()->addMinutes(rand(5, 15)));
    }
}