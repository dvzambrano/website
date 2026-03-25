<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Entities\Actors;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\Laravel\Services\Codes\QrService;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Http\Controllers\DeBridgeController;
use Modules\Web3\Http\Controllers\AlchemyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Modules\Laravel\Services\BehaviorService;
use Modules\Web3\Services\Web3MathService;
use Modules\Web3\Services\ConfigService;

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
        $sessionUser = session('telegram_user');

        $controller = new ZentroTraderBotController();
        $controller->receiveMessage($this->bot, [
            'message' => [
                'message_id' => "",
                'text' => "/start",
                'chat' => [
                    'id' => $sessionUser['user_id'],
                    'type' => "web"
                ],
                'from' => [
                    'id' => $sessionUser['user_id']
                ]
            ]
        ]);

        // tiene q ir aqui abajo porq arriba se esta simulando el comando /start del bot q suscribe al usuario y le crea su wallet
        return Suscriptions::where("user_id", $sessionUser['user_id'])->first();
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

        $balance = 0;
        $transactions = [];
        try {
            // 3. Obtener Balance REAL (específicamente de BASE_TOKEN en Polygon)
            // El método getBalance que tienes devuelve el portfolio
            $balance = $walletController->getBalance($suscriptor);
            // 4. Obtener Transacciones 
            $transactions = $walletController->getRecentTransactions($suscriptor);
        } catch (\Throwable $th) {
            //throw $th;
        }
        //dd($suscriptor->data, $balance, $transactions);

        return view("zentrotraderbot::themes.{$this->theme}.dashboard", [
            'balance' => $balance,
            'transactions' => $transactions,
            'qrService' => $this->qrService,
            'bot' => $this->bot
        ]);
    }

    public function pay($user)
    {
        $tenant = app('active_bot');

        $actor = Actors::where('data->telegram->username', $user)->first();
        if (!$actor)
            abort(404);
        $suscriptor = Suscriptions::where("user_id", $actor->user_id)->first();
        if (!$suscriptor)
            abort(404);
        //dd($suscriptor->getWallet()["address"]);

        $user = json_decode(TelegramController::getUserInfo($actor->user_id, $tenant->token), true)["result"];
        //dd($user);

        $user["photo_url"] = "";
        $photos = TelegramController::getUserPhotos($actor->user_id, $tenant->token);
        if (!empty($photos) && isset($photos[0][0]['file_id'])) {
            $fileId = $photos[0][0]['file_id'];
            $fileResponse = json_decode(self::getFileUrl($fileId, $tenant->token), true);
            if (isset($fileResponse['ok']) && $fileResponse['ok'])
                $user["photo_url"] = $fileResponse['result']['file_path'];
        }

        $destChain = (int) ConfigService::getActiveNetwork()['chainId'];
        //dd(strtoupper(ConfigService::getActiveNetwork()['shortName']));
        $destToken = ConfigService::getToken(
            env('BASE_TOKEN'),
            strtoupper(ConfigService::getActiveNetwork()['shortName'])
        )['address'];

        return view("zentrotraderbot::themes.{$this->theme}.pay", [
            'user' => $user,
            'dstWallet' => $suscriptor->getWallet()["address"],
            'destChain' => $destChain,
            'destToken' => $destToken,
            'bot' => $this->bot // Datos del bot para el branding
        ]);
    }

    // PASO 1: Obtener rutas soportadas
    public function getChains()
    {
        // 1. Obtener rutas comerciales de deBridge (terminan en Polygon 137)
        $bridge = new DeBridgeController();
        $chains = $bridge->getSupportedChainsInfo();

        // 2. Obtener datos técnicos de Chainlink / ChainID Network (con Cache para no saturar)
        // php artisan cache:forget external_chains_info
        $allChainsData = BehaviorService::cache('external_chains_info', function () {
            Log::info("✅ Refrescando base de datos de ChainID desde chainid.network.");
            $response = Http::timeout(BehaviorService::timeout())->get('https://chainid.network/chains.json');
            return $response->collect();
        }, 604800, 172800);
        // 604800 es una semana en segundos
        // 172800 son 2 dias en segundos

        $enrichedChains = [];
        foreach ($chains as $route) {
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


                $enrichedChains[$chainInfo['chainId']] = $chainInfo;

                //dd($chainInfo, $route);
            }
        }

        //dd($enrichedChains);
        return response()->json($enrichedChains);
    }

    public function getBalances(string $address, $chainId = 137, $networkKey = "POL")
    {
        $bridge = new DeBridgeController();
        $tokensData = $bridge->tokenList($chainId, 604800);
        $supportedTokens = collect($tokensData["tokens"]);

        // 1. Obtener Balances desde Alchemy
        $nativeHex = AlchemyController::getNativeBalance(config('zentrotraderbot.alchemy_api_key'), $address, $networkKey);
        $erc20Balances = AlchemyController::getTokenBalances(config('zentrotraderbot.alchemy_api_key'), $address, [], $networkKey);

        // 2. Unificar balances en una sola colección para el Map
        // Añadimos el nativo como si fuera un resultado más de Alchemy
        $allBalances = collect($erc20Balances)->prepend([
            'contractAddress' => '0x0000000000000000000000000000000000000000',
            'tokenBalance' => $nativeHex
        ]);

        // 3. Procesar todo con una sola lógica
        $portfolio = $allBalances->map(function ($balance) use ($supportedTokens) {
            $contract = strtolower($balance['contractAddress']);

            // Filtro rápido: si el balance es cero, ni buscamos info
            if ($balance['tokenBalance'] === '0x' . str_repeat('0', 64) || $balance['tokenBalance'] === '0x0') {
                return null;
            }

            $info = $supportedTokens->firstWhere('address', $contract);

            if ($info) {
                $amount = Web3MathService::hexToDecimal($balance['tokenBalance'], $info['decimals']);
                if ($amount > 0.00009) {// Si el balance es menor a 0.0001, lo ignoramos
                    $info['balance'] = Web3MathService::hexToDecimal($balance['tokenBalance'], $info['decimals']);
                    return $info;
                }
            }

            return null;
        })->filter()->values();

        return response()->json($portfolio);
    }

    // PASO 2: Obtener Estimación
    public function getQuote(Request $request)
    {
        /*
        http://localhost/website/pay/quote?
        // srcChainId=137&
        // srcToken=0x3c499c542cef5e3811e1192ce70d8cc03d5c3359&
        // amount=1200&
        // dstChainId=137&dstToken=0x3c499c542cef5e3811e1192ce70d8cc03d5c3359

         */
        $bridge = new DeBridgeController();

        $srcChainId = (int) $request->input('srcChainId');
        $srcToken = $request->input('srcToken');
        $amount = $request->input('amount'); // Ya viene en unidades mínimas (BigInt string)
        $dstChainId = (int) $request->input('dstChainId');
        $dstToken = $request->input('dstToken');
        $dstWallet = $request->input('dstWallet');





        // CASO A: Misma Red (Polygon -> Polygon)
        if ($srcChainId === $dstChainId) {
            if (strtolower($srcToken) === strtolower($dstToken)) {
                $token = ConfigService::getToken(env('BASE_TOKEN'), env('BASE_NETWORK'));
                // RESPUESTA MANUAL PARA TRANSFERENCIA DIRECTA
                return response()->json([
                    'isDirectTransfer' => true,
                    'estimation' => [
                        'srcChainTokenIn' => [
                            'symbol' => $token["symbol"],
                            'decimals' => $token["decimals"],
                            'amount' => $amount
                        ],
                        'dstChainTokenOut' => [
                            'symbol' => $token["symbol"],
                            'decimals' => $token["decimals"],
                            'amount' => $amount,
                            'recommendedAmount' => $amount
                        ]
                    ],
                    'tx' => [
                        // Generamos los datos para una transferencia ERC20 estándar
                        'to' => $dstToken, // El contrato del token
                        //'address' => $address, // El destinatario
                        // Codificamos la función transfer(address _to, uint256 _value)
                        'data' => $this->encodeERC20Transfer($dstWallet, $amount),
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

            $srcChainId = (int) $request->input('srcChainId');
            $srcToken = $request->input('srcToken');
            $amount = $request->input('amount'); // Ya viene en unidades mínimas (BigInt string)

            $dstChainId = (int) $request->input('dstChainId');
            $dstToken = $request->input('dstToken');
            $dstWallet = $request->input('dstWallet');

            $userSignerAddress = $request->input('userWallet');

            // --- CASO 1: MISMA RED (Polygon -> Polygon) ---
            if ($srcChainId === $dstChainId) {
                // Sub-caso A: BASE_TOKEN -> BASE_TOKEN (Transferencia Directa)
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

                // Sub-caso B: OTRO TOKEN -> BASE_TOKEN (Single Chain Swap)
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