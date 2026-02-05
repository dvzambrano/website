<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ClearLaravel extends Command
{
    protected $signature = 'laravel:clear';
    protected $description = 'Limpia caché, optimiza el proyecto y refresca el autoload de Composer';

    public function handle()
    {
        $this->info('🚀 Iniciando limpieza profunda del proyecto...');
        $commands = [
            'route:clear' => 'Limpiando rutas...',
            'view:clear' => 'Limpiando vistas...',
            'config:clear' => 'Limpiando configuración...',
            'cache:clear' => 'Limpiando caché de la aplicación...',
            'route:cache' => 'Cacheando rutas...',
            'config:cache' => 'Cacheando configuración...',
            'optimize' => 'Optimizando framework...',
            'queue:restart' => 'Reiniciando worker...',
            'queue:flush' => 'Limpiando la cola del worker...',
        ];

        foreach ($commands as $command => $description) {
            $this->comment($description);
            $this->call($command);
        }

        $this->info('📦 Ejecutando composer dump-autoload...');

        // Ejecutamos el proceso del sistema
        $result = Process::run('composer dump-autoload');

        if ($result->successful()) {
            $this->info('✅ Autoload refrescado correctamente.');
            $this->line("<fg=gray>{$result->output()}</>");
        } else {
            $this->error('❌ Error al ejecutar composer: ' . $result->errorOutput());
        }

        $this->info('✨ ¡Proyecto reseteado !');
    }
}