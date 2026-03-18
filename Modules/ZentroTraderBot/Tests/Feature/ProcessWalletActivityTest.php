<?php

namespace Tests\Feature;

use Modules\Web3\Events\WalletActivityDetected;
use Modules\ZentroTraderBot\Listeners\ProcessWalletActivity;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Services\ConfigService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Modules\ZentroTraderBot\Tests\TestCase;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;

class ProcessWalletActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        //Cache::flush();
    }

    public function test_it_processes_native_deposit_correctly()
    {
        //fwrite(STDOUT, "\n[INFO] ".);
        $hash = '0x_' . md5(date("YmdHis"));
        $data = [
            'network_id' => 80002,
            'confirmed' => true,
            'token_symbol' => 'POLYGONAMOY', // Ya normalizado para evitar fallos de ConfigService
            'token_address' => '',
            'tx_hash' => $hash,
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'value' => rand(2, 9),
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid()
        ];

        $event = new WalletActivityDetected($data);
        $listener = new ProcessWalletActivity();
        $listener->handle($event);

        $this->assertTrue(Cache::has('tx_processed_' . $hash), "La caché $hash no se guardó.");
    }


    public function test_it_escapes_if_native_token_symbol_cannot_be_resolved()
    {

        $hash = '0x_' . md5(date("YmdHis") . rand(1, 9));
        $data = [
            'network_id' => 1234567890,
            'confirmed' => true,
            'token_symbol' => '', // Obligatorio: vacío para que intente resolverlo
            'tx_hash' => $hash,
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid(),
            'token_address' => '', // Al estar vacío, entra en el "Caso B" (Nativo)
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'value' => 1,
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        // Ahora sí debería ser FALSE porque el código hace: 
        // if (empty($data['token_symbol'])) return;
        $this->assertFalse(Cache::has('tx_processed_' . $hash));
    }

    public function test_it_blocks_scam_tokens_not_in_whitelist()
    {
        $data = [
            'confirmed' => true,
            'token_address' => '0x_contrato_falso',
            'network_id' => 137,
            'tx_hash' => '0x_hash_scam',
            'trace_id' => (string) Str::uuid(),
            'token_symbol' => 'POLYGONAMOY', // Ya normalizado para evitar fallos de ConfigService
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'value' => rand(2, 9),
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        $this->assertFalse(Cache::has('tx_processed_0x_hash_scam'));
    }


    public function test_it_prevents_duplicate_processing_of_same_hash()
    {
        $hash = '0x_unique_hash_idempotency';
        $data = [
            'network_id' => 80002,
            'confirmed' => true,
            'token_symbol' => 'POLYGONAMOY',
            'tx_hash' => $hash,
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'value' => 1,
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid(),
            'token_address' => '',
        ];

        $event = new WalletActivityDetected($data);
        $listener = new ProcessWalletActivity();

        // Primer intento: Debe retornar true (proceso exitoso)
        $listener->handle($event);
        $this->assertTrue(Cache::has('tx_processed_' . $hash));

        // Segundo intento: Simulamos que llega de nuevo el mismo hash
        // El listener debería "escapar" por el cacheKey existente sin lanzar errores.
        $listener->handle($event);

        // Verificamos que el contador de caché no se haya roto y siga ahí
        $this->assertTrue(Cache::has('tx_processed_' . $hash));
    }


    public function test_it_escapes_if_bot_does_not_exist()
    {
        $data = [
            'network_id' => 80002,
            'confirmed' => true,
            'tx_hash' => '0x_non_existent_bot_hash',
            'tenant_code' => 'codigo-que-no-existe-en-db',
            'trace_id' => (string) Str::uuid(),
            'token_symbol' => 'POLYGONAMOY', // Ya normalizado para evitar fallos de ConfigService
            'token_address' => '',
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'value' => rand(2, 9),
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        // No debería haberse guardado nada en caché porque el bot falló
        $this->assertFalse(Cache::has('tx_processed_0x_non_existent_bot_hash'));
    }


    public function test_it_escapes_if_no_subscriber_found_for_address()
    {
        $data = [
            'network_id' => 80002,
            'confirmed' => true,
            'token_symbol' => 'POLYGONAMOY',
            'tx_hash' => '0x_no_subscriber_hash',
            'to' => '0x_wallet_que_nadie_tiene_registrada',
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid(),
            'token_address' => '',
            'value' => rand(2, 9),
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        $this->assertFalse(Cache::has('tx_processed_0x_no_subscriber_hash'));
    }


    public function test_it_forgets_cache_if_notification_fails()
    {
        $hash = '0x_failed_notification_hash';
        $data = [
            'network_id' => 80002,
            'confirmed' => true,
            'token_symbol' => 'POLYGONAMOY',
            'tx_hash' => $hash,
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid(),
            'value' => 1,
            'token_address' => ''
        ];

        // Mockeamos el controlador para que lance una excepción
        $mockController = Mockery::mock(ZentroTraderBotController::class);
        $mockController->shouldReceive('notifyDepositConfirmed')->andThrow(new \Exception("Telegram Error"));
        app()->instance(ZentroTraderBotController::class, $mockController);

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        // La caché debe estar VACÍA porque el catch hizo Cache::forget()
        $this->assertFalse(Cache::has('tx_processed_' . $hash), "La caché debería haberse borrado tras el fallo.");
    }


    public function test_it_ignores_unconfirmed_transactions()
    {
        $data = [
            'confirmed' => false, // TX en estado pendiente
            'tx_hash' => '0x_pending_hash',
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid()
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        $this->assertFalse(Cache::has('tx_processed_0x_pending_hash'));
    }


    public function test_it_handles_uppercase_wallets_correctly()
    {
        $hash = '0x_checksum_test';
        $data = [
            'network_id' => 80002,
            'confirmed' => true,
            'token_symbol' => 'POLYGONAMOY',
            'tx_hash' => $hash,
            'to' => '0xD4B4E6DD4134CE09D910AAA3583BBE5D1172220D', // EN MAYÚSCULAS
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid(),
            'value' => 1,
            'token_address' => ''
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        // Si tu SQL LOWER(...) funciona, esto será TRUE
        $this->assertTrue(Cache::has('tx_processed_' . $hash));
    }


    public function test_it_processes_real_usdc_deposit_on_polygon_mainnet()
    {
        // Dirección oficial de USDC (PoS) en Polygon Mainnet
        $polygonUsdcAddress = '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174';
        $networkId = 137; // ID de Polygon Mainnet
        $hash = '0x_usdc_mainnet_test_' . Str::random(10);
        $tenantCode = '59d5e7a3-dea0-4289-88f0-a39765f50bcf';
        $userWallet = '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d';

        // 1. Aseguramos que el bot existe y conectamos
        $bot = TelegramBots::where('key', $tenantCode)->first();
        if (!$bot)
            $this->fail("El bot con key $tenantCode no existe.");
        $bot->connectToThisTenant();

        $data = [
            'network_id' => $networkId,
            'confirmed' => true,
            'token_address' => $polygonUsdcAddress,
            'tx_hash' => $hash,
            'to' => $userWallet,
            'value' => 50.0, // 50 USDC
            'tenant_code' => $tenantCode,
            'trace_id' => (string) Str::uuid(),
            'token_symbol' => 'TEMP' // Debería cambiar a 'USDC' tras el ConfigService
        ];

        $event = new WalletActivityDetected($data);
        $listener = new ProcessWalletActivity();
        $listener->handle($event);

        // Verificación
        $this->assertTrue(
            Cache::has('tx_processed_' . $hash),
            "El USDC de Mainnet fue rechazado. Verifica si el ConfigService tiene el token $polygonUsdcAddress en la red $networkId."
        );
    }


    public function test_it_handles_micro_deposits_without_exploding()
    {
        $hash = '0x_micro_test_' . Str::random(5);
        $data = [
            'network_id' => 137,
            'confirmed' => true,
            'token_symbol' => 'USDC',
            'tx_hash' => $hash,
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'value' => 0.00000012345, // Mucho más de 4 decimales
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid()
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        $this->assertTrue(Cache::has('tx_processed_' . $hash));
    }


    public function test_it_handles_micro_deposits_correctly()
    {
        $hash = '0x_micro_test_' . Str::random(8);
        $data = [
            'network_id' => 137,
            'confirmed' => true,
            'token_symbol' => 'USDC',
            'tx_hash' => $hash,
            'to' => '0xd4b4e6dd4134ce09d910aaa3583bbe5d1172220d',
            'value' => 0.00000012, // Valor ínfimo
            'tenant_code' => '59d5e7a3-dea0-4289-88f0-a39765f50bcf',
            'trace_id' => (string) Str::uuid()
        ];

        $listener = new ProcessWalletActivity();
        $listener->handle(new WalletActivityDetected($data));

        // Verificamos que se haya marcado como procesado a pesar de ser un valor bajo
        $this->assertTrue(Cache::has('tx_processed_' . $hash));
    }

}