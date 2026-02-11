<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Entities\Ramporders;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Lang;
use Modules\ZentroTraderBot\Contracts\RampProviderInterface;

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
        $bot = app('active_bot');
        // Forzamos la consulta a la base de datos del Tenant
        $suscriptor = Suscriptions::on('tenant')->where("user_id", $user_id)->first();
        if (!$suscriptor || !isset($suscriptor->data["wallet"]["address"])) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.suscriptor");
        }

        $widgetUrl = $this->getWidgetUrl($bot, $suscriptor, strtoupper($action));

        if (!$widgetUrl) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.widgeturl");
        }

        return Redirect::to($widgetUrl);
    }

    public function success($key, $secret, $user_id)
    {
        //Log::info("Ramp success redirect hit: " . json_encode(request()->all()));

        // Recuperamos el bot que el Middleware ya encontró y guardó
        $bot = app('active_bot');

        /*
        redirectURL
        User will be redirected to the partner page passed in redirectURL along with the following parameters appended to the URL:
        orderId: Transak order ID
        fiatCurrency: Payout fiat currency
        cryptoCurrency: Token symbol to be transferred
        fiatAmount: Expected payout fiat amount
        cryptoAmount: Amount of crypto to be transferred
        isBuyOrSell: Will be 'Sell' in case of off ramp
        status: Transak order status
        walletAddress: Destination wallet address where crypto should be transferred
        totalFeeInFiat: Total fee charged in local currency for the transaction
        partnerCustomerId: Partner's customer ID (if present)
        partnerOrderId: Partner's order ID (if present)
        network: Network on which relevant crypto currency needs to be transferred

        https://dev.micalme.com/ramp/success?
        orderId=4d6a0067-2d37-493f-b671-3424f3207f25
        &fiatCurrency=USD
        &cryptoCurrency=USDC
        &fiatAmount=300
        &cryptoAmount=294
        &isBuyOrSell=BUY
        &status=FAILED
        &walletAddress=0xd2531438b90232f4aab4ddfc6f146474e84e1ea1
        &totalFeeInFiat=6
        &isNFTOrder=undefined
        &network=polygon
        */

        // Guardamos en la tabla que creamos (ramporders)
        $this->createRamporder(
            $bot->id,
            request('orderId'),
            $user_id,
            request('cryptoAmount'),
            request('status'),
            request()->all()
        );

        return redirect("https://t.me/" . $bot->code);
    }

    public function processWebhook(): array
    {
        return array();
    }

    /**
     * Webhook para el procesamiento de Órdenes (Pagos)
     */
    public function webhookOrder()
    {
        try {
            $payload = request()->all();
            // Logueamos siempre para auditoría interna
            Log::info("Transak Order Webhook - Evento recibido:" . json_encode($payload));

            // 1. Transak envía el JWT directamente en el cuerpo de la petición (body)
            $token = request()->getContent();

            if (empty($token)) {
                Log::warning("Transak Webhook: Cuerpo de petición vacío.");
                return response()->json(['status' => 'empty'], 200);
            }

            // 2. Desencriptar el token usando tu API Secret
            // Importante: Transak usa el algoritmo HS256
            $decoded = JWT::decode($token, new Key($this->getAccessToken(), 'HS256'));
            //$decoded = JWT::decode($token, new Key($this->apiSecret, 'HS256'));

            // Convertimos el objeto de la librería a un array de Laravel para que sea fácil de usar
            $payload = json_decode(json_encode($decoded), true);

            // Ahora sí, tenemos acceso a eventID y webhookData
            $eventID = $payload['eventID'] ?? null;
            $data = $payload['webhookData'] ?? null;

            Log::info("Transak Webhook Desencriptado - Evento: " . $eventID);

            if ($eventID && $data) {
                // Guardamos en la tabla que creamos (ramporders)
                $order = $this->createRamporder(
                    $data['partnerOrderId'],
                    $data['id'],
                    $data['partnerCustomerId'],
                    $data['cryptoAmount'],
                    $data['status'],
                    $payload
                );

                if ($eventID === 'ORDER_COMPLETED') {
                    $this->processKashioDeposit($order);
                }
            }

        } catch (\Exception $e) {
            // Logueamos el error pero devolvemos 200 para que Transak no marque el webhook como "Caído"
            Log::error("Error procesando Webhook de Orden: " . $e->getMessage());
        }

        // Si el soporte de Transak está probando el endpoint, 
        // esto responderá con éxito antes de intentar procesar lógica compleja.
        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String()
        ], 200);
    }

    private function createRamporder($bot_id, $orderId, $userId, $amount, $status, $payload)
    {
        // Guardamos en la tabla que creamos (ramporders)
        return Ramporders::updateOrCreate(
            ['order_id' => $orderId],
            [
                'user_id' => $userId,
                'bot_id' => $bot_id,
                'amount' => $amount,
                'status' => $status,
                'raw_data' => $payload
            ]
        );
    }

    /**
     * Webhook para el procesamiento de KYC (Verificación de Identidad)
     */
    public function webhookKyc()
    {
        try {
            $payload = request()->all();

            Log::info("Transak KYC Webhook - Evento recibido:" . json_encode($payload));

        } catch (\Exception $e) {
            Log::error("Error procesando Webhook de KYC: " . $e->getMessage());
        }
        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String()
        ], 200);
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

    public function getWidgetUrl($bot, $suscriptor, $action = "BUY"): ?string
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
                            "name" => $bot->code
                        ]),
                        'environment' => $this->environment,
                        'redirectURL' => route('ramp-success', array(
                            "key" => $bot->key,
                            "secret" => $bot->secret,
                            "user_id" => $suscriptor->user_id
                        )),
                        //'partnerCustomerId' => $suscriptor->user_id,
                        //'partnerOrderId' => $bot->id,
                    ]
                ]);

        if ($response->failed()) {
            Log::error("Error Transak Session: " . $response->body());
            return null;
        }

        return $response->json()['data']['widgetUrl'] ?? null;
    }
}