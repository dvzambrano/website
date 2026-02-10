<?php

namespace Modules\TelegramBot\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Modules\TelegramBot\Entities\TelegramBots;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->route('key');
        $secret = $request->route('secret');
        $headerToken = $request->header('X-Telegram-Bot-Api-Secret-Token') ?? $secret;

        // Buscamos la configuración en la DB principal
        $tenant = TelegramBots::where('key', $key)->firstOrFail();
        // 2. Si no existe o el token del header no coincide, rechazamos
        if (!$tenant || $tenant->secret !== $headerToken) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // 3. Si todo está ok, configuramos la conexión dinámica 'tenant'
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'database' => $tenant->database,
            'username' => $tenant->username,
            'password' => $tenant->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        // Forzamos la reconexión
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Creamos un nombre de archivo de log dinámico basado en el Tenant
        $logName = "storage_" . strtolower($tenant->code) . ".log";
        config(['logging.channels.storage.path' => storage_path("logs/{$logName}")]);
        // Para asegurar que use el nuevo path, "olvidamos" el canal anterior:
        app('log')->forgetChannel('storage');

        // Opcional: Compartir la config con el resto de la app
        app()->instance('active_bot', $tenant);

        return $next($request);
    }
}