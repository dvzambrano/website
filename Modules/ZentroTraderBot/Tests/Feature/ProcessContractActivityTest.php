<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Tests\TestCase;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Events\ContractActivityDetected;
use Modules\ZentroTraderBot\Listeners\ProcessContractActivity;
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

        // Asumimos que los IDs 1 y 2 existen en la DB de pruebas del tenant
        $this->seller = Suscriptions::on('tenant')->find(1);
        $this->buyer = Suscriptions::on('tenant')->find(2);
    }

    public function test_it_processes_trade_created_event_with_buyer_and_locks_it()
    {
        Offers::on('tenant')->truncate();

        $amount = 100; // 100 DAI
        $tradeId = 1;

        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => 'TradeCreated',
                'params' => [
                    'tradeId' => $tradeId,
                    'seller' => $this->seller->getWallet()["address"],
                    'buyer' => $this->buyer->getWallet()["address"], // Nuevo en este contrato
                    'amount' => $amount * pow(10, $this->token['decimals'])
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 4. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        (new ProcessContractActivity())->handle($event);

        // 5. VERIFICACIONES
        $offer = Offers::on('tenant')->where('blockchain_trade_id', $tradeId)->first();

        $this->assertNotNull($offer);
        $this->assertEquals('LOCKED', strtoupper($offer->status), "El nuevo contrato bloquea fondos al crear.");
        $this->assertEquals($amount, (float) $offer->amount);
        $this->assertEquals(strtolower($this->buyer->getWallet()["address"]), strtolower($offer->buyer_address));
    }

    public function test_it_handles_trade_signed_by_party()
    {
        // Simulamos que ya existe un trade #5
        $tradeId = 5;
        Offers::on('tenant')->create([
            'blockchain_trade_id' => $tradeId,
            'status' => 'LOCKED',
            'amount' => 50,
            'seller_address' => $this->seller->getWallet()["address"],
            'buyer_address' => $this->buyer->getWallet()["address"],
            'user_id' => $this->seller->user_id,
            'min_limit' => 1,
            'price_per_usd' => 1,
            'payment_method' => 'Test',
        ]);

        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => 'TradeSigned',
                'params' => [
                    'tradeId' => $tradeId,
                    'signer' => $this->seller->getWallet()["address"]
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];
        // 4. Ejecutamos el Listener
        $event = new ContractActivityDetected($payload);
        (new ProcessContractActivity())->handle($event);

        // El estado no cambia con una sola firma, pero verificamos que no rompa nada
        $offer = Offers::on('tenant')->where('blockchain_trade_id', $tradeId)->first();
        $this->assertEquals('LOCKED', strtoupper($offer->status));
    }

    public function test_it_completes_offer_on_trade_closed()
    {
        $tradeId = 10;
        Offers::on('tenant')->create([
            'blockchain_trade_id' => $tradeId,
            'status' => 'LOCKED',
            'amount' => 10,
            'user_id' => $this->seller->user_id,
            'min_limit' => 1,
            'price_per_usd' => 1,
            'payment_method' => 'Test',
        ]);

        $txHashFinal = '0x' . Str::random(64);
        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => $txHashFinal,
            'decoded' => [
                'name' => 'TradeClosed',
                'params' => [
                    'tradeId' => $tradeId,
                    'seller' => $this->seller->getWallet()["address"],
                    'buyer' => $this->buyer->getWallet()["address"],
                    'amount' => 10 * pow(10, 18)
                ]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];

        // 4. Ejecutamos el Listener
        (new ProcessContractActivity())->handle(new ContractActivityDetected($payload));

        $offer = Offers::on('tenant')->where('blockchain_trade_id', $tradeId)->first();
        $this->assertEquals('COMPLETED', strtoupper($offer->status));
        $this->assertEquals($txHashFinal, $offer->tx_hash_release);
    }

    public function test_it_processes_dispute_flow_to_completion()
    {
        $tradeId = 99;
        $offer = Offers::on('tenant')->create([
            'blockchain_trade_id' => $tradeId,
            'status' => 'LOCKED',
            'amount' => 20,
            'user_id' => $this->seller->user_id,
            'min_limit' => 1,
            'price_per_usd' => 1,
            'payment_method' => 'Test',
        ]);

        // 1. Abrir Disputa
        $payloadDispute = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => 'DisputeOpened',
                'params' => ['tradeId' => $tradeId, 'opener' => $this->buyer->getWallet()["address"]]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];

        (new ProcessContractActivity())->handle(new ContractActivityDetected($payloadDispute));

        // CORRECCIÓN AQUÍ: strtoupper para que coincida con 'DISPUTED'
        $this->assertEquals('DISPUTED', strtoupper($offer->refresh()->status));

        // 2. Resolver Disputa
        $txWinner = '0x' . Str::random(64);
        $payloadResolve = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => $txWinner,
            'decoded' => [
                'name' => 'DisputeResolved',
                'params' => ['tradeId' => $tradeId, 'winner' => $this->buyer->getWallet()["address"]]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 1
        ];

        (new ProcessContractActivity())->handle(new ContractActivityDetected($payloadResolve));

        $offer->refresh();
        $this->assertEquals('COMPLETED', strtoupper($offer->status));
        $this->assertEquals($txWinner, $offer->tx_hash_release);
    }

    public function test_it_handles_trade_cancelled_by_seller()
    {
        $tradeId = 500;
        Offers::on('tenant')->create([
            'blockchain_trade_id' => $tradeId,
            'status' => 'LOCKED',
            'amount' => 1.5,
            'user_id' => $this->seller->user_id,
            'min_limit' => 1,
            'price_per_usd' => 1,
            'payment_method' => 'Test',
        ]);

        $payload = [
            'network_id' => 137,
            'confirmed' => true,
            'tenant_code' => $this->bot->key,
            'tx_hash' => '0x' . Str::random(64),
            'decoded' => [
                'name' => 'TradeCancelled',
                'params' => ['tradeId' => $tradeId]
            ],
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];

        // 4. Ejecutamos el Listener
        (new ProcessContractActivity())->handle(new ContractActivityDetected($payload));
        $this->assertEquals('CANCELLED', strtoupper(Offers::on('tenant')->where('blockchain_trade_id', $tradeId)->first()->status));
    }
}