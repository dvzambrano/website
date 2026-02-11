<?php

namespace Modules\ZentroTraderBot\Contracts;

interface RampProviderInterface
{
    public function getWidgetUrl($bot, $suscriptor, $action): ?string;
    public function processWebhook(): array;
}