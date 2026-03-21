<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Entities\TelegramBots;

class CheckGas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantId;
    protected $userId;
    protected $chatId;
    protected $minFee;
    protected $gasEstimated;
    protected $alertMargin;

    public function __construct($tenantId, $userId, $chatId)
    {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->chatId = $chatId;

        $this->minFee = 0.10; // USD
        $this->gasEstimated = 386220;
        $this->alertMargin = 0.03; // Avisar si la ganancia baja de 3 centavos
    }

    public function handle()
    {
        // 1. Obtener Tenant (sin lanzar excepción fatal si falla)
        $tenant = TelegramBots::find($this->tenantId);
        if (!$tenant)
            return;

        $tenant->connectToThisTenant();

        try {
            // 1. Obtener precio del Gas (Sugerencia: Usar un RPC de Polygon o PolygonScan API)
            // Usaremos el standard gas price de una fuente confiable
            $response = Http::get('https://gasstation-mainnet.polygon.technology/v2');

            if (!$response->successful()) {
                throw new \Exception("No se pudo obtener el precio del gas");
            }

            $gasData = $response->json();
            $gasPriceGwei = $gasData['fast']['maxFee']; // Usamos 'fast' para asegurar que el bot no se quede pegado

            // 2. Obtener precio de MATIC (vía CoinGecko o similar)
            $maticPriceRes = Http::get('https://api.coingecko.com/api/v3/simple/price?ids=matic-network&vs_currencies=usd');
            $maticPrice = $maticPriceRes->json()['matic-network']['usd'] ?? 1.0;

            // 3. Cálculos matemáticos
            // (GasUnits * GasPriceInGwei) / 1,000,000,000 = Costo en MATIC
            $costInMatic = ($this->gasEstimated * $gasPriceGwei) / 1000000000;
            $costInUsd = $costInMatic * $maticPrice;
            $profit = $this->minFee - $costInUsd;

            $message = "";
            // 4. Lógica de Alertas usando tu TelegramController
            if ($costInUsd >= $this->minFee) {
                $msg = "🚨 *KASHIO: PÉRDIDA DETECTADA*\n\n";
                $msg .= "El gas está carísimo: *\${$costInUsd}* por trade.\n";
                $msg .= "Tu fee mínimo es de: *\$" . $this->minFee . "*.\n\n";
                $msg .= "👉 _Donel, sube el minFeePerToken ahora mismo._";
            } elseif ($profit < $this->alertMargin) {
                $msg = "⚠️ *KASHIO: MARGEN ESTRECHO*\n\n";
                $msg .= "Ganancia limpia por trade: *\${$profit}*.\n";
                $msg .= "La red Polygon está subiendo de precio.";
            }

            if (!empty($message)) {
                $payload = array(
                    "message" => array(
                        "text" => $message,
                        "chat" => array(
                            "id" => $this->userId,
                        ),
                    ),
                );
                TelegramController::sendMessage($payload, $tenant->token);
            }

        } catch (\Exception $e) {

        }

        self::dispatch()->delay(now()->addMinutes(5));
    }
}