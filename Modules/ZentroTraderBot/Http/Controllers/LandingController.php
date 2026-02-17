<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\TelegramBot\Entities\TelegramBots;

class LandingController extends Controller
{
    private $bot;

    private $theme;

    public function __construct()
    {
        $this->theme = config('zentrotraderbot.theme', 'FlexStart');

        $this->bot = TelegramBots::where(
            'name',
            '@' . config('zentrotraderbot.bot', 'KashioBot')
        )->first();
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
        $balance = random_int(10, 3000);

        $transactions = [
            ['type' => 'in', 'concept' => 'Saldo acreditado', 'amount' => random_int(10, 200), 'date' => 'Hace 1 minuto'],
            ['type' => 'in', 'concept' => 'Recarga FIAT', 'amount' => random_int(50, 200), 'date' => 'Hace 2 horas'],
            ['type' => 'out', 'concept' => 'Swap USDC a MATIC', 'amount' => 45.20, 'date' => 'Ayer'],
        ];
        $transactions = [
            ['type' => 'in', 'concept' => 'Saldo acreditado', 'amount' => random_int(10, 200), 'date' => 'Hace 1 minuto'],
        ];

        return view("zentrotraderbot::themes.{$this->theme}.dashboard", [
            'balance' => $balance,
            'transactions' => $transactions,
            'bot' => $this->bot
        ]);
    }
}