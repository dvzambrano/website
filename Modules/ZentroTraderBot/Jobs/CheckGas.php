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
use Modules\Web3\Http\Controllers\EscrowController;
use Modules\Web3\Services\ConfigService;
use Modules\Web3\Traits\BlockchainTools;
use Web3\Utils;

class CheckGas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BlockchainTools;

    protected $tenant;
    protected $userId;

    public function __construct($tenant, $userId)
    {
        $this->tenant = $tenant;
        $this->userId = $userId;
    }

    public function handle()
    {
        // 1. Configuración de Red y Tenant
        $network = ConfigService::getNetworks(env("ESCROW_CHAIN"));
        if (!$network)
            return;
        $token = ConfigService::getToken(env('ESCROW_TOKEN'), $network["chainId"]);

        $tenant = TelegramBots::where('key', $this->tenant)->first();
        if (!$tenant)
            return;
        $tenant->connectToThisTenant();

        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));
        $escrow = new EscrowController();

        try {
            // 2. CONSULTA AL CONTRATO
            $feePercentage = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $network) {
                return $escrow->getFeePercentage($rpc, env('ESCROW_CONTRACT'), $network['chainId'], env('ETHERSCAN_API_KEY'));
            });

            $currentMinFeeRaw = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $network, $token) {
                return $escrow->getMinFeePerToken(
                    $rpc,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    $token["address"],
                    env('ETHERSCAN_API_KEY')
                );
            });

            // 3. DATOS DE MERCADO
            $gasPriceGwei = $this->rpcCallWithFallback($rpcUrls, function ($rpc) {
                $gasPriceHex = $this->rpcCall($rpc, 'eth_gasPrice', [], true);
                return hexdec($gasPriceHex) / 1000000000;
            });

            $nativePrice = $this->getPriceFromLlama($network);
            if ($nativePrice <= 0)
                throw new \Exception("Precio DeFiLlama no disponible.");

            // 4. ANÁLISIS ECONÓMICO
            $gasEstimated = 386220;
            $costInUsd = ($gasEstimated * $gasPriceGwei) / 1000000000 * $nativePrice;
            $margin = 30; // 30% beneficio
            $multiplier = 1 + ($margin / 100);

            $idealMinFeeUsd = $costInUsd * $multiplier;
            $currentMinFeeUsd = (float) $currentMinFeeRaw / $token["decimals"];

            $breakEvenTrade = ($currentMinFeeUsd > 0 && $feePercentage > 0)
                ? ($currentMinFeeUsd / ($feePercentage / 10000)) : 0;

            // --- ESTRATEGIA BASE (Para recuperación) ---
            $baseFeeBps = 25; // 0.25%
            $baseMinFeeUsd = 0.05; // Tu suelo ideal por defecto


            if (env("DEBUG_MODE", false))
                Log::debug("🐞 CheckGas Job handle", [
                    "network" => $network['name'],
                    "cost" => $costInUsd,
                    "feePercentage" => $feePercentage,
                    "currentMinFeeRaw" => $currentMinFeeRaw,
                    "gasPriceGwei" => $gasPriceGwei,
                ]);

            // 5. LÓGICA DE ALERTAS
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
                $msg .= "🔺 `setMinFeePerToken` = `" . round($idealMinFeeUsd * $token["decimals"]) . "` (*" . number_format($idealMinFeeUsd, 4) . "*)";
            } elseif ($currentMinFeeUsd < $idealMinFeeUsd) {
                $alertType = 'warning';
                $msg = "🟠 *MARGEN ESTRECHO *\n";
                $msg .= "⛽️ Gas " . number_format($costInUsd, 4) . " < " . number_format($currentMinFeeUsd, 4) . " (MinFee)\n";
                $msg .= "📉 *Trades < " . number_format($breakEvenTrade, 2) . " dan pérdida*.\n";
                $msg .= "🔸 `setMinFeePerToken` = `" . round($idealMinFeeUsd * $token["decimals"]) . "` (*" . number_format($idealMinFeeUsd, 4) . "*)";
            }

            // 6. GESTIÓN DE ENVÍO Y RECUPERACIÓN
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
                    $recoveryMsg .= "🔻 `setMinFeePerToken` = `" . ($baseMinFeeUsd * $token["decimals"]) . "` (*\$" . number_format($baseMinFeeUsd, 2) . "*)\n";
                    $recoveryMsg .= "🔻 `feePercentage` = `" . $baseFeeBps . "` (*" . number_format(($baseFeeBps / 100), 2) . "*%)";
                    TelegramController::sendMessage(["message" => ["text" => $recoveryMsg, "chat" => ["id" => $this->userId]]], $tenant->token);
                    Cache::forget($statusKey);
                }
                $this->clearAlerts($network['chain']);
            }

        } catch (\Exception $e) {
            Log::error("❌ CheckGas Error [{$network['chain']}]: " . $e->getMessage());
        }

        self::dispatch($this->tenant, $this->userId)->delay(now()->addMinutes(5));
    }

    private function clearAlerts($chain)
    {
        $base = "gas_alert_{$this->tenant}_" . strtolower($chain);
        Cache::forget("{$base}_critical");
        Cache::forget("{$base}_warning");
    }

    private function getPriceFromLlama($config)
    {
        $chainName = strtolower($config['chain'] ?? $config['name']);
        $address = ($chainName === 'polygon') ? "0x0000000000000000000000000000000000001010" : "0x0000000000000000000000000000000000000000";
        $id = "{$chainName}:{$address}";
        return Cache::remember("llama_p_{$id}", 300, function () use ($id) {
            $res = Http::get("https://coins.llama.fi/prices/current/{$id}");
            return $res->json()['coins'][$id]['price'] ?? 0.0;
        });
    }
}