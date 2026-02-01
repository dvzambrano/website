<?php
namespace Modules\ZentroTraderBot\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransakService
{
    protected $apiKey;
    protected $apiSecret;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config("metadata.system.app.zentrotraderbot.ramp.apiurl");
        $this->apiKey = config("metadata.system.app.zentrotraderbot.ramp.apikey");
        $this->apiSecret = config("metadata.system.app.zentrotraderbot.ramp.apisecret");
    }

    public function generateSignedUrl($wallet, $amount = null)
    {
        // Documentación: https://docs.transak.com/reference/create-a-order-via-api
        // Nota: Para usar la API de "Order" o "Widget Session", a veces requieren partners verificados.
        // Si solo quieres firmar la URL de Query para que no la manipulen, se hace así:

        $params = [
            'apiKey' => $this->apiKey,
            'cryptoCurrencyCode' => 'USDC',
            'displayCryptoCurrencyCode' => 'USD', // Intentamos forzarlo aquí
            'network' => 'polygon',
            'walletAddress' => $wallet,
            'disableWalletAddressForm' => true,
            'productsAvailed' => 'BUY',
            'isCryptoEditable' => false,
            'themeColor' => '043927',
            'exchangeScreenTitle' => 'Cargar Bóveda Kashio',
            'environment' => 'STAGING'
        ];

        if ($amount) {
            $params['fiatAmount'] = $amount;
        }

        // 1. Convertir parámetros a query string
        $queryString = http_build_query($params);

        // 2. Firmar la URL (Si tu cuenta de Transak lo requiere en el dashboard)
        // Algunos partners deben enviar un JWT o una firma HMAC. 
        // Si Transak te pide firmar el Widget, usa este método:
        $signature = base64_encode(hash_hmac('sha256', $queryString, $this->apiSecret, true));

        $finalUrl = $this->apiUrl . "/?" . $queryString . "&signature=" . urlencode($signature);

        return $finalUrl;
    }
}