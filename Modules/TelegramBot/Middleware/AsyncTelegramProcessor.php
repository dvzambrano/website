<?php

namespace Modules\TelegramBot\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\TelegramBot\Events\TelegramUpdateReceived;

class AsyncTelegramProcessor
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Este método se ejecuta DESPUÉS de que la respuesta se envía al cliente.
     */
    public function terminate($request, $response)
    {
        $tenant = app('active_bot');
        $update = $request->all();

        if ($tenant && isset($tenant->key)) {
            // Disparamos el evento aquí. 
            // Como el Listener NO tendrá 'ShouldQueue', se ejecutará síncronamente
            // pero el proceso de PHP seguirá vivo mientras Telegram ya tiene su "ok".
            event(new TelegramUpdateReceived($tenant->key, $update));
        }
    }
}