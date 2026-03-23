<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Modules\ZentroTraderBot\Jobs\SimulateScrowAction;
class StartScrowSimulation extends Command
{
    protected $signature = 'zentrotraderbot:start-scrow-simulation';
    protected $description = 'Ejecuta el Job SimulateScrowAction que comienza a generar operaciones del Scrow simuladas mientras que ESCROW_TEST_MODE=true en el .env';

    public function handle()
    {
        SimulateScrowAction::dispatch()->delay(now()->addSeconds(5));
        $this->info("🟢 Se ha comenzado a simular! Para parar la reaccion en cadena cambia ESCROW_TEST_MODE a falso en el .env durante al menos 1 hora");
    }
}