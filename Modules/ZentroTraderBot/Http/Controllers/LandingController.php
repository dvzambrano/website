<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\Codes\QrService;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Http\Controllers\DeBridgeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;

class LandingController extends Controller
{
    private $bot;

    private $theme;
    private $qrService;

    public function __construct()
    {
        parent::__construct();

        $this->theme = config('zentrotraderbot.theme', 'FlexStart');

        $this->qrService = new QrService();

        $this->bot = TelegramBots::where(
            'name',
            '@' . config('zentrotraderbot.bot', 'KashioBot')
        )->first();

        $this->bot->connectToThisTenant();

        // Opcional: Compartir la config con el resto de la app
        app()->instance('active_bot', $this->bot);
    }

    public function getSuscriptor()
    {
        // 1. Obtener el ID de Telegram del usuario desde la sesión
        $telegramUser = session('telegram_user');

        $controller = new ZentroTraderBotController();
        $controller->receiveMessage($this->bot, [
            'message' => [
                'message_id' => "",
                'text' => "/start",
                'chat' => [
                    'id' => $telegramUser['user_id'],
                    'type' => "web"
                ],
                'from' => [
                    'id' => $telegramUser['user_id']
                ]
            ]
        ]);

        // tiene q ir aqui abajo porq arriba se esta simulando el comando /start del bot q suscribe al usuario y le crea su wallet
        return Suscriptions::where("user_id", $telegramUser['user_id'])->first();
    }

    public function index()
    {
        // Si no existe, podrías lanzar un error o cargar uno por defecto
        if (!$this->bot) {
            abort(404, 'Bot no configurado');
        }

        // Retornamos la vista dinámica
        return view("zentrotraderbot::themes.{$this->theme}.index", [
            'bot' => $this->bot
        ]);

    }

    public function dashboard()
    {
        $suscriptor = $this->getSuscriptor();

        // 2. Instanciar el controlador de Wallets
        $walletController = new TraderWalletController();

        $usdcBalance = 0;
        $transactions = [];
        try {
            // 3. Obtener Balance REAL (específicamente de USDC en Polygon)
            // El método getBalance que tienes devuelve el portfolio
            $usdcBalance = $walletController->getBalance($suscriptor);
            // 4. Obtener Transacciones 
            $transactions = $walletController->getRecentTransactions($suscriptor);
        } catch (\Throwable $th) {
            //throw $th;
        }

        return view("zentrotraderbot::themes.{$this->theme}.dashboard", [
            'balance' => $usdcBalance,
            'transactions' => $transactions,
            'qrService' => $this->qrService,
            'bot' => $this->bot
        ]);
    }

    public function pay()
    {
        // Forzar eliminación de la caché de rutas de deBridge
        //Cache::forget('debridge_routes_to_137');

        $suscriptor = $this->getSuscriptor();
        //dd($suscriptor->getWallet()["address"]);

        $bridge = new DeBridgeController();

        //dd(config('web3'));


        return view("zentrotraderbot::themes.{$this->theme}.pay", [
            'userWallet' => $suscriptor->getWallet()["address"],
            'bot' => $this->bot // Datos del bot para el branding
        ]);
    }

    // PASO 1: Obtener rutas soportadas
    public function getRoutes()
    {
        // 1. Obtener rutas comerciales de deBridge (terminan en Polygon 137)
        $bridge = new DeBridgeController();
        $debridgeRoutes = $bridge->getSupportedChainsInfo(137);

        // 2. Obtener datos técnicos de Chainlink / ChainID Network (con Cache para no saturar)
        $allChainsData = Cache::remember('external_chains_info', 3600, function () {
            $response = Http::timeout(3600)->get('https://chainid.network/chains.json');
            return $response->collect();
        });

        $enrichedRoutes = [];

        foreach ($debridgeRoutes as $id => $route) {
            $chainId = (int) $route['chainId'];

            // Buscamos la info técnica oficial por ChainID
            $chainInfo = $allChainsData->firstWhere('chainId', $chainId);

            if ($chainInfo) {
                $chainInfo["logo"] = $route["logoURI"];

                // --- LÓGICA DE NORMALIZACIÓN DE RPC ---
                $cleanRpc = [];
                // Dentro de tu bucle de redes en el controlador
                $rpcSource = (array) $chainInfo['rpc'];

                // 1. Lista de RPCs que SABEMOS que fallan o dan 401/CORS
                $badRpcs = [
                    'polygon-rpc.com',
                    'api.mycryptoapi.com',
                    'rpc.astral.global',
                    'pokt.network' // A veces da 401 sin ID
                ];

                $cleanRpc = [];
                foreach ($rpcSource as $rpc) {
                    if (str_contains($rpc, '${'))
                        continue; // Saltar los que piden API KEY

                    $isBad = false;
                    foreach ($badRpcs as $bad) {
                        if (str_contains($rpc, $bad)) {
                            $isBad = true;
                            break;
                        }
                    }

                    if (!$isBad && str_starts_with($rpc, 'https://')) {
                        $cleanRpc[] = $rpc;
                    }
                }

                // 2. PRIORIZACIÓN ESTRATÉGICA
                // Forzamos nodos que casi nunca fallan al inicio del array
                if ($chainId == 137) { // Polygon
                    array_unshift($cleanRpc, 'https://polygon-bor-rpc.publicnode.com', 'https://1rpc.io/matic');
                } elseif ($chainId == 1) { // Ethereum
                    array_unshift($cleanRpc, 'https://cloudflare-eth.com', 'https://eth.llamarpc.com');
                } elseif ($chainId == 56) { // BSC
                    array_unshift($cleanRpc, 'https://binance.llamarpc.com', 'https://bsc-dataseed.binance.org');
                }

                $chainInfo['rpc'] = array_values(array_unique($cleanRpc));

                // --- NORMALIZACIÓN DE EXPLORERS ---
                // Hacemos lo mismo con explorers para evitar errores en el Blade
                $chainInfo['explorers'] = array_values((array) ($chainInfo['explorers'] ?? []));


                $enrichedRoutes[$chainInfo['chainId']] = $chainInfo;

                //dd($chainInfo, $route);
            }
        }

        return response()->json($enrichedRoutes);
    }

    public function getTokens($chainId = 137)
    {
        $bridge = new DeBridgeController();
        $tokens = $bridge->tokenList($chainId);
        return response()->json($tokens);
    }

    // PASO 2: Obtener Estimación
    public function getQuote(Request $request)
    {

        $bridge = new DeBridgeController();

        $quote = $bridge->getEstimation(
            $request->srcChainId,
            $request->srcToken,
            $request->amount, // Cantidad a enviar
            137,              // Polygon
            '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359' // USDC
        );
        return response()->json($quote);
    }

    // PASO 3: Crear Orden (Generar Data para firmar)
    public function createOrder(Request $request)
    {
        try {
            $suscriptor = $this->getSuscriptor();
            $userSignerAddress = $request->input('userWallet');

            $bridge = new DeBridgeController();

            // Obtenemos la respuesta real de la API de deBridge
            $result = $bridge->createOrder(
                $request->input('srcChainId'),
                $request->input('srcToken'),
                $request->input('amount'),
                $userSignerAddress,
                $suscriptor->getWallet()["address"],
                $request->input('dstChainId'),
                $request->input('dstToken')
            );

            // IMPORTANTE: Debes devolver el resultado de la API
            // Si el bridge ya devuelve un array o un objeto JSON, Laravel lo enviará correctamente.
            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("🚨 Error en createOrder: " . $e->getMessage());
            return response()->json([
                'error' => 'Error al procesar la orden',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}