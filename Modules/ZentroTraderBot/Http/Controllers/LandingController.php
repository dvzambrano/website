<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\Codes\QrService;
use Modules\Web3\Http\Controllers\AlchemyController;

class LandingController extends Controller
{
    private $bot;

    private $theme;
    private $qrService;

    public function __construct()
    {
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
        $balanceData = $walletController->getBalance($userId, 'USDC');
        dd($balanceData);

        // Extraemos el valor numérico de la respuesta (ajusta según tu config/web3.php)
        // Asumiendo que 'Polygon' es el nombre en tu config
        $usdcBalance = $balanceData['portfolio']['Polygon']['assets']['USDC'] ?? 0;

        // 4. Obtener Transacciones (Usando Alchemy)
        // Como AlchemyController ya lo tienes integrado para webhooks, 
        // podemos usar su API para traer el historial.
        $transactions = $this->getRecentTransactions($balanceData['address']);

        $authToken = config('metadata.system.app.zentrotraderbot.alchemy.authtoken');
        //AlchemyController::getRecentTransactions($authToken, $balanceData['address']);

        return view("zentrotraderbot::themes.{$this->theme}.dashboard", [
            'balance' => $usdcBalance,
            'transactions' => $transactions,
            'wallet_address' => $balanceData['address'],
            'qrService' => $this->qrService,
            'bot' => $this->bot,
            'user' => $telegramUser
        ]);
    }
}