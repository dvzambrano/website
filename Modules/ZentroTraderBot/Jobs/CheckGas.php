<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        $this->minFee = 0.10; // USD (Comisión que cobras)
        $this->gasEstimated = 386220; // Unidades de Gas estimadas por trade
        $this->alertMargin = 0.03; // Margen de seguridad (3 centavos)
    }

    public function handle()
    {
        // 1. Obtener Tenant y conectar
        $tenant = TelegramBots::find($this->tenantId);
        if (!$tenant)
            return;

        $tenant->connectToThisTenant();

        try {
            // 2. Obtener precio de la moneda nativa (POL/MATIC) con CACHÉ (15 min)
            // Esto evita que CoinGecko te bloquee por exceso de peticiones
            $maticPrice = Cache::remember('coingecko_pol_price', 900, function () {
                $res = Http::get('https://api.coingecko.com/api/v3/simple/price?ids=matic-network&vs_currencies=usd');
                return $res->json()['matic-network']['usd'] ?? 0.0;
            });

            if ($maticPrice <= 0)
                throw new \Exception("No se pudo obtener el precio de MATIC/POL");

            // 3. Gas Station de Polygon (V2 para EIP-1559)
            $response = Http::get('https://gasstation-mainnet.polygon.technology/v2');
            if (!$response->successful())
                throw new \Exception("Gas Station Down");

            $gasData = $response->json();
            $gasPriceGwei = $gasData['fast']['maxFee']; // Prioridad 'fast'

            // 4. Cálculos Financieros
            $costInMatic = ($this->gasEstimated * $gasPriceGwei) / 1000000000;
            $costInUsd = $costInMatic * $maticPrice;
            $profit = $this->minFee - $costInUsd;

            $msg = "";
            $alertType = null;

            // 5. Lógica de Alertas
            if ($costInUsd >= $this->minFee) {
                $alertType = 'critical';
                $msg = "🚨 *KASHIO: GAS ALERT* 🚨\n\n";
                $msg .= "El costo por trade ha superado tu comisión.\n";
                $msg .= "Costo Gas: *\$" . number_format($costInUsd, 4) . "*\n";
                $msg .= "Tu Fee: *\$" . number_format($this->minFee, 4) . "*\n\n";
                $msg .= "⚠️ *Operación en pérdida detectada.*";
            } elseif ($profit < $this->alertMargin) {
                $alertType = 'warning';
                $msg = "⚠️ *KASHIO: MARGEN BAJO*\n\n";
                $msg .= "Ganancia neta: *\$" . number_format($profit, 4) . "*\n";
                $msg .= "Costo Gas: *\$" . number_format($costInUsd, 4) . "*\n";
                $msg .= "⚠️ *Margen menor a \$" . $this->alertMargin . "*";
            }

            // 6. Sistema de Cooldown (Enfriamiento de notificaciones)
            // Si hay un mensaje, verificamos si ya enviamos esta misma alerta hace poco
            if (!empty($msg)) {
                $cacheKey = "gas_alert_sent_{$this->tenantId}_{$alertType}";

                if (!Cache::has($cacheKey)) {
                    $payload = [
                        "message" => [
                            "text" => $msg,
                            "chat" => ["id" => $this->userId],
                            "parse_mode" => "Markdown"
                        ],
                    ];
                    TelegramController::sendMessage($payload, $tenant->token);

                    // Guardamos en caché por 1 hora para no repetir la alerta
                    Cache::put($cacheKey, true, 3600);
                }
            } else {
                // Si el gas volvió a la normalidad, limpiamos los flags para que la próxima alerta se dispare
                Cache::forget("gas_alert_sent_{$this->tenantId}_critical");
                Cache::forget("gas_alert_sent_{$this->tenantId}_warning");
            }

        } catch (\Exception $e) {
            Log::error("❌ CheckGas Error: " . $e->getMessage());
        }

        // 7. Re-despachar el Job para monitoreo constante
        self::dispatch($this->tenantId, $this->userId, $this->chatId)
            ->delay(now()->addMinutes(5));
    }
}