<?php

namespace Modules\ZentroTraderBot\Services;

use Illuminate\Support\Str;
use Modules\ZentroTraderBot\Tests\TestCase;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Events\ContractActivityDetected;
use Modules\ZentroTraderBot\Listeners\ProcessContractActivity;
use Modules\Web3\Services\ConfigService;

class ScrowMockService
{
    private static function getTradeId($tradeId = false)
    {
        if (!$tradeId) {
            $id = Offers::latest('id')->value('id');
            if (!$id)
                $id = 0;
            $tradeId = $id + 1;
        }

        return $tradeId;
    }
    private static function getAmount($amount = false)
    {
        if (!$amount)
            $amount = rand(1, 100);
        return $amount;
    }
    private static function getPayload($tenant_code)
    {
        $payload = [
            'network_id' => env("ESCROW_CHAIN"),
            'confirmed' => true,
            'tenant_code' => $tenant_code,
            'tx_hash' => '0x' . Str::random(64),
            'trace_id' => (string) Str::uuid(),
            'log_index' => 0
        ];

        return $payload;
    }

    public static function getTradeCreatedPayload($tenant_code, $seller_address, $buyer_address, $decimals, $amount = false, $tradeId = false)
    {
        $payload = self::getPayload($tenant_code);
        $payload['decoded'] = [
            'name' => 'TradeCreated',
            'params' => [
                'tradeId' => self::getTradeId($tradeId),
                'seller' => $seller_address,
                'buyer' => $buyer_address,
                'amount' => self::getAmount($amount) * pow(10, $decimals)
            ]
        ];
        return $payload;
    }

    public static function getTradeSignedPayload($tenant_code, $signer_address, $tradeId = false)
    {
        $payload = self::getPayload($tenant_code);
        $payload['decoded'] = [
            'name' => 'TradeSigned',
            'params' => [
                'tradeId' => self::getTradeId($tradeId),
                'signer' => $signer_address
            ]
        ];
        return $payload;
    }

    public static function getTradeClosedPayload($tenant_code, $seller_address, $buyer_address, $decimals, $amount = false, $tradeId = false)
    {
        $payload = self::getPayload($tenant_code);
        $payload['decoded'] = [
            'name' => 'TradeClosed',
            'params' => [
                'tradeId' => self::getTradeId($tradeId),
                'seller' => $seller_address,
                'buyer' => $buyer_address,
                'amount' => self::getAmount($amount) * pow(10, $decimals)
            ]
        ];
        return $payload;
    }

    public static function getTradeCancelledPayload($tenant_code, $tradeId = false)
    {
        $payload = self::getPayload($tenant_code);
        $payload['decoded'] = [
            'name' => 'TradeCancelled',
            'params' => ['tradeId' => $tradeId]
        ];
        return $payload;
    }
}