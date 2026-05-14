<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Modules\Web3\Http\Controllers\BlockchainProviderController;
use Modules\Web3\Http\Controllers\EthersController;
use Modules\Web3\Http\Controllers\WalletController;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Services\Web3MathService;
use Modules\Web3\Http\Controllers\InchController;
use Modules\Web3\Http\Controllers\ChainidController;
use Modules\Web3\Services\ConfigService;

class TraderWalletController extends WalletController
{
    /**
     * Se llama cuando el usuario inicia el bot (/start).
     */
    public function getWallet()
    {
        $wallet = null;

        try {
            $wallet = $this->generateWallet();
        } catch (\Exception $e) {
            Log::error("🆘 TraderWalletController getWallet: Error generando wallet: " . $e->getMessage());
        }

        return $wallet;
    }

    /**
     * CONSULTAR SALDO (ESTANDARIZADO)
     * - Devuelve el balance de BASE_TOKEN en BASE_NETWORK.
     * - Si no hay wallet, devuelve error específico.
     * - Si hay wallet pero no balance, devuelve 0.0 sin error.
     */
    public function getBalance($suscriptor)
    {
        // 1. Obtener Wallet
        if (!$suscriptor || !isset($suscriptor->data['wallet']['address'])) {
            return ['status' => 'error', 'message' => 'No tienes wallet configurada.'];
        }

        $address = $suscriptor->data['wallet']['address'];

        $chain = ConfigService::getActiveNetwork();
        $token = ConfigService::getToken(env('BASE_TOKEN'), env('BASE_NETWORK'));
        //dd($address, $token);

        $balances = EthersController::getTokenBalance($address, $chain, [$token]);

        $humanBal = "0.0";
        foreach ($balances as $bal)
            $humanBal = $bal['balance'];

        return $humanBal;
    }
    public function getRecentTransactions($suscriptor, $limit = 5)
    {
        // 1. Obtener Wallet
        if (!$suscriptor || !isset($suscriptor->data['wallet']['address'])) {
            return ['status' => 'error', 'message' => 'No tienes wallet configurada.'];
        }

        $address = $suscriptor->data['wallet']['address'];
        $apiKey = config("zentrotraderbot.alchemy_api_key");

        $network = ConfigService::getActiveNetwork();
        $token = ConfigService::getToken(env('BASE_TOKEN'), env('BASE_NETWORK'));

        $txs = BlockchainProviderController::getRecentTransactions($address, $network, [$token], $limit);
        //dd($txs);
        return $txs;
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
        return decryptValue($encryptedKey);
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