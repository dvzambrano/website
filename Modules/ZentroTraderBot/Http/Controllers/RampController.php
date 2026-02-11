<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Contracts\RampProviderInterface;
use Illuminate\Support\Facades\Lang;

class RampController extends Controller
{
    public function redirect($action, $user_id)
    {
        $bot = app('active_bot');
        $suscriptor = Suscriptions::on('tenant')->where("user_id", $user_id)->first();

        // 1. Decidir el proveedor (Aquí entra tu parámetro de base de datos)
        // Por ahora lo forzamos a 'transak', pero luego será $bot->ramp_provider
        $providerName = $bot->data['ramp_provider'] ?? 'transak';

        $provider = $this->getProvider($providerName);
        $url = $provider->getWidgetUrl($bot, $suscriptor, $action);

        return $url ? Redirect::to($url) : back()->with('error', 'Error en pasarela');
    }
    public function redirect1($action, $key, $secret, $user_id)
    {
        // Recuperamos el bot que el Middleware ya encontró y guardó
        $bot = app('active_bot');
        // Forzamos la consulta a la base de datos del Tenant
        $suscriptor = Suscriptions::on('tenant')->where("user_id", $user_id)->first();
        if (!$suscriptor || !isset($suscriptor->data["wallet"]["address"])) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.suscriptor");
        }


        $widgetUrl = $this->getWidgetUrl($bot, $suscriptor, strtoupper($action));

        if (!$widgetUrl) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.widgeturl");
        }

        return Redirect::to($widgetUrl);
    }

    private function getProvider($name): RampProviderInterface
    {
        return match ($name) {
            'transak' => app(TransakController::class),
            //'wert' => app(WertController::class),
            //'guardarian' => app(GuardarianController::class),
            default => throw new \Exception("Proveedor no soportado"),
        };
    }
}