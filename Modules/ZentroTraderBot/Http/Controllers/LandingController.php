<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Entities\Actors;
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

    public function pay($user)
    {
        $actor = Actors::where('data->telegram->username', $user)->first();
        if (!$actor)
            abort(404);
        $suscriptor = Suscriptions::where("user_id", $actor->user_id)->first();
        if (!$suscriptor)
            abort(404);
        //dd($suscriptor->getWallet()["address"]);

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

    /**
     * Codifica la función transfer(address,uint256) para envíos directos de tokens
     */
    private function encodeERC20Transfer($to, $amount)
    {
        // Firma de la función transfer(address,uint256): 0xa9059cbb
        $methodId = "0xa9059cbb"; // selector de transfer(address,uint256)
        $paddedAddress = str_pad(substr($to, 2), 64, "0", STR_PAD_LEFT);

        // El amount ya debe venir en unidades mínimas (base 10) desde el request
        // Lo convertimos a hexadecimal y lo rellenamos a 64 caracteres
        // Usamos BCMath si el número es muy grande para dechex()
        $hexAmount = str_pad($this->bcdechex($amount), 64, "0", STR_PAD_LEFT);

        return $methodId . $paddedAddress . $hexAmount;
    }

    private function bcdechex($dec)
    {
        $last = bcmod($dec, 16);
        $remain = bcdiv(bcsub($dec, $last), 16);
        if ($remain > 0) {
            return $this->bcdechex($remain) . dechex($last);
        } else {
            return dechex($last);
        }
    }

    // PASO 3: Crear Orden (Generar Data para firmar)
    public function createOrder(Request $request)
    {
        try {
            $bridge = new DeBridgeController();
            $suscriptor = $this->getSuscriptor();


            $srcChainId = (int) $request->input('srcChainId');
            $srcToken = $request->input('srcToken');
            $amount = $request->input('amount'); // Ya viene en unidades mínimas (BigInt string)

            $dstChainId = (int) $request->input('dstChainId');
            $dstToken = $request->input('dstToken');
            $dstWallet = $suscriptor->getWallet()["address"];

            $userSignerAddress = $request->input('userWallet');

            // --- CASO 1: MISMA RED (Polygon -> Polygon) ---
            if ($srcChainId === $dstChainId) {
                // Sub-caso A: USDC -> USDC (Transferencia Directa)
                if (strtolower($srcToken) === strtolower($dstToken)) {
                    return response()->json([
                        'type' => 'direct_transfer',
                        'tx' => [
                            'to' => $srcToken, // El contrato del token
                            'data' => $this->encodeERC20Transfer($dstWallet, $amount),
                            'value' => "0",
                            'chainId' => $srcChainId
                        ]
                    ]);
                }

                // Sub-caso B: OTRO TOKEN -> USDC (Single Chain Swap)
                $rawSwap = $bridge->getSameChainTransaction($srcChainId, $srcToken, $amount, $dstToken, $userSignerAddress, $dstWallet);
                return response()->json([
                    'type' => 'same_chain_swap',
                    'tx' => [
                        'to' => $rawSwap['tx']['to'],
                        'data' => $rawSwap['tx']['data'],
                        'value' => $rawSwap['tx']['value'] ?? "0",
                        'chainId' => $srcChainId,
                        'allowanceTarget' => $rawSwap['tx']['allowanceTarget'] ?? $rawSwap['tx']['to'],
                    ]
                ]);
            }

            // --- CASO 2: PUENTE (Cross-chain) ---
            // Aquí usamos la lógica de DLN que ya tenías
            // Obtenemos la respuesta real de la API de deBridge
            $rawBridge = $bridge->createOrder(
                $srcChainId,
                $srcToken,
                $amount,
                $userSignerAddress,
                $dstWallet,
                $dstChainId,
                $dstToken,
            );

            return response()->json([
                'type' => 'cross_chain_bridge',
                'tx' => [
                    'to' => $rawBridge['tx']['to'],
                    'data' => $rawBridge['tx']['data'],
                    'value' => $rawBridge['tx']['value'] ?? "0",
                    'chainId' => $srcChainId,
                    'allowanceTarget' => $rawBridge['tx']['allowanceTarget'] ?? null
                ],
                'orderId' => $rawBridge['orderId'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error("🚨 Error en createOrder: " . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}