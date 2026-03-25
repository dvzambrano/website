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

    /**
     * Método Helper para centralizar la ejecución de transacciones en el Escrow.
     */
    private function executeTransaction(callable $callback)
    {
        try {
            $network = ConfigService::getNetworks(env("BASE_NETWORK"));
            $rpcUrls = array_filter($network['rpc'] ?? [], fn($url) => str_starts_with($url, 'https'));

            return $this->rpcCallWithFallback($rpcUrls, function ($rpc) use ($callback, $network) {
                $escrow = new BotEscrowController();
                // Pasamos rpc y escrow al callback para realizar la acción específica
                $txHash = $callback($rpc, $escrow, $network);

                if (env("DEBUG_MODE", false))
                    Log::debug("🐞 EscrowController executeTransaction", [
                        "txHash" => $txHash,
                        "network" => $network
                    ]);

                return $txHash;
            });

        } catch (\Exception $e) {
            Log::error('🆘 EscrowController error', [
                "chain" => env("BASE_NETWORK"),
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function proposeArbiter($address)
    {
        return $this->executeTransaction(function ($rpc, $escrow) use ($address) {
            // $escrow = new BotEscrowController();
            return $escrow->proposeArbiter(
                $rpc,
                decryptValue(env('ESCROW_ARBITER_KEY')),
                env('ESCROW_CONTRACT'),
                env('BASE_NETWORK'),
                $address,
                env('ETHERSCAN_API_KEY')
            );
        });
    }

    public function resolveDispute($tradeId, $winner)
    {
        return $this->executeTransaction(function ($rpc, $escrow) use ($tradeId, $winner) {
            // $escrow = new BotEscrowController();
            return $escrow->resolveDispute(
                $rpc,
                decryptValue(env('ESCROW_ARBITER_KEY')),
                env('ESCROW_CONTRACT'),
                env('BASE_NETWORK'),
                $tradeId,
                $winner,
                env('ETHERSCAN_API_KEY')
            );
        });
    }

    public function rescueTokens($address)
    {
        return $this->executeTransaction(function ($rpc, $escrow) use ($address) {
            // $escrow = new BotEscrowController();
            return $escrow->rescueTokens(
                $rpc,
                decryptValue(env('ESCROW_ARBITER_KEY')),
                env('ESCROW_CONTRACT'),
                env('BASE_NETWORK'),
                $address,
                env('ETHERSCAN_API_KEY')
            );
        });
    }

    public function setFee($feeHuman = 0)
    {
        return $this->executeTransaction(function ($rpc, $escrow) use ($feeHuman) {
            $feeBps = $feeHuman * 100; // Pasamos de humano a Base Points (ej: 0.25 -> 25)
            // $escrow = new BotEscrowController();
            return $escrow->setFee(
                $rpc,
                decryptValue(env('ESCROW_ARBITER_KEY')),
                env('ESCROW_CONTRACT'),
                env('BASE_NETWORK'),
                $feeBps,
                env('ETHERSCAN_API_KEY')
            );
        });
    }

    public function setMinFeePerToken($feeHuman = 0)
    {
        return $this->executeTransaction(function ($rpc, $escrow, $network) use ($feeHuman) {
            $token = ConfigService::getToken(env('BASE_TOKEN'), $network["chainId"]);
            $minFeeWei = $feeHuman * pow(10, $token["decimals"]);
            // $escrow = new BotEscrowController();
            return $escrow->setMinFeePerToken(
                $rpc,
                decryptValue(env('ESCROW_ARBITER_KEY')),
                env('ESCROW_CONTRACT'),
                env('BASE_NETWORK'),
                env('BASE_TOKEN'),
                $minFeeWei,
                env('ETHERSCAN_API_KEY')
            );
        });
    }

    public function withdrawFees()
    {
        return $this->executeTransaction(function ($rpc, $escrow) {
            // $escrow = new BotEscrowController();
            return $escrow->withdrawFees(
                $rpc,
                decryptValue(env('ESCROW_ARBITER_KEY')),
                env('ESCROW_CONTRACT'),
                env('BASE_NETWORK'),
                env('BASE_TOKEN'),
                env('ETHERSCAN_API_KEY')
            );
        });
    }
}