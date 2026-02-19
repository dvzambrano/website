<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\Codes\QrService;

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
        $userId = $telegramUser['id'];

        // 2. Instanciar el controlador de Wallets
        $walletController = new TraderWalletController();

        // 3. Obtener Balance REAL (específicamente de USDC en Polygon)
        // El método getBalance que tienes devuelve el portfolio
        $usdcBalance = $walletController->getBalance($userId);

        // 4. Obtener Transacciones 
        $transactions = $walletController->getRecentTransactions($userId);
        //$transactions = [];

        return view("zentrotraderbot::themes.{$this->theme}.dashboard", [
            'balance' => $usdcBalance,
            'transactions' => $transactions,
            'qrService' => $this->qrService,
            'bot' => $this->bot
        ]);
    }
}