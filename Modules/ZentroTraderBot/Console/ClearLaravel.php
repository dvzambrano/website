<?php

namespace Modules\ZentroTraderBot\Console;

use Modules\Laravel\Console\ClearLaravel as BaseClear;

class ClearLaravel extends BaseClear
{
    protected $signature = 'laravel:clear {--scrowtest : Inicia la simulación de Escrow} {--clean : Limpia cache de alertas}';
    protected $description = 'Ejecuta el Job CheckGas que comienza a monitorear el gas de la red';

    public function handle()
    {
        // 1. Ejecuta toda la limpieza profunda
        parent::handle();

        $this->newLine();
        $this->alert('🔐 RE-ACTIVACIÓN DE SEGURIDAD');
        $this->call('laravel:unlock');

        try {
            $this->newLine();
            if ($this->option('clean'))
                $this->call('zentrotraderbot:reset-gas-alerts');
            $this->call('zentrotraderbot:start-check-gas');
            $this->info('☑️  DONE!');
        } catch (\Exception $e) {
            $this->warn("⚠️ No se pudo iniciar el monitoreo de gas: " . $e->getMessage());
        }

        if ($this->option('scrowtest')) {
            try {
                $this->newLine();
                $this->call('zentrotraderbot:start-scrow-simulation');
                $this->info('☑️  DONE!');
            } catch (\Exception $e) {
                $this->warn("⚠️ No se pudo iniciar la simulacion de operaciones ESCROW: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('🏁 Proceso de mantenimiento de Finalizado.');
        $this->newLine();
        $this->newLine();
    }
}