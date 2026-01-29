<?php

namespace Modules\TelegramBot\Http\Controllers;

use App\Http\Controllers\FileController;
use App\Http\Controllers\JsonsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Entities\Actors;
use App\Http\Controllers\Controller;

class TelegramBotController extends Controller
{
    public function handle($botname, $instance = false)
    {
        $controller = $this->getController($botname, $instance);
        if ($controller) {
            return $controller->receiveMessage(
                //    $botname,
                //   $instance
            );
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
            /*
            if (!$instance)
                $instance = $botname;
            $host = request()->getHost(); // gutotradebot.micalme.com
            $parts = explode(".", $host);
            if (count($parts) > 2) {
                unset($parts[count($parts) - 1]);
                unset($parts[count($parts) - 1]);
                $instance = implode(".", $parts);
            }
            */
            //return app()->make($controller)->receiveMessage($botname, $instance);
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
