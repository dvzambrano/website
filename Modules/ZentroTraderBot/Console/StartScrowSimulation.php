<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Modules\ZentroTraderBot\Jobs\SimulateScrowAction;
use Illuminate\Support\Facades\Cache;
use Modules\TelegramBot\Entities\TelegramBots;

class StartScrowSimulation extends Command
{
    protected $signature = 'zentrotraderbot:start-scrow-simulation {bot=KashioBot}';
    protected $description = 'Ejecuta el Job SimulateScrowAction que comienza a generar operaciones del Scrow simuladas mientras que ESCROW_TEST_MODE=true en el .env';

    public function handle()
    {
        $botName = '@' . ltrim($this->argument('bot'), '@');
        $tenant = TelegramBots::where('name', $botName)->first();

        $stopKey = "stop_job_" . SimulateScrowAction::class . "_{$tenant->key}";
        Cache::forget($stopKey);

        SimulateScrowAction::dispatch($tenant->key)->delay(now()->addSeconds(5));
        $this->warn("🏃💨 Se ha comenzado a simular operaciones en el ESCROW!");
        $this->info("✋ Para parar la reaccion en cadena ejecute: php artisan bot:stop-job KashioBot SimulateScrowAction 3600");
        $this->info("⏱️  Debe ser al menos 1 hora para que terminen los Jobs activos");
    }
}