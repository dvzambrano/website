<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\Web3\Services\ConfigService;
use Modules\Web3\Http\Controllers\EscrowController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Modules\Web3\Traits\BlockchainTools;
use Modules\ZentroTraderBot\Entities\Offers;

class BlockchainController extends Controller
{
    use BlockchainTools;

    public function getStatus()
    {
        try {
            $network = ConfigService::getNetworks(env("BASE_NETWORK"));
            $token = ConfigService::getToken(env('BASE_TOKEN'), $network["chainId"]);
            $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));
            $escrow = new EscrowController();

            // 1. Obtener Gas Price en WEI (unidad mínima)
            $gasPriceWei = $this->rpcCallWithFallback($rpcUrls, function ($rpc) {
                $gasPriceHex = $this->rpcCall($rpc, 'eth_gasPrice', [], true);
                return hexdec($gasPriceHex);
            });
            $gasPriceGwei = $gasPriceWei / 1000000000;

            $nativePrice = $this->getPriceFromLlama($network);

            // 2. Obtener estado del contrato
            $feePercentage = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $network) {
                return $escrow->getFeePercentage($rpc, env('ESCROW_CONTRACT'), $network['chainId'], env('ETHERSCAN_API_KEY'));
            });
            $tradeTimeout = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $network) {
                return $escrow->getTradeTimeout($rpc, env('ESCROW_CONTRACT'), $network['chainId'], env('ETHERSCAN_API_KEY'));
            });

            // 3. CÁLCULO DE COSTO (Corregido)
            $gasEstimated = env("ESCROW_GAS_ESTIMATED", 600000); // Gas de Funciones Operativas del Escrow: se obtiene del reporte "forge test --gas-report" en el 
            // (Gas * GasPrice en Wei) / 10^18 (para obtener POL/ETH) * Precio USD
            $costInUsd = ($gasEstimated * $gasPriceWei / pow(10, 18)) * $nativePrice;

            // 4. Diagnóstico MinFee (Corregido)
            $currentMinFeeRaw = $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($escrow, $network, $token) {
                return $escrow->getMinFeePerToken($rpc, env('ESCROW_CONTRACT'), $network['chainId'], $token["address"], env('ETHERSCAN_API_KEY'));
            });
            $currentMinFeeUsd = (float) $currentMinFeeRaw / pow(10, $token["decimals"]);

            $referenceTrade = 100; // Valor por defecto
            try {
                // Promediamos el 'amount' de las ofertas completadas
                $avgAmount = Offers::where('status', 'completed')->avg('amount');
                if ($avgAmount && $avgAmount > 0) {
                    $referenceTrade = (float) $avgAmount;
                }
            } catch (\Exception $e) {
                Log::error('🆘 BlockchainController referenceTrade error', [
                    'message' => $e->getMessage()
                ]);
            }

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

            $array = [
                "network" => $network,
                "token" => $token,
                "gasPriceGwei" => $gasPriceGwei,
                "nativePrice" => $nativePrice,
                "feePercentage" => (int) $feePercentage,
                "tradeTimeout" => (int) $tradeTimeout,
                "gasEstimated" => $gasEstimated,
                "costInUsd" => $costInUsd,
                "currentMinFeeRaw" => $currentMinFeeRaw,
                "currentMinFeeUsd" => $currentMinFeeUsd,
                "referenceTrade" => $referenceTrade,
                "breakEvenTrade" => $breakEvenTrade,
                "realFeeFactor" => $realFeeFactor,
            ];
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 CheckGas Job handle", $array);

            return $array;
            BASE_NETWORK

        } catch (\Exception $e) {
            Log::error('🆘 BlockchainController error', [
                "chain" => env("BASE_NETWORK"),
                'message' => $e->getMessage()
            ]);
            return null;
        }
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
