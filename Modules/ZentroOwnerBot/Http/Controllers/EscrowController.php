<?php

namespace Modules\ZentroOwnerBot\Http\Controllers;

use Illuminate\Container\Attributes\Config;
use Modules\TelegramBot\Traits\UsesTelegramBot;
use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Http\Controllers\Controller;
use Modules\Web3\Services\ConfigService;
use Modules\Web3\Traits\BlockchainTools;
use Modules\Web3\Http\Controllers\EscrowController as BotEscrowController;

class EscrowController extends Controller
{
    use BlockchainTools;

    private $escrow;
    private $rpc;
    private $apiKey;
    private $contract;
    private $chainId;
    private $token;
    private $decimals;
    public function __construct()
    {
        $this->escrow = new BotEscrowController();

        $network = ConfigService::getNetworks(env('ESCROW_CHAIN'));


        // Configuración de Polygon Amoy (Testnet)
        $this->rpc = "https://rpc-amoy.polygon.technology";
        $this->apiKey = env('ETHERSCAN_API_KEY');
        $this->contract = env('ESCROW_CONTRACT');
        $this->chainId = 80002;

        $this->token = "0x8b0180f2101c8260d49339abfee87927412494b4";
        $this->decimals = $this->escrow->getTokenDecimals($this->rpc, $this->token);
    }

    public function processMessage()
    {

    }

    public function test_admin_set_min_fee($minFeeHuman)
    {
        $arbiterKey = env('ESCROW_ARBITER_KEY'); // Wallet del Árbitro


        $this->escrow = new EscrowController();


        $minFeeWei = $minFeeHuman * pow(10, $this->decimals);

        fwrite(STDOUT, "\n--- [ADMIN] Estableciendo Fee Mínimo a $minFeeHuman Tokens (" . $minFeeWei . ") ---\n");

        $txHash = $this->escrow->setMinFeePerToken(
            $this->rpc,
            $arbiterKey,
            $this->contract,
            $this->chainId,
            $this->token,
            $minFeeWei,
            $this->apiKey
        );

        $this->assertNotNull($txHash);
        $this->waitForConfirmation($this->rpc, $txHash);
        fwrite(STDOUT, "[OK] Fee mínimo actualizado.\n");
    }
}
