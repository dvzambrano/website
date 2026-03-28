<?php

namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\TelegramBot\Entities\TelegramBots;

class StopJob extends Command
{
    protected $signature = 'bot:stop-job {bot=KashioBot} {job=CheckGas} {time=3600}';
    protected $description = 'Detiene la cadena recursiva de jobs';

    public function handle()
    {
        $botName = '@' . ltrim($this->argument('bot'), '@');
        $tenant = TelegramBots::where('name', $botName)->first();

        if (!$tenant) {
            $this->error("❌ Bot {$botName} no encontrado.");
            return;
        }

        $jobName = strtolower($this->argument('job'));
        $killKey = "stop_job_{$jobName}_{$tenant->key}";

        Cache::put($killKey, true, (int) $this->argument('time'));

        $this->info("🛑 Señal de parada para " . strtoupper($jobName) . " enviada al bot {$tenant->name}.");
    }
}