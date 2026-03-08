<?php

namespace Modules\ZentroTraderBot\Contracts;

use Illuminate\Http\Request;

interface BlockchainProviderInterface
{
    public function processWebhook(Request $request): array;
}