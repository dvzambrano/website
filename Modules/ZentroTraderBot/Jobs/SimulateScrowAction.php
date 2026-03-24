<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Services\ConfigService;
use Modules\ZentroTraderBot\Services\ScrowMockService;

class SimulateScrowAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $bot;
    protected $token;
    protected $seller;
    protected $buyer;

    public function __construct()
    {
    }

    public function handle()
    {
        $this->tenant = '59d5e7a3-dea0-4289-88f0-a39765f50bcf';
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

        // creamos el trade
        $payload = ScrowMockService::getTradeCreatedPayload(
            $this->tenant,
            $this->seller->getWallet()["address"],
            $this->buyer->getWallet()["address"],
            $this->token['decimals']
        );
        $tradeId = $payload['decoded']['params']['tradeId'];
        $amountWei = $payload['decoded']['params']['amount'];
        $amountHuman = $amountWei / pow(10, $this->token['decimals']);


        ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(1));

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

            // 10% de probabilidad: El trade expira (Vendedor reclama por falta de pago)
            case 2:
                $payload = ScrowMockService::getTradeExpiredPayload(
                    $this->tenant,
                    $tradeId
                );
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(65));
                break;

            // 10% de probabilidad: Alguien abre una disputa
            case 8:
                $address = $this->seller->getWallet()["address"];
                $rand = rand(1, 2);
                if ($rand == 1)
                    $address = $this->buyer->getWallet()["address"];
                // abrir la disputa
                $payload = ScrowMockService::getDisputeOpenedPayload(
                    $this->tenant,
                    $address,
                    $tradeId
                );
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(10));
                // resolverla 
                $address = $this->seller->getWallet()["address"];
                $rand = rand(1, 2);
                if ($rand == 1)
                    $address = $this->buyer->getWallet()["address"];
                $payload = ScrowMockService::getDisputeResolvedPayload(
                    $this->tenant,
                    $address,
                    $tradeId
                );
                ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(15));
                break;

            // simulamos un flujo normal de firma y cierre del trade
            default:
                $rand = rand(1, 2);
                if ($rand == 1) {
                    // firma primero el vendedor (raro pero posible) q recibio el pago
                    $payload = ScrowMockService::getTradeSignedPayload(
                        $this->tenant,
                        $this->seller->getWallet()["address"],
                        $tradeId
                    );
                    ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(2));

                    $payload = ScrowMockService::getTradeSignedPayload(
                        $this->tenant,
                        $this->buyer->getWallet()["address"],
                        $tradeId
                    );
                    ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(4));
                } else {
                    // firma primero el comprador indicando q ya pago
                    $payload = ScrowMockService::getTradeSignedPayload(
                        $this->tenant,
                        $this->buyer->getWallet()["address"],
                        $tradeId
                    );
                    ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(2));

                    $payload = ScrowMockService::getTradeSignedPayload(
                        $this->tenant,
                        $this->seller->getWallet()["address"],
                        $tradeId
                    );
                    ProcessScrowAction::dispatch($payload)->delay(now()->addMinutes(4));
                }

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

        if (env("ESCROW_TEST_MODE", false))
            self::dispatch()->delay(now()->addMinutes(rand(2, 12)));
    }
}