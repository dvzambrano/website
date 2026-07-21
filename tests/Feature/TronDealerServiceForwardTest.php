<?php

namespace Tests\Feature;

use Dvzambrano\TronDealer\Events\TronDealerDepositConfirmed;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TronDealerServiceForwardTest extends TestCase
{
    public function test_default_webhook_does_not_forward(): void
    {
        Http::fake();

        event(new TronDealerDepositConfirmed(
            ['tx_hash' => '0x1'],
            '{"tx_hash":"0x1"}',
            'sig',
            'default'
        ));

        Http::assertNothingSent();
    }

    public function test_service_segment_forwards_to_matching_subdomain(): void
    {
        Http::fake([
            'kashio.*' => Http::response('ok', 200),
        ]);

        event(new TronDealerDepositConfirmed(
            ['tx_hash' => '0x1'],
            '{"tx_hash":"0x1"}',
            'sig-value',
            'kashio'
        ));

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'http://kashio.')
                && str_ends_with($request->url(), '/webhook/trondealer')
                && $request->body() === '{"tx_hash":"0x1"}'
                && $request->hasHeader('X-Signature-256', 'sig-value');
        });
    }

    public function test_real_webhook_request_to_service_segment_triggers_forward(): void
    {
        Http::fake([
            'kashio.*' => Http::response('ok', 200),
        ]);

        $secret = env('TRONDEALER_WEBHOOK_SECRET', '');
        if (empty($secret)) {
            $this->markTestSkipped('TRONDEALER_WEBHOOK_SECRET not configured');
        }

        $payload = [
            'event' => 'transaction.confirmed',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'tx_hash' => '0x' . bin2hex(random_bytes(32)),
                'to_address' => '0x' . bin2hex(random_bytes(20)),
                'amount' => '10.5',
                'asset' => 'USDT',
                'network' => 'bsc',
                'confirmations' => 15,
            ],
        ];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $secret);

        $response = $this->call(
            'POST',
            'http://micalme.com/webhook/trondealer/kashio',
            [], [], [],
            [
                'HTTP_X-Signature-256' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $body
        );

        $response->assertStatus(200);

        Http::assertSent(function ($request) use ($body, $signature) {
            return $request->url() === 'http://kashio.micalme.com/webhook/trondealer'
                && $request->body() === $body
                && $request->hasHeader('X-Signature-256', $signature);
        });
    }
}
