<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Contracts\BlockchainProviderInterface;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Modules\ZentroTraderBot\Entities\Ramporders;

class BlockchainController extends Controller
{
    private function getProvider($name): BlockchainProviderInterface
    {
        return match ($name) {
            'moralis' => app(MoralisController::class),
            //'alchemy' => app(AlchemyController::class),
            default => throw new \Exception("Proveedor no soportado"),
        };
    }

    public function processWebhook()
    {
        // Decidir el proveedor de RAMP activo para este bot
        $providerName = 'moralis'; // $tenant->data['ramp']

        try {
            $provider = $this->getProvider($providerName);

            $array = $provider->processWebhook(request());

        } catch (\Exception $e) {
            // Logueamos el error pero devolvemos 200 para que el proveedor no marque el webhook como "DOWN"
            Log::error("🆘 Error procesando Webhook de {$providerName}: " . $e->getMessage());
        }

        // Respuesta estándar
        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String()
        ], 200);
    }
}