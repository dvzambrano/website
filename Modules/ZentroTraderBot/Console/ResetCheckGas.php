<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Modules\ZentroTraderBot\Jobs\CheckGas;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\ZentroTraderBot\Http\Controllers\BlockchainController;

class ResetCheckGas extends Command
{
    // Firma: bot por defecto y tu ID de usuario por defecto
    protected $signature = 'zentrotraderbot:reset-gas-alerts {bot=KashioBot} {user=816767995}';
    protected $description = 'Limpia la caché de alertas de gas y reinicia el monitoreo inmediato';

    public function handle()
    {
        $botName = $this->argument('bot');
        $userId = $this->argument('user');

        // 1. Buscamos el bot/tenant
        $tenant = TelegramBots::where('name', '@' . $botName)->first();

        if (!$tenant) {
            $this->error("❌ No se encontró el bot: @{$botName}");
            return;
        }

        // 2. Limpiamos la caché de alertas para todas las redes posibles 
        // (Usamos el controller para saber qué red estamos monitoreando)
        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();

        if ($status) {
            $chain = strtolower($status['network']['chain']);
            $baseKey = "gas_alert_{$tenant->key}_{$chain}";
            $statusKey = "gas_status_{$tenant->key}_{$chain}";

            Cache::forget("{$baseKey}_critical");
            Cache::forget("{$baseKey}_warning");
            Cache::forget($statusKey);

            $this->info("🧹 Caché de alertas para '{$chain}' limpiada con éxito.");
        }

        // 3. Despachamos el Job inmediatamente (sin el delay de 5 min)
        CheckGas::dispatch($tenant->key, $userId);

        $this->info("🚀 Job CheckGas despachado para {$tenant->key}. Recibirás un mensaje en Telegram en breve.");
    }
}