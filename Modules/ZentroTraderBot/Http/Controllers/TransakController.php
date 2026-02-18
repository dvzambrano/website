<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Lang;
use Modules\ZentroTraderBot\Contracts\RampProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransakController extends Controller implements RampProviderInterface
{
    protected $apiKey;
    protected $apiSecret;
    protected $apiBaseUrl;
    protected $gatewayBaseUrl;
    protected $environment;

    public function __construct()
    {
        parent::__construct();

        $this->apiKey = config("metadata.system.app.zentrotraderbot.ramp.apikey");
        $this->apiSecret = config("metadata.system.app.zentrotraderbot.ramp.apisecret");

        $this->environment = config("metadata.system.app.zentrotraderbot.ramp.environment");

        $this->apiBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".api");
        $this->gatewayBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".gateway");
    }

    public function redirect($action, $key, $secret, $user_id)
    {
        // Recuperamos el bot que el Middleware ya encontró y guardó
        $tenant = app('active_bot');
        // Forzamos la consulta a la base de datos del Tenant
        $suscriptor = Suscriptions::on('tenant')->where("user_id", $user_id)->first();
        if (!$suscriptor || !isset($suscriptor->data["wallet"]["address"])) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.suscriptor");
        }

        $widgetUrl = $this->getWidgetUrl($tenant, $suscriptor, strtoupper($action));

        if (!$widgetUrl) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.widgeturl");
        }

        return Redirect::to($widgetUrl);
    }

    public function processWebhook1(Request $request): JsonResponse
    {

        // Si el soporte de Transak está probando el endpoint, 
        // esto responderá con éxito antes de intentar procesar lógica compleja.
        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String()
        ], 200);
    }

    public function processWebhook(Request $request): array
    {
        $array = array();
        $payload = $request->all();
        // Logueamos siempre para auditoría interna
        Log::info("Transak Webhook - Evento recibido:" . json_encode($payload));

        try {
            $token = $request->getContent();
            if (empty($token)) {
                return $array;
            }

            // 1. Desencriptar el JWT (Válido para ambos tipos de Webhook)
            // Importante: Transak usa el algoritmo HS256
            $decoded = JWT::decode($token, new Key($this->getAccessToken(), 'HS256'));
            // pero a veces firman el Webhook con el API Secret (el estático)
            //$decoded = JWT::decode($token, new Key($this->apiSecret, 'HS256'));

            $payload = json_decode(json_encode($decoded), true);

            $array["eventID"] = $payload['eventID'] ?? null;
            $data = $payload['webhookData'] ?? null;

            // Los de órdenes empiezan por "ORDER_"
            if ($data && str_starts_with($array["eventID"], 'ORDER_')) {
                $array["order"] = true;
                $array["botId"] = $data['partnerOrderId'];
                $array["orderId"] = $data['id'];
                $array["userId"] = $data['partnerCustomerId'];
                $array["amount"] = $data['cryptoAmount'];
                $array["status"] = $data['status'];
                $array["payload"] = $payload;

                Log::info("Transak ORDER Update - User: " . $array["userId"] . " - Order: " . $array["orderId"] . "" . " - Status: " . $array["status"]);
            }

            // Los eventos de KYC empiezan por "USER_" (ej: USER_KYC_APPROVED)
            if ($data && str_starts_with($array["eventID"], 'USER_')) {
                $array["kyc"] = true;
                $array["userId"] = $data['partnerCustomerId'];
                $array["status"] = $data['kycStatus'];

                Log::info("Transak KYC Update - User: " . $array["userId"] . " - Status: " . $array["status"]);
            }


        } catch (\Exception $e) {
            Log::error("Transak Webhook Error: " . $e->getMessage());
        }

        return $array;
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

    public function getWidgetUrl($tenant, $suscriptor, $action = "BUY"): ?string
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken)
            return null;

        /*
        Método,                             Resultado ejemplo,          Notas
        request()->getSchemeAndHttpHost(),   https://dev.micalme.com,    Incluye el protocolo (http/https).
        request()->getHost(),                dev.micalme.com,            Solo el dominio/subdominio.
        request()->root(),                   https://dev.micalme.com,    "Similar al primero, muy usado en Laravel."
        request()->getHttpHost(),           dev.micalme.com:443,        Incluye el puerto si es uno no estándar.
        */

        $wallet = $suscriptor->data["wallet"]["address"];

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'access-token' => $accessToken,
            'content-type' => 'application/json',
        ])->post("{$this->gatewayBaseUrl}/api/v2/auth/session", [
                    'widgetParams' => [
                        'apiKey' => $this->apiKey,
                        'referrerDomain' => request()->getHost(),
                        //'defaultCryptoAmount' => 1,
                        //'cryptoAmount' => 1,
                        'cryptoCurrencyCode' => 'USDC',
                        'network' => 'polygon',
                        //'networks' => 'ethereum, polygon,terra, mainnet',
                        'walletAddress' => $wallet,
                        'disableWalletAddressForm' => true,
                        'hideMenu' => true,
                        'productsAvailed' => $action,
                        'isCryptoEditable' => false,
                        //'fiatCurrency' => 'USD',
                        'themeColor' => '043927',
                        'exchangeScreenTitle' => Lang::get("zentrotraderbot::bot.prompts." . strtolower($action) . ".exchangetitle", [
                            "name" => $tenant->code
                        ]),
                        'environment' => $this->environment,
                        'redirectURL' => route('ramp-success', array(
                            "key" => $tenant->key,
                            "secret" => $tenant->secret,
                            "user_id" => $suscriptor->user_id
                        )),
                        'partnerCustomerId' => $suscriptor->user_id,
                        'partnerOrderId' => $tenant->id,
                    ]
                ]);

        if ($response->failed()) {
            Log::error("Error Transak Session: " . $response->body());
            return null;
        }

        return $response->json()['data']['widgetUrl'] ?? null;
    }
}