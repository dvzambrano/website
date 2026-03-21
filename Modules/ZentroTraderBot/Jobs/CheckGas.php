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
        // 1. Cargar Configuración de Red
        $network = ConfigService::getNetworks(env("ESCROW_CHAIN"));
        if (!$network) {
            Log::error("❌ CheckGas: No se encontró configuración para la red: " . strtolower($network['chain']));
            return;
        }

        $tenant = TelegramBots::where('key', $this->tenant)->first();
        if (!$tenant)
            return;
        $tenant->connectToThisTenant();

        $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));

        try {
            // 2. OBTENER FEE DESDE EL CONTRATO (Dinámico)
            $feePercentage = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($network) {
                $escrow = new EscrowController();
                return $escrow->getFeePercentage(
                    $rpc,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    env('ETHERSCAN_API_KEY')
                );
            });

            // Asumimos base 10000 (100 = 1%). En un trade promedio de 10 USD:
            $minFee = (10 * $feePercentage) / 10000;

            // 3. OBTENER GAS PRICE (Dinámico vía RPC)
            $gasPriceGwei = $this->rpcCallWithFallback($rpcUrls, function ($rpc) {
                $gasPriceHex = $this->rpcCall($rpc, 'eth_gasPrice', [], true);
                return hexdec($gasPriceHex) / 1000000000;
            });


            $currentMinFeeRaw = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($network) {
                $escrow = new EscrowController();
                return $escrow->getMinFeePerToken(
                    $rpc,
                    env('ESCROW_CONTRACT'),
                    $network['chainId'],
                    env('ESCROW_TOKEN'), // La dirección de USDC/POL en esa red
                    env('ETHERSCAN_API_KEY')
                );
            });

            // Suponiendo USDC (6 decimales), convertimos para la lógica de la alerta
            $currentMinFeeUsd = (float) $currentMinFeeRaw / 1000000;

            // 4. OBTENER PRECIO NATIVO (DeFiLlama)
            $nativePrice = $this->getPriceFromLlama($network);

            if ($nativePrice <= 0)
                throw new \Exception("Precio de token nativo no disponible.");

            // 5. CÁLCULOS REALISTAS
            $gasEstimated = 386220; // Unidades de gas
            $costInUsd = ($gasEstimated * $gasPriceGwei) / 1000000000 * $nativePrice;
            $profit = $minFee - $costInUsd;
            $margin = 30; // Margen de seguridad del 30%
            $alertMargin = $minFee * $margin / 100;
            // CÁLCULO DINÁMICO DE RECOMENDACIÓN
            // Si el margen es 30, multiplicamos por 1.30
            $multiplier = 1 + ($margin / 100);
            $recommendedFee = $costInUsd * $multiplier;
            // En base 10000 para el contrato (asumiendo trade de $10)
            // Formula: (Recomendado * 10000) / 10
            $suggestedBasisPoints = ($recommendedFee * 10000) / 10;

            if (env("DEBUG_MODE", false))
                Log::debug("🐞 CheckGas Job handle", [
                    "network" => $network['name'],
                    "cost" => $costInUsd,
                    "profit" => $profit,
                    "minFee" => $minFee,
                    "alertMargin" => $alertMargin,
                ]);

            // --- CONFIGURACIÓN DE TU ESTRATEGIA ---
            $baseFeeBps = 25; // Tu 0.25% ideal (estado inicial)
            $baseMinFee = (10 * $baseFeeBps) / 10000; // Lo que ganarías en estado normal ($0.025)

            // 6. LÓGICA DE ALERTAS
            $msg = "";
            $alertType = null;
            $label = $network['title'];
            if ($costInUsd >= $minFee) {
                $alertType = 'critical';
                $msg = "🔴 *MARGEN NEGATIVO - {$label}*\n";
                $msg .= "⛽ Costo Gas: *" . number_format($costInUsd, 4) . "*\n";
                $msg .= "👉 Tu Fee actual: *" . number_format($minFee, 4) . "*\n";
                $msg .= "💰 Perdida neta: *" . number_format($profit, 4) . "*\n\n";
                $msg .= "🔺 *Subir Fee a: " . number_format($recommendedFee, 4) . "*\n";
                $msg .= "📋 Valor para el contrato: `" . round($suggestedBasisPoints) . "` bps";
            } elseif ($profit < $alertMargin) {
                $alertType = 'warning';
                $msg = "🟠 *MARGEN MENOR AL {$margin}% - {$label}*\n";
                $msg .= "⛽ Costo Gas: *" . number_format($costInUsd, 4) . "*\n";
                $msg .= "👉 Tu Fee actual: *" . number_format($minFee, 4) . "*\n";
                $msg .= "💰 Ganancia neta: *" . number_format($profit, 4) . "*\n\n";
                $msg .= "🔸 *Ajustar Fee a: " . number_format($recommendedFee, 4) . "*\n";
                $msg .= "📋 Valor para el contrato: `" . round($suggestedBasisPoints) . "` bps";
            } elseif ($feePercentage > $baseFeeBps && ($baseMinFee - $costInUsd) > ($baseMinFee * $margin / 100)) {
                $alertType = 'optimize';
                $msg = "🟢 *OPORTUNIDAD DE OPTIMIZACIÓN - {$label}*\n";
                $msg .= "El gas ha bajado a niveles normales (Base).\n\n";
                $msg .= "⛽ Costo Gas: *" . number_format($costInUsd, 4) . "*\n";
                $msg .= "💰 Ganancia si bajas al base: *" . number_format($baseMinFee - $costInUsd, 4) . "*\n\n";
                $msg .= "✅ *Sugerencia:* Volver al fee inicial de `" . $baseFeeBps . "` bps para ser más competitivo.";
            }

            // 7. ENVÍO CON COOLDOWN
            if (!empty($msg)) {
                $cacheKey = "gas_alert_{$this->tenant}_" . strtolower($network['chain']) . "_{$alertType}";
                if (!Cache::has($cacheKey)) {
                    TelegramController::sendMessage([
                        "message" => ["text" => $msg, "chat" => ["id" => $this->userId], "parse_mode" => "Markdown"]
                    ], $tenant->token);
                    Cache::put($cacheKey, true, 3600);
                }
            } else {
                Cache::forget("gas_alert_{$this->tenant}_" . strtolower($network['chain']) . "_critical");
                Cache::forget("gas_alert_{$this->tenant}_" . strtolower($network['chain']) . "_warning");
            }

        } catch (\Exception $e) {
            Log::error("❌ CheckGas Error (" . strtolower($network['chain']) . "): " . $e->getMessage());
        }

        // Re-despachar para monitoreo constante
        self::dispatch($this->tenant, $this->userId)->delay(now()->addMinutes(5));
    }

    /**
     * Mapea ChainID a los IDs que entiende DeFiLlama
     */
    private function getPriceFromLlama($networkConfig)
    {
        // 1. Normalizamos el nombre de la red para Llama
        // Llama usa "polygon", "bsc", "ethereum", "arbitrum", etc.
        $chainName = strtolower($networkConfig['chain'] ?? $networkConfig['name']);

        // 2. Dirección del token nativo (Casi siempre es esta para Llama)
        // Para tokens nativos, Llama suele aceptar la dirección de "0x0000..." 
        // o el nombre del asset. Pero lo más seguro es:
        $nativeTokenAddress = "0x0000000000000000000000000000000000000000";

        // Caso especial: Polygon/Amoy a veces requieren su token de sistema
        if ($chainName === 'polygon') {
            $nativeTokenAddress = "0x0000000000000000000000000000000000001010";
        }

        $identifier = "{$chainName}:{$nativeTokenAddress}";

        return Cache::remember("llama_price_{$identifier}", 300, function () use ($identifier) {
            $res = Http::get("https://coins.llama.fi/prices/current/{$identifier}");
            $data = $res->json();

            return $data['coins'][$identifier]['price'] ?? 0.0;
        });
    }
}