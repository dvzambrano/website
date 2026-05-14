<?php

namespace Modules\TelegramBot\Listeners;

use Modules\TelegramBot\Events\TelegramUpdateReceived;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Http\Controllers\TelegramBotController;
use Modules\Laravel\Services\BehaviorService;

class ProcessTelegramUpdate
{
    public function handle(TelegramUpdateReceived $event)
    {
        $update = $event->update;

        // 2. Identificar el Bot/Tenant
        $tenant = BehaviorService::cache('tenant_' . $event->tenantKey, function () use ($event) {
            return TelegramBots::where('key', $event->tenantKey)->first();
        });

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