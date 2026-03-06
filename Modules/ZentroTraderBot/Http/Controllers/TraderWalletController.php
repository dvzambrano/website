<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Http\Controllers\WalletController;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Http\Controllers\AlchemyController;
use Modules\Web3\Services\Web3MathService;
use Modules\Web3\Http\Controllers\InchController;
use Modules\Web3\Http\Controllers\ChainidController;

class TraderWalletController extends WalletController
{
    /**
     * Se llama cuando el usuario inicia el bot (/start).
     */
    public function getWallet($tenant)
    {
        $wallet = null;

        try {
            $wallet = $this->generateWallet();

            $authToken = config('web3.alchemy_auth_token');
            AlchemyController::updateWebhookAddresses(
                $tenant->data["alchemy_webhook_id"],
                $authToken,
                [$wallet["address"]]
            );

            return $wallet;

        } catch (\Exception $e) {
            Log::error("🆘 TraderWalletController getWallet: Error generando wallet: " . $e->getMessage());
        }

        return $wallet;
    }

    /**
     * CONSULTAR SALDO (ESTANDARIZADO)
     * - Devuelve el balance de USDC en Polygon.
     * - Si no hay wallet, devuelve error específico.
     * - Si hay wallet pero no balance, devuelve 0.0 sin error.
     */
    public function getBalance($suscriptor, $networkSymbol = "POL")
    {
        // 1. Obtener Wallet
        if (!$suscriptor || !isset($suscriptor->data['wallet']['address'])) {
            return ['status' => 'error', 'message' => 'No tienes wallet configurada.'];
        }

        $address = $suscriptor->data['wallet']['address'];
        $apiKey = config('web3.alchemy_api_key');

        $networks = ChainidController::getNetworkData();
        $chainId = (int) $networks[$networkSymbol]["chainId"];
        $tokenMap = InchController::getTokensData($chainId);

        $usdcContract = $tokenMap["usdc"]["address"];

        $balances = AlchemyController::getTokenBalances($apiKey, $address, [$usdcContract]);

        $humanBal = "0.0";
        if (is_array($balances) && count($balances)) {
            foreach ($balances as $bal) {
                $hexBal = $bal['tokenBalance'] ?? '0x0';
                // Conversión humana
                $humanBal = Web3MathService::hexToDecimal($hexBal, 6);
            }
        }

        return $humanBal;
    }
    public function getRecentTransactions($suscriptor, $networkSymbol = null)
    {
        // 1. Obtener Wallet
        if (!$suscriptor || !isset($suscriptor->data['wallet']['address'])) {
            return ['status' => 'error', 'message' => 'No tienes wallet configurada.'];
        }

        $address = $suscriptor->data['wallet']['address'];
        $apiKey = config("web3.alchemy_api_key");
        $usdcContract = config('web3.networks.POL.tokens.USDC.address');
        $polUsdcContract = config('web3.networks.POL.tokens.USDC.address');

        return AlchemyController::getRecentTransactions($apiKey, $address, 'POL', ["erc20"], [$polUsdcContract], 5);
    }

    /**
     * OBTENER CLAVE PRIVADA (DESCIFRADA)
     * Uso interno para firmar transacciones.
     */
    public function getDecryptedPrivateKey(int $userId)
    {
        $suscriptor = Suscriptions::where('user_id', $userId)->first();

        if (!$suscriptor || !isset($suscriptor->data['wallet']['private_key'])) {
            throw new \Exception("Usuario $userId no tiene wallet.");
        }

        $encryptedKey = $suscriptor->data['wallet']['private_key'];

        // 🔓 Desencriptamos manualmente
        return Crypt::decryptString($encryptedKey);
    }

    /**
     * RETIRAR FONDOS (Híbrido Universal v0.9)
     * - Detecta EIP-1559 vs Legacy.
     * - Límites de gas seguros para contratos/exchanges.
     */
    // Sobrecarga para aceptar userId y extraer el privateKey antes de delegar
    public function withdraw($privateKey, string $networkKey, string $tokenSymbol, string $toAddress, ?float $amount = null)
    {
        // Si el primer parámetro es un int, se asume userId y se extrae la clave
        if (is_int($privateKey)) {
            $privateKey = $this->getDecryptedPrivateKey($privateKey);
        }
        $tokenSymbol = strtoupper($tokenSymbol);
        return parent::withdraw($privateKey, $toAddress, $tokenSymbol, $amount);
    }
}