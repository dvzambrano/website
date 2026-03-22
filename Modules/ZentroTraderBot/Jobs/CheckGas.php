<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Http\Controllers\BlockchainController;
use Modules\ZentroTraderBot\Entities\Offers;

class CheckGas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $userId;

    public function __construct($tenant, $userId)
    {
        $this->tenant = $tenant;
        $this->userId = $userId;
    }

    public function handle()
    {
        // 1. Obtener datos centralizados del Controller
        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();

        if (!$status) {
            Log::error("❌ CheckGas: No se pudieron obtener los datos de la blockchain.");
            return;
        }

        // 2. Conectar al Tenant para obtener el token del bot
        $tenant = TelegramBots::where('key', $this->tenant)->first();
        if (!$tenant)
            return;
        $tenant->connectToThisTenant();

        try {
            // Extraemos variables para legibilidad (Mapping del array de getStatus)
            $token = $status['token'];
            $network = $status['network'];
            $costInUsd = $status['costInUsd'];
            $feePercentage = $status['feePercentage'];
            $currentMinFeeUsd = $status['currentMinFeeUsd'];

            // 3. ANÁLISIS ECONÓMICO 
            $margin = 30; // 30% beneficio
            $multiplier = 1 + ($margin / 100);
            $idealMinFeeUsd = $costInUsd * $multiplier;

            // 1. Convertimos el entero (BPS) a decimal real
            // Si feePercentage es 25, $realFeeFactor será 0.0025
            $realFeeFactor = $feePercentage / 10000;

            $breakEvenTrade = 0;
            // Evitamos división por cero por si acaso
            if ($realFeeFactor > 0) {
                if ($currentMinFeeUsd > 0) {
                    // El punto donde el % alcanza al mínimo fijo
                    $breakEvenTrade = $currentMinFeeUsd / $realFeeFactor;
                } else {
                    // Si el MinFee es 0, el punto donde el % cubre el costo de GAS
                    $breakEvenTrade = $costInUsd / $realFeeFactor;
                }
            }

            // --- ESTRATEGIA BASE (Para recuperación) ---
            $baseFeeBps = 25; // 0.25%
            $baseMinFeeUsd = 0.01; // Tu suelo ideal por defecto

            // 4. LÓGICA DE ALERTAS
            $msg = "";
            $alertType = null;

            if ($costInUsd > 2.00) {
                $alertType = 'critical';

                // --- LÓGICA DE REALISMO DINÁMICO ---
                $referenceTrade = 100; // Valor por defecto
                try {
                    // Promediamos el 'amount' de las ofertas completadas
                    $avgAmount = Offers::where('status', 'completed')->avg('amount');
                    if ($avgAmount && $avgAmount > 0) {
                        $referenceTrade = (float) $avgAmount;
                    }
                } catch (\Exception $e) {
                }

                // 2. Calculamos qué % representaría ese gas sobre $100
                // ($2.50 / $100) * 10000 = 250 BPS (2.5%)
                $neededBps = ($costInUsd / $referenceTrade) * 10000;

                // 3. CAP: No sugerir nunca más del 5% (500 BPS) para no ser abusivos
                $suggestedBps = min(round($neededBps), 500);

                $msg = "☢️ *CATÁSTROFE DE RED*\n";
                $msg .= "⛽️ Gas prohibitivo: *\$" . number_format($costInUsd, 2) . "*\n";
                $msg .= "💡 Basado en trades promedio de: *\$" . number_format($referenceTrade, 2) . "*\n";
                $msg .= "🔺 `feePercentage` = `" . $suggestedBps . "` (*" . ($suggestedBps / 100) . "%*)\n";
                $msg .= "🔺 `setMinFeePerToken` = `" . round($idealMinFeeUsd * pow(10, $token["decimals"])) . "` (*\$" . number_format($idealMinFeeUsd, 2) . "*)";
            } elseif ($costInUsd >= $currentMinFeeUsd) {
                $alertType = 'critical';
                $msg = "🔴 *PROTECCIÓN DUST FALLIDA *\n";
                $msg .= "⛽️ Gas " . number_format($costInUsd, 4) . " > " . number_format($currentMinFeeUsd, 4) . " (MinFee)\n";
                $msg .= "📉 *Trades < " . number_format($breakEvenTrade, 2) . "  dan pérdida*.\n";
                $msg .= "🔺 `setMinFeePerToken` = `" . round($idealMinFeeUsd * pow(10, $token["decimals"])) . "` (*" . number_format($idealMinFeeUsd, 4) . "*)";
            } elseif ($currentMinFeeUsd < $idealMinFeeUsd) {
                $alertType = 'warning';
                $msg = "🟠 *MARGEN ESTRECHO *\n";
                $msg .= "⛽️ Gas " . number_format($costInUsd, 4) . " < " . number_format($currentMinFeeUsd, 4) . " (MinFee)\n";
                $msg .= "📉 *Trades < " . number_format($breakEvenTrade, 2) . " dan pérdida*.\n";
                $msg .= "🔸 `setMinFeePerToken` = `" . round($idealMinFeeUsd * pow(10, $token["decimals"])) . "` (*" . number_format($idealMinFeeUsd, 4) . "*)";
            }

            // 5. GESTIÓN DE ENVÍO Y RECUPERACIÓN
            $statusKey = "gas_status_{$this->tenant}_" . strtolower($network['chain']);

            if (!empty($msg)) {
                $cacheKey = "gas_alert_{$this->tenant}_" . strtolower($network['chain']) . "_{$alertType}";
                if (!Cache::has($cacheKey)) {
                    TelegramController::sendMessage(["message" => ["text" => $msg, "chat" => ["id" => $this->userId]]], $tenant->token);
                    Cache::put($cacheKey, true, 3600);
                    Cache::put($statusKey, 'alert', 86400);
                }
            } else {
                // LÓGICA DE RECUPERACIÓN
                if (Cache::get($statusKey) === 'alert') {
                    $recoveryMsg = "✅ *BLOCKCHAIN RECUPERADA*\n";
                    $recoveryMsg .= "⛽️ Gas " . number_format($costInUsd, 4) . " | " . number_format($currentMinFeeUsd, 4) . " (MinFee)\n";
                    $recoveryMsg .= "🔻 `setMinFeePerToken` = `" . ($baseMinFeeUsd * pow(10, $token["decimals"])) . "` (*\$" . number_format($baseMinFeeUsd, 2) . "*)\n";
                    $recoveryMsg .= "🔻 `feePercentage` = `" . $baseFeeBps . "` (*" . number_format(($baseFeeBps / 100), 2) . "*%)";
                    TelegramController::sendMessage(["message" => ["text" => $recoveryMsg, "chat" => ["id" => $this->userId]]], $tenant->token);
                    Cache::forget($statusKey);
                }
                $this->clearAlerts($network['chain']);
            }

        } catch (\Exception $e) {
            Log::error("❌ CheckGas Error [{$network['chain']}]: " . $e->getMessage());
        }

        // Re-despacho automático cada 5 min
        self::dispatch($this->tenant, $this->userId)->delay(now()->addMinutes(5));
    }

    private function clearAlerts($chain)
    {
        $base = "gas_alert_{$this->tenant}_" . strtolower($chain);
        Cache::forget("{$base}_critical");
        Cache::forget("{$base}_warning");
    }
}