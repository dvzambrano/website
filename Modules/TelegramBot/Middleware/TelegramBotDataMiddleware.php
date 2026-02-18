<?php

namespace Modules\TelegramBot\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\TelegramBot\Entities\TelegramBots;

class TelegramBotDataMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->route('key');

        // 1. Buscamos el bot por la 'key' única (no por el username público)
        $bot = TelegramBots::where('key', $key)->first();

        if (!$bot) {
            return redirect('/')->with('error', 'Acceso no autorizado.');
        }

        // 2. Conectamos al tenant (tu lógica anterior para DB dinámica si aplica)
        $bot->connectToThisTenant();

        // 4. Inyectamos los datos necesarios para el controlador
        // Usamos atributos del request para no ensuciar el input
        $request->attributes->add([
            'bot_token' => $bot->token,
            'active_bot' => $bot
        ]);

        return $next($request);
    }
}