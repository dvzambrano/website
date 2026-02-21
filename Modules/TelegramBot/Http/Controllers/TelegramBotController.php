<?php

namespace Modules\TelegramBot\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Modules\Laravel\Http\Controllers\Controller;

class TelegramBotController extends Controller
{
    /**
     * Maneja el mensaje recibido por el bot de Telegram.
     * @return mixed
     */
    public function handle()
    {
        // El Middleware 'tenant.detector' ya hizo el trabajo sucio 
        // de configurar la DB 'tenant' antes de llegar aquí.
        $update = request()->all();
        $tenant = app('active_bot'); // Recuperamos lo que guardó el Middleware

        // Validación básica
        if (!$tenant || !isset($tenant->module)) {
            Log::error('🆘 TelegramBotController handle: Active bot not found or invalid module', ['tenant' => $tenant]);
            abort(404, 'Bot handle controller not found');
        }

        $controller = $this->getController($tenant->module, $tenant->module);
        return $this->callControllerMethod($controller, 'receiveMessage', [$tenant, $update], 'Bot handle controller not found');
    }

    /**
     * Inicializa el escaneo del bot.
     * @param mixed $gpsrequired
     * @param string $botname
     * @param mixed $instance
     * @return mixed
     */
    public function initScanner($gpsrequired, $botname, $instance = false)
    {
        $controller = $this->getController($botname, $instance);
        return $this->callControllerMethod(
            $controller,
            'initScanner',
            [$gpsrequired, $botname, $instance],
            'Bot scan controller not found'
        );
    }

    /**
     * Obtiene el controlador dinámico del bot.
     * @param string $botname
     * @param mixed $instance
     * @return object|false
     */
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
        Log::error("🆘  TelegramBotController getController Error: Controller $controller not found ", ['botname' => $botname, 'instance' => $instance]);
        return false;
    }

    /**
     * Almacena el resultado del escaneo.
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeScan()
    {
        $codes = request("codes"); // array
        if (!is_array($codes)) {
            Log::error('🆘 TelegramBotController storeScan: Received codes are not valid', ['codes' => $codes]);
            return response()->json(['success' => false, 'error' => 'Invalid codes'], 400);
        }

        // 1. Extraer el chat_id del usuario desde initData (para saber a quién responder)
        parse_str(request("initData"), $tgData);
        $user = json_decode($tgData['user'] ?? '{}');
        if (!isset($user->id)) {
            Log::error('🆘 TelegramBotController storeScan: Could not extract user from initData', ['initData' => request("initData")]);
            return response()->json(['success' => false, 'error' => 'Invalid user'], 400);
        }

        $controller = $this->getController(request("bot"));
        $result = $this->callControllerMethod(
            $controller,
            'afterScan',
            [$user->id, $codes],
            null,
            ['user_id' => $user->id, 'codes' => $codes]
        );
        if ($result !== null) {
            return $result;
        }
        // If controller not found, log and return error
        return response()->json(['success' => false, 'error' => 'Controller not found'], 404);
    }

    /**
     * Llama a un método de un controlador dinámico, gestiona errores y logs.
     * @param object|false $controller
     * @param string $method
     * @param array $params
     * @param string|null $abortMsg
     * @param array $logContext
     * @return mixed|null
     */
    private function callControllerMethod($controller, $method, $params = [], $abortMsg = null, $logContext = [])
    {
        if ($controller && method_exists($controller, $method)) {
            try {
                return call_user_func_array([$controller, $method], $params);
            } catch (\Throwable $e) {
                Log::error("🆘  TelegramBotController callControllerMethod: Error executing $method", array_merge($logContext, [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]));
                if ($abortMsg)
                    abort(500, $abortMsg);
                return null;
            }
        }
        Log::error("🆘  TelegramBotController callControllerMethod: Method $method not found in controller", $logContext);
        if ($abortMsg)
            abort(404, $abortMsg);
        return null;
    }
}
