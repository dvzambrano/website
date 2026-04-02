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
use Modules\Laravel\Services\Exchange\CambiocupService;

class SimulateScrowAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fast;
    protected $tenant;
    protected $bot;
    protected $token;
    protected $seller;
    protected $buyer;

    public function __construct($tenant, $fast = false)
    {
        $this->tenant = $tenant;
        $this->fast = $fast;
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

        // 2. Crear Oferta
        $type = rand(1, 2) == 1 ? 'sell' : 'buy';
        $amountHuman = rand(10, 200);

        $price = CambiocupService::getRate("cup");
        if (rand(1, 2) == 1)
            $price = $price + ($price * rand(5, 10) / 100);
        else
            $price = $price - ($price * rand(1, 5) / 100);


        $offer = new Offers([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->seller->user_id,
            'type' => $type,
            'amount' => $amountHuman,
            'price_per_usd' => $price, // Ejemplo para CUP/MLC o similar
            'currency' => 'CUP',
            'payment_method' => 'Transferencia Bancaria',
            'payment_details' => "9223 " . rand(1000, 9999) . " " . rand(1000, 9999) . " " . rand(1000, 9999),
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

        // 3. Telegram: Publicación inicial
        $response = TelegramController::sendMessage($offer->getAsChannelMessage($this->bot->code), $this->bot->token);
        if ($response) {
            $array = json_decode($response, true);
            $offer->update(['data' => array_merge($offer->data, ["channel" => ["message_id" => $array["result"]["message_id"] ?? null]])]);
        }

        // --- DINAMISMO DE TIEMPOS ---
        // $delay es nuestro acumulador de minutos para que los eventos no se solapen
        $delay = rand(2, 5); // Alguien ve la oferta en el canal y le da a "Aplicar" en 2-5 min
        if ($this->fast)
            $delay = 1;

        // Evento: TradeCreated (Bloqueo de fondos en Escrow)
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
        ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes($delay));

        // 4. FLUJO DE ACCIONES ALEATORIAS
        $action = rand(1, 10);
        switch ($action) {
            case 1: // El Comprador se arrepiente rápido (Cancel)
                $delay += 1;
                if (!$this->fast)
                    $delay += rand(1, 2);
                $payload = ScrowMockService::getTradeCancelledPayload($this->tenant, $tradeId);
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes($delay));
                break;

            case 2: // El trade expira (Simulado)
                // Para que sea realista, el Job de expiración debería ser mucho después
                $extra = 5;
                if (!$this->fast)
                    $extra = rand(61, 69);
                $payload = ScrowMockService::getTradeExpiredPayload($this->tenant, $tradeId);
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes($delay + $extra));
                break;

            case 8: // Disputa: El proceso se vuelve lento
                $delay += 1;
                if (!$this->fast)
                    $delay += rand(5, 10); // Pasa un tiempo antes de que alguien reclame
                $address = rand(1, 2) == 1 ? $this->seller->getWallet()["address"] : $this->buyer->getWallet()["address"];

                $p1 = ScrowMockService::getDisputeOpenedPayload($this->tenant, $address, $tradeId);
                ProcessScrowAction::dispatch($p1)->delay(now()->addMinutes($delay));

                $delay += 1;
                if (!$this->fast)
                    $delay += rand(5, 15); // El administrador de Kashio tarda en resolver
                $winner = rand(1, 2) == 1 ? $this->seller->getWallet()["address"] : $this->buyer->getWallet()["address"];
                $p2 = ScrowMockService::getDisputeResolvedPayload($this->tenant, $winner, $tradeId);
                ProcessScrowAction::dispatch($p2)->delay(now()->addMinutes($delay));
                break;

            default: // Flujo Feliz (Firmas y Cierre)
                // Firma 1: Alguien confirma que envió/recibió el pago (5-15 min después del bloqueo)
                $delay += 1;
                if (!$this->fast)
                    $delay += rand(5, 10);
                $signer1 = rand(1, 2) == 1 ? $this->seller : $this->buyer;
                $p1 = ScrowMockService::getTradeSignedPayload($this->tenant, $signer1->getWallet()["address"], $tradeId);
                ProcessScrowAction::dispatch($p1)->delay(now()->addMinutes($delay));

                // Firma 2: La otra parte verifica el banco y firma (10-25 min después de la primera firma)
                $delay += 2;
                if (!$this->fast)
                    $delay += rand(5, 10);
                $signer2 = ($signer1->id == $this->seller->id) ? $this->buyer : $this->seller;
                $p2 = ScrowMockService::getTradeSignedPayload($this->tenant, $signer2->getWallet()["address"], $tradeId);
                ProcessScrowAction::dispatch($p2)->delay(now()->addMinutes($delay));

                // Cierre: El contrato libera los fondos (Casi inmediato tras la 2da firma)
                $delay += 3;
                $payload = ScrowMockService::getTradeClosedPayload(
                    $this->tenant,
                    $this->seller->getWallet()["address"],
                    $this->buyer->getWallet()["address"],
                    $this->token['decimals'],
                    $amountHuman,
                    $tradeId
                );
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes($delay));
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

        if ($this->fast)
            // Próxima oferta en el canal entre 1 y 3 min (pruebas rapidas)
            self::dispatch($this->tenant, $this->fast)->delay(now()->addMinutes(rand(1, 3)));
        else
            // Próxima oferta en el canal entre 5 y 30 min (para que no parezca spam)
            self::dispatch($this->tenant, $this->fast)->delay(now()->addMinutes(rand(5, 30)));
    }
}