<?php

namespace Modules\TelegramBot\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class TelegramBotController extends Controller
{
    public function handle()
    {
        // El Middleware 'tenant.detector' ya hizo el trabajo sucio 
        // de configurar la DB 'tenant' antes de llegar aquí.
        $update = request()->all();
        $tenant = app('active_bot'); // Recuperamos lo que guardó el Middleware

        $controller = $this->getController($tenant->module, $tenant->module);
        if ($controller) {
            // Pasamos el objeto $bot y el $update (el mensaje de Telegram)
            return $controller->receiveMessage($tenant, $update);
        }

        abort(404, 'Bot handle controller not found');
    }

    public function initScanner($gpsrequired, $botname, $instance = false)
    {
        $controller = $this->getController($botname, $instance);
        if ($controller) {
            return $controller->initScanner(
                $gpsrequired,
                $botname,
                $instance
            );
        }


        abort(404, 'Bot scan controller not found');
    }

    public function getController($botname, $instance = false)
    {
        // Creando una instancia dinamica de una clase hija encargada de manipular el bot correspondiente
        $controller = "Modules\\{$botname}\\Http\\Controllers\\{$botname}Controller";
        if (class_exists($controller)) {
            return app()->make($controller, [
                'botname' => $botname,
                'instance' => $instance
            ]);
        }

        return false;
    }
    public function storeScan()
    {
        $codes = request("codes"); // array

        // 1. Extraer el chat_id del usuario desde initData (para saber a quién responder)
        parse_str(request("initData"), $tgData);
        $user = json_decode($tgData['user'] ?? '{}');

        $controller = $this->getController(request("bot"));
        if ($controller) {
            return $controller->afterScan(
                $user->id,
                $codes
            );
        } else {
            // Si no aparece el controlador al menos dejamos el log de q se ha leido correctamente
            Log::info("User " . $user->id . " scanned: " . json_encode($codes));
        }

        return response()->json(['success' => true]);
    }
}
