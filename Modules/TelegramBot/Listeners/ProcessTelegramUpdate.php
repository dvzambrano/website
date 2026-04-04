<?php

namespace Modules\TelegramBot\Listeners;

use Modules\TelegramBot\Events\TelegramUpdateReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Http\Controllers\TelegramBotController;

class ProcessTelegramUpdate implements ShouldQueue
{
    // Esto hace que se procese en el queue worker
    public $queue = 'telegram-updates';

    public function handle(TelegramUpdateReceived $event)
    {
        $update = $event->update;

        // 2. Identificar el Bot/Tenant
        $tenant = TelegramBots::where('key', $event->tenantKey)->first();
        if (!$tenant) {
            Log::error("🆘  ProcessTelegramUpdate handle escaped by !bot: ", [
                "tenant_code" => $event->tenantKey,
                "update" => $update,
            ]);
            return;
        }
        $tenant->connectToThisTenant();

        $c = new TelegramBotController();
        $controller = $c->getController($tenant->module, $tenant->module);
        $c->callControllerMethod($controller, 'receiveMessage', [$tenant, $update], 'Bot handle controller not found');
    }
}