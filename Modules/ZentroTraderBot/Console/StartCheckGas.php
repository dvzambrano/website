<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Modules\ZentroTraderBot\Jobs\CheckGas;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Cache;

class StartCheckGas extends Command
{
    protected $signature = 'zentrotraderbot:start-check-gas {bot=KashioBot} {user=816767995}';
    protected $description = 'Ejecuta el Job CheckGas que comienza a monitorear el gas de la red';

    public function handle()
    {
        $tenant = TelegramBots::where('name', '@' . $this->argument('bot'))->first();

        $stopKey = "stop_job_" . CheckGas::class . "_{$tenant->key}";
        Cache::forget($stopKey);

        CheckGas::dispatch($tenant->key, $this->argument('user'))->delay(now()->addSeconds(5));
        $this->warn("⛽ Ejecutando monitoreo de Gas para {$tenant->code} notificando a " . $this->argument('user'));
    }
}