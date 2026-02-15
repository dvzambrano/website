<?php

namespace Modules\ZentroTraderBot\Contracts;

use Illuminate\Http\Request;

interface RampProviderInterface
{
    public function getWidgetUrl($bot, $suscriptor, $action): ?string;
    public function processWebhook(Request $request): array;
}