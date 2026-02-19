<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Http\Controllers\WalletController;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Http\Controllers\AlchemyController;
use Modules\Web3\Services\Web3MathService;

class TraderWalletController extends WalletController
{
    /**
     * Se llama cuando el usuario inicia el bot (/start).
     */
    public function getWallet(int $userId)
    {
        $tenant = app('active_bot');

        // 1. Buscar el Actor por su ID de Telegram (user_id)
        $suscriptor = Suscriptions::where('user_id', $userId)->first();

        // Si no existe el actor, retornamos error (o lo creamos según tu lógica)
        if (!$suscriptor) {
            // $suscriptor = Suscriptions::create(['user_id' => $userId, 'data' => []]); 
            return ['status' => 'error', 'message' => 'Usuario no registrado en el sistema.'];
        }

        $currentData = $suscriptor->data ?? [];

        // Validación: Si ya tiene wallet, devolvemos la existente
        if (isset($currentData['wallet']['address'])) {
            return [
                'status' => 'exists',
                'address' => $currentData['wallet']['address']
            ];
        }

        try {
            $wallet = $this->generateWallet();

            // 3. GUARDADO SEGURO EN JSON
            $walletData = [
                'address' => $wallet["address"],
                'private_key' => Crypt::encryptString($wallet["private_key"]), // 🔒 ENCRIPTADO
                'created_at' => now()->toIso8601String()
            ];

            $currentData['wallet'] = $walletData;
            $suscriptor->data = $currentData;
            $suscriptor->save();

            $authToken = config('metadata.system.app.zentrotraderbot.alchemy.authtoken');
            $response = AlchemyController::updateWebhookAddresses(
                $tenant->data["alchemy_webhook_id"],
                $authToken,
                [$wallet["address"]]
            );
            $registered = "";
            if ($response->successful())
                $registered = " y registrada en en Alchemy webhook ID: " . $tenant->data["alchemy_webhook_id"];

            Log::info("✅ Wallet " . $wallet["address"] . " generada en JSON para usuario $userId" . $registered);

            return ['status' => 'created', 'address' => $wallet["address"]];

        } catch (\Exception $e) {
            Log::error("❌ Error generando wallet: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno de criptografía'];
        }
    }

    /**
     * CONSULTAR SALDO (ESTANDARIZADO)
     * - Devuelve el balance de USDC en Polygon.
     * - Si no hay wallet, devuelve error específico.
     * - Si hay wallet pero no balance, devuelve 0.0 sin error.
     */
    public function getBalance($userId, $networkSymbol = null)
    {
        // 1. Obtener Wallet
        $suscriptor = Suscriptions::where('user_id', $userId)->first();
        if (!$suscriptor || !isset($suscriptor->data['wallet']['address'])) {
            return ['status' => 'error', 'message' => 'No tienes wallet configurada.'];
        }

        $address = $suscriptor->data['wallet']['address'];
        $authToken = config('metadata.system.app.zentrotraderbot.alchemy.authtoken');
        $usdcContract = config('web3.tokens.USDC.address');
        $balances = AlchemyController::getTokenBalances($authToken, $address, [$usdcContract]);
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
    public function getRecentTransactions($userId, $networkSymbol = null)
    {
        // 1. Obtener Wallet
        $suscriptor = Suscriptions::where('user_id', $userId)->first();
        if (!$suscriptor || !isset($suscriptor->data['wallet']['address'])) {
            return ['status' => 'error', 'message' => 'No tienes wallet configurada.'];
        }

        $address = $suscriptor->data['wallet']['address'];
        $authToken = config('metadata.system.app.zentrotraderbot.alchemy.authtoken');
        $usdcContract = config('web3.tokens.USDC.address');

        return AlchemyController::getRecentTransactions($authToken, $address, ["erc20"], [$usdcContract]);
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
    public function withdraw($privateKey, string $toAddress, string $tokenSymbol, ?float $amount = null)
    {
        // Si el primer parámetro es un int, se asume userId y se extrae la clave
        if (is_int($privateKey)) {
            $privateKey = $this->getDecryptedPrivateKey($privateKey);
        }
        $tokenSymbol = strtoupper($tokenSymbol);
        return parent::withdraw($privateKey, $toAddress, $tokenSymbol, $amount);
    }
}