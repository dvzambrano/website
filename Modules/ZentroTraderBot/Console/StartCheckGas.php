<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Modules\ZentroTraderBot\Jobs\SimulateScrowAction;
use Modules\ZentroTraderBot\Jobs\CheckGas;
class StartCheckGas extends Command
{
    protected $signature = 'zentrotraderbot:start-check-gas';
    protected $description = 'Ejecuta el Job CheckGas que comienza a monitorear el gas de la red para modificar el min_fee en el contrato';

    public function handle()
    {
        $gas = new CheckGas("59d5e7a3-dea0-4289-88f0-a39765f50bcf", "816767995");
        $gas->handle();

        //SimulateScrowAction::dispatch()->delay(now()->addSeconds(5));
        $this->info("🟢 Se ha comenzado a monitorear!");
    }
}