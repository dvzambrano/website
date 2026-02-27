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
use Modules\Laravel\Services\BehaviorService;

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

        $isLocal = app()->environment('local');
        $time = $isLocal ? 86400 : 3600; // 1 día en local, 1 hora en producción
        // 2. Obtener datos técnicos de Chainlink / ChainID Network (con Cache para no saturar)
        $allChainsData = BehaviorService::cache('external_chains_info', function () {
            $response = Http::timeout(BehaviorService::timeout())->get('https://chainid.network/chains.json');
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
                $rpcSource = (array) $chainInfo['rpc'];

                // 1. Lista de RPCs que SABEMOS que fallan o dan 401/CORS
                $badRpcs = [
                    'polygon-rpc.com',
                    'api.mycryptoapi.com',
                    'rpc.astral.global',
                    'pokt.network' // A veces da 401 sin ID
                ];

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

        $srcChainId = (int) $request->srcChainId;
        $dstChainId = config('web3.networks.POL.chain_id'); // Tu destino fijo en Polygon
        $srcToken = $request->srcToken;
        $amount = $request->amount;
        $dstToken = config('web3.networks.POL.tokens.USDC.address');// USDC Polygon

        // CASO A: Misma Red (Polygon -> Polygon)
        if ($srcChainId === $dstChainId) {
            if (strtolower($srcToken) === strtolower($dstToken)) {
                $suscriptor = $this->getSuscriptor();
                $address = $suscriptor->getWallet()["address"];

                // RESPUESTA MANUAL PARA TRANSFERENCIA DIRECTA
                return response()->json([
                    'isDirectTransfer' => true,
                    'estimation' => [
                        'srcChainTokenIn' => [
                            'symbol' => 'USDC',
                            'decimals' => config('web3.networks.POL.tokens.USDC.decimals'),
                            'amount' => $amount
                        ],
                        'dstChainTokenOut' => [
                            'symbol' => 'USDC',
                            'decimals' => config('web3.networks.POL.tokens.USDC.decimals'),
                            'amount' => $amount,
                            'recommendedAmount' => $amount
                        ]
                    ],
                    'tx' => [
                        // Generamos los datos para una transferencia ERC20 estándar
                        'to' => $dstToken, // El contrato del token
                        //'address' => $address, // El destinatario
                        // Codificamos la función transfer(address _to, uint256 _value)
                        'data' => $this->encodeERC20Transfer($suscriptor->getWallet()["address"], $amount),
                        'value' => "0"
                    ]
                ]);
            }

            // Usamos la lógica Single-Chain
            $rawQuote = $bridge->getSameChainEstimation($srcChainId, $srcToken, $amount, $dstToken);
            // --- NORMALIZACIÓN ---
            $quote = [
                'isSameChain' => true,
                'estimation' => [
                    'srcChainTokenIn' => $rawQuote['estimation']['tokenIn'],
                    'dstChainTokenOut' => [
                        'chainId' => $dstChainId,
                        'address' => $rawQuote['estimation']['tokenOut']['address'],
                        'symbol' => $rawQuote['estimation']['tokenOut']['symbol'],
                        'decimals' => $rawQuote['estimation']['tokenOut']['decimals'],
                        // Normalizamos el nombre del campo para el JS
                        'amount' => $rawQuote['estimation']['tokenOut']['amount'],
                        'recommendedAmount' => $rawQuote['estimation']['tokenOut']['amount'],
                    ]
                ],
                'tx' => $rawQuote['tx'] ?? null, // deSwap suele incluir el objeto tx si pides la cotización completa
                'fixFee' => "0", // No hay fee de puente
                'protocolFee' => $rawQuote['estimation']['protocolFee'] ?? "0"
            ];
        }
        // CASO B: Puente (Cualquier red -> Polygon)
        else {
            $quote = $bridge->getEstimation(
                $srcChainId,
                $srcToken,
                $amount,
                $dstChainId,
                $dstToken
            );
        }

        return response()->json($quote);
    }
    private function encodeERC20Transfer($to, $amount)
    {
        // Firma de la función transfer(address,uint256): 0xa9059cbb
        $methodId = "0xa9059cbb";
        $paddedAddress = str_pad(substr($to, 2), 64, "0", STR_PAD_LEFT);

        // El amount ya debe venir en unidades mínimas (base 10) desde el request
        // Lo convertimos a hexadecimal y lo rellenamos a 64 caracteres
        $hexAmount = str_pad(dechex($amount), 64, "0", STR_PAD_LEFT);

        return $methodId . $paddedAddress . $hexAmount;
    }

    // PASO 3: Crear Orden (Generar Data para firmar)
    public function createOrder(Request $request)
    {
        try {
            $srcChainId = (int) $request->input('srcChainId');
            $dstChainId = (int) $request->input('dstChainId');
            if ($srcChainId === $dstChainId) {
                // Lógica para generar la transacción de intercambio simple o transferencia
                // Aquí llamarías a un método diferente en tu DeBridgeController
                // Ejemplo: $result = $bridge->createLocalSwap(...);

                // Por ahora, para debug, podemos retornar un aviso:
                return response()->json(['info' => 'Misma red detectada, usando flujo local']);
            }


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