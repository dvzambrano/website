<?php

namespace Modules\ZentroTraderBot\Observers;

use Modules\ZentroTraderBot\Entities\Ramporders;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Jobs\VerifyRampPaymentJob;

class RamporderObserver
{
    /**
     * Manejar el evento "created" de la Orden.
     */
    public function created(Ramporders $order): void
    {
        // Disparamos el Job para que empiece a monitorear en 1 minuto si esta confirmada la orden
        VerifyRampPaymentJob::dispatch($order)->delay(now()->addMinutes(1));
    }
}