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

        // 1. Primero el autoload para que Laravel reconozca posibles clases nuevas tras el merge
        $this->composerDumpAutoload();

        // 2. Limpieza total
        $this->comment('🧹 Ejecutando limpieza maestra...');
        $this->call('optimize:clear'); // Limpia config, routes, views y cache de un solo golpe

        $commands = [
            'event:clear' => 'Limpiando eventos...',
            'queue:flush' => 'Vaciando colas...',
            'queue:restart' => 'Reiniciando workers...',
        ];

        foreach ($commands as $command => $description) {
            $this->comment($description);
            try {
                $this->call($command);
            } catch (\Exception $e) {
                $this->warn("⚠️ No se pudo ejecutar $command (quizás no está configurado).");
            }
        }

        // 3. Un último dump por si acaso el clear activó algo
        $this->composerDumpAutoload();

        $this->info('✨ ¡Proyecto reseteado y listo para la acción!');
    }

    public function composerDumpAutoload()
    {
        $this->info('📦 Ejecutando composer dump-autoload...');

        // Ejecutamos el proceso del sistema
        $result = Process::run('composer dump-autoload');

        if ($result->successful()) {
            $this->info('✅ Autoload refrescado correctamente.');
            $this->line("<fg=gray>{$result->output()}</>");
        } else {
            $this->error('❌ Error al ejecutar composer: ' . $result->errorOutput());
        }
    }
}