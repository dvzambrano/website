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
        $this->warn("🏃💨 Se ha comenzado a simular operaciones en el ESCROW!");
        $this->info("✋ Para parar la reaccion en cadena cambia ESCROW_TEST_MODE a false en el .env");
        $this->info("⏱️  Debe ser al menos 1 hora para que terminen los Jobs activos");
    }
}