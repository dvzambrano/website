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

            $breakEvenTrade = ($currentMinFeeUsd > 0 && $feePercentage > 0)
                ? ($currentMinFeeUsd / ($feePercentage / 10000)) : 0;

            // --- ESTRATEGIA BASE (Para recuperación) ---
            $baseFeeBps = 25; // 0.25%
            $baseMinFeeUsd = 0.05; // Tu suelo ideal por defecto

            // 4. LÓGICA DE ALERTAS
            $msg = "";
            $alertType = null;

            if ($idealMinFeeUsd > 2.00) {
                $alertType = 'critical';
                $suggestedBps = ($idealMinFeeUsd * 10000) / 10;
                $msg = "☢️ *CATÁSTROFE DE RED *\n";
                $msg .= "⛽️ Gas prohibitivo: *\$" . number_format($costInUsd, 2) . "*\n";
                $msg .= "🔺 `feePercentage` = `" . round($suggestedBps) . "` (*" . number_format($idealMinFeeUsd, 2) . "*%)";
            } elseif ($costInUsd >= $currentMinFeeUsd) {
                $alertType = 'critical';
                $msg = "🔴 *PROTECCIÓN DUST FALLIDA *\n";
                $msg .= "⛽️ Gas " . number_format($costInUsd, 4) . " > " . number_format($currentMinFeeUsd, 4) . " (MinFee)\n";
                $msg .= "📉 *Trades < " . number_format($breakEvenTrade, 2) . " dan pérdida*.\n";
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