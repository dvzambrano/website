<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\ZentroTraderBot\Entities\Ramporders;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\ZentroTraderBot\Entities\Suscriptions;

class VerifyRampPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    public function __construct(Ramporders $order)
    {
        $this->order = $order;
    }

    /**
     * Define cuánto esperar entre reintentos.
     */
    public function backoff()
    {
        // Primeros intentos rápidos, luego cada 15 minutos
        // Puedes ajustar: [espera_intento_1, espera_intento_2, ...]
        return [60, 300, 600, 900]; // 1min, 5min, 10min, 15min... y luego seguirá en 15min
    }

    public function handle()
    {
        if ($this->attempts() > 300) { // Aproximadamente 3 días
            Log::warning("Orden {$this->order->order_id} expirada tras 300 intentos.");
            $this->delete(); // Elimina el job de la cola
            return;
        }

        $this->order->refresh();

        // Si ya se notificó o se completó (por Webhook), terminamos el Job con éxito
        if ($this->order->notified || strtoupper($this->order->status) === 'COMPLETED') {
            return;
        }

        // --- PLAN B: CONSULTA BLOCKCHAIN ---
        // Aquí llamas a la función que verifica la wallet en Polygonscan
        $suscriptor = Suscriptions::where("user_id", $this->order->user_id)->first();
        if (!$suscriptor || !isset($suscriptor->data["wallet"]["address"])) {
            Log::error("No se pudo encontrar la billetera para el usuario {$this->order->user_id}");
            $this->release(3600); // Reintentar mucho más tarde o fallar
            return;
        }
        $foundOnChain = app('Modules\ZentroTraderBot\Http\Controllers\RampController')->verifyOnChain(
            $suscriptor->data["wallet"]["address"],
            $this->order->amount
        );

        if ($foundOnChain) {
            // Si lo encontramos, marcamos como completado y notificamos
            $this->order->update([
                'status' => 'COMPLETED',
                'notified' => true
            ]);

            // Lógica para enviar mensaje de éxito por Telegram
            $bot = new ZentroTraderBotController($this->order->botname);
            $bot->notifyOnRampConfirmed($this->order);

            return;
        }

        // --- LOGICA DE REINTENTO DINÁMICA SIN LOGS DE ERROR ---
        // Obtenemos el tiempo de espera según el intento actual
        $backoffs = $this->backoff();
        $currentAttempt = $this->attempts(); // Laravel nos dice qué intento es este (1, 2, 3...)

        // Si ya superamos los definidos en el array, usamos el último (900)
        $delay = $backoffs[$currentAttempt - 1] ?? end($backoffs);

        Log::info("Pago no encontrado para orden {$this->order->order_id}. Reintentando en {$delay} segundos (Intento #{$currentAttempt})");

        // Liberamos el job para que vuelva en el tiempo calculado sin lanzar error
        $this->release($delay);
    }
}