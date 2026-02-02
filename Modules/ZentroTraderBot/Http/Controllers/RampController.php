<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Modules\ZentroTraderBot\Entities\Suscriptions;

class RampController extends Controller
{
    protected $apiKey;
    protected $apiSecret;
    protected $apiBaseUrl;
    protected $gatewayBaseUrl;
    protected $environment;
    protected $referrerDomain;

    public function __construct()
    {
        parent::__construct();

        $this->apiKey = config("metadata.system.app.zentrotraderbot.ramp.apikey");
        $this->apiSecret = config("metadata.system.app.zentrotraderbot.ramp.apisecret");
        $this->referrerDomain = config("metadata.system.app.zentrotraderbot.ramp.referrerdomain");
        $this->environment = config("metadata.system.app.zentrotraderbot.ramp.environment");

        $this->apiBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".api");
        $this->gatewayBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".gateway");
    }

    public function redirect($user_id = null)
    {
        if (!$user_id) {
            return "Error: ID de usuario no proporcionado.";
        }

        $suscriptor = Suscriptions::where("user_id", $user_id)->first();

        if (!$suscriptor || !isset($suscriptor->data["wallet"]["address"])) {
            return "Lo sentimos, no pudimos encontrar tu billetera configurada.";
        }

        $walletAddress = $suscriptor->data["wallet"]["address"];
        $widgetUrl = $this->getWidgetUrl($walletAddress);

        if (!$widgetUrl) {
            return "Lo sentimos, hubo un error al generar tu sesión de pago. Por favor, intenta más tarde.";
        }

        return Redirect::to($widgetUrl);
    }

    public function getAccessToken()
    {
        $cacheKey = 'transak_access_token';
        $token = Cache::get($cacheKey);

        if ($token) {
            return $token;
        }

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'api-secret' => $this->apiSecret,
                'content-type' => 'application/json',
            ])->post("{$this->apiBaseUrl}/partners/api/v2/refresh-token", [
                        'apiKey' => $this->apiKey,
                    ]);

            if ($response->successful()) {
                $accessToken = $response->json()['data']['accessToken'] ?? null;
                if ($accessToken) {
                    Cache::put($cacheKey, $accessToken, 3000);
                    return $accessToken;
                }
            }

            Log::error("Error Transak Access Token: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Excepción en Transak Token: " . $e->getMessage());
        }

        return null;
    }

    public function getWidgetUrl($wallet)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken)
            return null;


        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access-token' => $accessToken,
            'content-type' => 'application/json',
        ])->post("{$this->gatewayBaseUrl}/api/v2/auth/session", [
                    'widgetParams' => [
                        'apiKey' => $this->apiKey,
                        'referrerDomain' => $this->referrerDomain,
                        'cryptoCurrencyCode' => 'USDC',
                        'network' => 'polygon',
                        'walletAddress' => $wallet,
                        'disableWalletAddressForm' => true,
                        'hideMenu' => true,
                        'productsAvailed' => 'BUY',
                        'isCryptoEditable' => false,
                        'displayCryptoCurrencyCode' => 'USD',
                        'fiatCurrency' => 'USD',
                        'themeColor' => '043927',
                        'exchangeScreenTitle' => 'Depositar en Kashio',
                        'environment' => $this->environment
                    ]
                ]);

        if ($response->failed()) {
            Log::error("Error Transak Session: " . $response->body());
            return null;
        }

        return $response->json()['data']['widgetUrl'] ?? null;
    }
}