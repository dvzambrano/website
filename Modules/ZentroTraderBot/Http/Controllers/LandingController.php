<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\TelegramBot\Entities\TelegramBots;

class LandingController extends Controller
{
    public function index()
    {
        // Leemos el bot para el q esta trabajando esta pagina:
        $bot_name = config('zentrotraderbot.bot', 'KashioBot');
        // Leemos el tema configurado (por defecto flexstart)
        $theme = config('zentrotraderbot.theme', 'FlexStart');

        $bot = TelegramBots::where('name', '@' . $bot_name)->first();
        // Si no existe, podrías lanzar un error o cargar uno por defecto
        if (!$bot) {
            abort(404, 'Bot no configurado');
        }

        // Retornamos la vista dinámica
        return view("zentrotraderbot::themes.{$theme}.index", [
            'bot' => $bot
        ]);

    }
}