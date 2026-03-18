<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Modules\ZentroTraderBot\Tests\TestCase;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Events\ContractActivityDetected;
use Modules\ZentroTraderBot\Listeners\ProcessContractActivity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Web3\Services\ConfigService;

class ProcessContractActivityTest extends TestCase
{
    private $bot;
    private $token;
    private $seller;
    private $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantCode = '59d5e7a3-dea0-4289-88f0-a39765f50bcf';
        // 1. Aseguramos que el bot existe y conectamos
        $this->bot = TelegramBots::where('key', $tenantCode)->first();
        if (!$this->bot)
            $this->fail("El bot con key $tenantCode no existe.");
        $this->bot->connectToThisTenant();

        //{"token":{"chainId":137,"address":"0x8f3cf7ad23cd3cadbd9735aff958023239c6a063","decimals":18,"symbol":"DAI","name":"(PoS) Dai Stablecoin","logoURI":"https://tokens.1inch.io/0x6b175474e89094c44da98b954eedeac495271d0f.png","eip2612":true,"tags":[]}} 
        $this->token = ConfigService::getToken(env('BASE_TOKEN'), env('BASE_NETWORK'));

        $this->seller = Suscriptions::on('tenant')->where("id", 1)->first();
        $this->buyer = Suscriptions::on('tenant')->where("id", 2)->first();

        // Offers::on('tenant')->max('id') + 1;
    }

    public function test_it_processes_trade_created_event_and_creates_offer()
    {
        // Limpiamos ofertas previas si existen
        Offers::on('tenant')->truncate();

        $amount = 100; // DAI

        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => 'TradeCreated',
                'params' => [
                    'tradeId' => 1,
                    'seller' => $this->seller->getWallet()["address"],
                    'amount' => $amount * pow(10, $this->token['decimals'])
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 4. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        $listener = new ProcessContractActivity();
        $listener->handle($event);

        // 5. VERIFICACIONES
        $offer = Offers::on('tenant')->where('blockchain_trade_id', 1)->first();

        $this->assertNotNull($offer, "La oferta debería haberse creado en la DB.");
        $this->assertEquals('sell', $offer->type);
        $this->assertEquals($amount, (float) $offer->amount, "El monto no se normalizó correctamente.");
        $this->assertEquals($this->seller->user_id, $offer->user_id, "No se vinculó correctamente el user_id del suscriptor.");
        $this->assertEquals('active', $offer->status);
    }

    public function test_it_processes_trade_applied_event_and_update_offer()
    {
        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => "TradeApplied",
                'params' => [
                    'tradeId' => 1,
                    'buyer' => $this->buyer->getWallet()["address"],
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 4. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        $listener = new ProcessContractActivity();
        $listener->handle($event);

        $offer = Offers::on('tenant')->where('blockchain_trade_id', 1)->first();

        $offer->refresh();
        $this->assertEquals('locked', strtolower($offer->status));
        $this->assertEquals(strtolower($this->buyer->getWallet()["address"]), $offer->buyer_address);
    }

    public function test_it_processes_disputed_oppened_event_and_update_offer()
    {
        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => "DisputeOpened",
                'params' => [
                    'tradeId' => 1,
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 4. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        $listener = new ProcessContractActivity();
        $listener->handle($event);

        $offer = Offers::on('tenant')->where('blockchain_trade_id', 1)->first();

        $offer->refresh();
        $this->assertEquals('disputed', strtolower($offer->status));
    }

    public function test_it_processes_disputed_resolved_event_and_update_offer()
    {
        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => "DisputeResolved",
                'params' => [
                    'tradeId' => 1,
                    'winner' => $this->buyer->getWallet()["address"],
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 4. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        $listener = new ProcessContractActivity();
        $listener->handle($event);

        $offer = Offers::on('tenant')->where('blockchain_trade_id', 1)->first();
        $this->assertEquals('completed', strtolower($offer->status));
    }

    public function test_it_handles_trade_cancellation()
    {
        $amount = 1700; // DAI

        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => 'TradeCreated',
                'params' => [
                    'tradeId' => 1700,
                    'seller' => $this->seller->getWallet()["address"],
                    'amount' => $amount * pow(10, $this->token['decimals'])
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 4. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        $listener = new ProcessContractActivity();
        $listener->handle($event);


        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => 'TradeCancelled',
                'params' => [
                    'tradeId' => 1700,
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 5. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        $listener = new ProcessContractActivity();
        $listener->handle($event);
        $offer = Offers::on('tenant')->where('blockchain_trade_id', 1700)->first();
        $this->assertEquals('cancelled', $offer->status);
    }

}