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

    public function __construct()
    {
        parent::__construct();

        $this->apiKey = config("metadata.system.app.zentrotraderbot.ramp.apikey");
        $this->apiSecret = config("metadata.system.app.zentrotraderbot.ramp.apisecret");

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

        $widgetUrl = $this->getWidgetUrl($suscriptor);

        if (!$widgetUrl) {
            return "Lo sentimos, hubo un error al generar tu sesión de pago. Por favor, intenta más tarde.";
        }

        return Redirect::to($widgetUrl);
    }

    public function success()
    {
        Log::error("Ramp success: " . json_encode(request()->all()));

        // Capturamos los datos que Transak inyecta en la URL
        $status = request('status');
        $orderId = request('orderId');
        $cryptoAmount = request('cryptoAmount');
        $walletAddress = request('walletAddress');

        if ($status === 'COMPLETED') {
            return redirect("https://t.me/TuBotNombre?start=check_order_" . $orderId);
        }

        return redirect("https://t.me/TuBotNombre?start=order_pending");
    }
    public function webhook()
    {
        // 1. Logueamos TODO para ver la estructura real que nos manda Transak hoy
        Log::info("TRANSK WEBHOOK BODY: " . json_encode(request()->all()));
        Log::info("TRANSAK WEBHOOK HEADERS: " . json_encode(request()->headers->all()));

        // 2. Extraer los datos (Asumiendo JSON plano por ahora)
        $event = request()->input('event');
        $data = request()->input('data'); // Aquí viene la info de la orden

        if (!$event || !$data) {
            // Si no viene como JSON plano, podría venir como JWT
            // Por ahora retornamos error para forzar el log y revisar
            return response()->json(['message' => 'Format not recognized'], 400);
        }

        // 3. Procesar el evento
        if ($event === 'ORDER_COMPLETED') {
            $orderId = $data['id'] ?? null;
            $userId = $data['partnerCustomerId'] ?? null;
            $amount = $data['cryptoAmount'] ?? 0;

            Log::info("¡Pago exitoso para el usuario {$userId}! Monto: {$amount}");

            // AQUÍ: Tu lógica para subir el saldo en Kashio

            return response()->json(['status' => 'success'], 200);
        }

        return response()->json(['status' => 'received'], 200);
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

    public function getWidgetUrl($suscriptor)
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
                        'productsAvailed' => 'BUY',
                        'isCryptoEditable' => false,
                        //'fiatCurrency' => 'USD',
                        'themeColor' => '043927',
                        'exchangeScreenTitle' => 'Depositar en Kashio',
                        'environment' => $this->environment,
                        'redirectURL' => route('ramp-success'),
                        'partnerCustomerId' => $suscriptor->user_id,
                    ]
                ]);

        /*
        redirectURL
        https://www.url.com/?orderId={{id}}&fiatCurrency={{code}}&cryptoCurrency={{code}}&fiatAmount={{amount}}&cryptoAmount={{amount}}&isBuyorSell=Sell&status={{orderStatus}}&walletAddress={{address}}&totalFeeInFiat={{amount}}&partnerCustomerId={{id}}&partnerOrderId={{id}}&network={{code}}

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

        */

        if ($response->failed()) {
            Log::error("Error Transak Session: " . $response->body());
            return null;
        }

        return $response->json()['data']['widgetUrl'] ?? null;
    }
    public function registerWebhook()
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withHeaders([
            'access-token' => $accessToken
        ])->post("{$this->gatewayBaseUrl}/api/v2/partners/webhooks", [
                    'webhookUrl' => route('ramp-webhook'),
                    'events' => ['ORDER_COMPLETED', 'ORDER_FAILED']
                ]);

        return $response->json();
    }
}