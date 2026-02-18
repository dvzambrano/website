<?php

namespace Modules\TelegramBot\Middleware;

use Closure;
use Illuminate\Http\Request;

class TelegramIsAuthenticatedMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Si no existe el usuario en la sesión, lo mandamos al landing con un error
        if (!$request->session()->has('telegram_user')) {
            return redirect()->to('/')->with('error', 'Debes iniciar sesión con Telegram para acceder al Dashboard.');
        }

        return $next($request);
    }
}