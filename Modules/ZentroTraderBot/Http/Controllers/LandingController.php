<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\Codes\QrService;
use Modules\ZentroTraderBot\Entities\Suscriptions;

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
        $suscriptor = Suscriptions::where("user_id", $telegramUser['user_id'])->first();

        // 2. Instanciar el controlador de Wallets
        $walletController = new TraderWalletController();

        // 3. Obtener Balance REAL (específicamente de USDC en Polygon)
        // El método getBalance que tienes devuelve el portfolio
        $usdcBalance = $walletController->getBalance($suscriptor);

        // 4. Obtener Transacciones 
        $transactions = $walletController->getRecentTransactions($suscriptor);
        //$transactions = [];

        return view("zentrotraderbot::themes.{$this->theme}.dashboard", [
            'balance' => $usdcBalance,
            'transactions' => $transactions,
            'qrService' => $this->qrService,
            'bot' => $this->bot
        ]);
    }

    public function pay()
    {
        // 1. Obtener el ID de Telegram del usuario desde la sesión
        $telegramUser = session('telegram_user');

        return view("zentrotraderbot::themes.{$this->theme}.pay", [
            'qrService' => $this->qrService,
            'bot' => $this->bot
        ]);
    }
}