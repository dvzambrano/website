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
}
