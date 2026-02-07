<?php

namespace Modules\ZentroTraderBot\Observers;

use Modules\ZentroTraderBot\Entities\Ramporders;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Jobs\NotifyRampOrder;

class RamporderObserver
{
    /**
     * Se ejecuta cuando se crea una nueva orden.
     */
    public function created(Ramporders $order)
    {
        $this->dispatchNotification($order);
    }

    /**
     * Se ejecuta cuando Transak actualiza el estado (ej: de COMPLETED a FAILED)
     */
    public function updated(Ramporders $order)
    {
        // Solo notificamos si el estado cambió
        if ($order->isDirty('status')) {
            $this->dispatchNotification($order);
        }
    }

    protected function dispatchNotification(Ramporders $order)
    {
        // Recuperamos el bot activo del contenedor (inyectado por el middleware)
        $bot = app('active_bot');

        // Enviamos al Job (esto es instantáneo para la DB)
        NotifyRampOrder::dispatch($order, $bot);
    }
}