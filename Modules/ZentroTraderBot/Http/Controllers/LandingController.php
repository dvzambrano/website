<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\Codes\QrService;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Http\Controllers\DeBridgeController;
use Illuminate\Http\Request;

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

        $chains = $bridge->getSupportedChainsInfo();
        //dd($chains);



        return view("zentrotraderbot::themes.{$this->theme}.pay", [
            'userWallet' => $suscriptor->getWallet()["address"],
            'chains' => $chains,
            'bot' => $this->bot // Datos del bot para el branding
        ]);
    }

    // PASO 1: Obtener rutas soportadas
    public function getRoutes()
    {
        $bridge = new DeBridgeController();
        // Obtenemos rutas que terminan en Polygon (137)
        $routes = $bridge->getSupportedRoutesTo(137);
        return response()->json($routes);
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


        $bridge = new DeBridgeController();

        // Preparamos la orden para que el dinero llegue a la wallet del usuario
        $order = $bridge->createOrder(
            $request->srcChainId,
            $request->srcToken,
            $request->amount,
            137,
            '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359',
            $request->userWallet // ReceiverAddress en destino
        );

        return response()->json($order);
    }
}