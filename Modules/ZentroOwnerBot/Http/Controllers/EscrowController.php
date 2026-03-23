<?php

namespace Modules\ZentroOwnerBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\Web3\Services\ConfigService;
use Modules\Web3\Traits\BlockchainTools;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Http\Controllers\EscrowController as BotEscrowController;

class EscrowController extends Controller
{
    use BlockchainTools;

    public function setMinFeePerToken($minFeeHuman = 0)
    {
        try {
            $network = ConfigService::getNetworks(env("ESCROW_CHAIN"));
            $token = ConfigService::getToken(env('ESCROW_TOKEN'), $network["chainId"]);

            $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));
            return $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($minFeeHuman, $token) {
                $escrow = new BotEscrowController();

                $minFeeWei = $minFeeHuman * pow(10, $token["decimals"]);
                $txHash = $escrow->setMinFeePerToken(
                    $rpc,
                    env('ESCROW_ARBITER_KEY'),
                    env('ESCROW_CONTRACT'),
                    env('ESCROW_CHAIN'),
                    env('ESCROW_TOKEN'),
                    $minFeeWei,
                    env('ETHERSCAN_API_KEY'),
                );

                if (env("DEBUG_MODE", false))
                    Log::debug("🐞 EscrowController setMinFeePerToken", [
                        "txHash" => $txHash
                    ]);

                return $txHash;
            });

        } catch (\Exception $e) {
            Log::error('🆘 EscrowController error', [
                "chain" => env("ESCROW_CHAIN"),
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }
}
