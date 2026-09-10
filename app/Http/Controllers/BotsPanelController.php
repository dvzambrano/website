<?php

namespace App\Http\Controllers;

use Dvzambrano\TelegramBot\Entities\TelegramBots;

/**
 * Listado central de bots con link a su dashboard (si el módulo lo trae),
 * sin acoplarse a qué bots existen o a su namespace: usa el contrato
 * Dvzambrano\TelegramBot\Contracts\ProvidesDashboard vía
 * TelegramBots::hasDashboard()/dashboardUrl()/dashboardLabel() (ver
 * Docs/DASHBOARD.md del paquete GutoTradeBot, sección 7).
 */
class BotsPanelController extends Controller
{
    public function index()
    {
        $bots = TelegramBots::orderBy('module')->orderBy('name')->get();

        return view('panel.bots', ['bots' => $bots]);
    }
}
