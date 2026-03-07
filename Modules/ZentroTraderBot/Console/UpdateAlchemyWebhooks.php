<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\Laravel\Services\BehaviorService;
use Modules\TelegramBot\Entities\TelegramBots;

class UpdateAlchemyWebhooks extends Command
{
    protected $signature = 'zentrotraderbot:alchemy-update-webhooks {module=ZentroTraderBot}';
    protected $description = 'Actualiza la URL y configuración de los webhooks existentes en Alchemy';

    public function handle()
    {
        $alchemyToken = config("zentrotraderbot.alchemy_auth_token");

        $bots = TelegramBots::where('module', $this->argument('module'))->get();

        foreach ($bots as $tenant) {
            $webhookId = $tenant->data['alchemy_webhook_id'] ?? null;

            if (!$webhookId) {
                $this->warn("⚠️ {$tenant->code} no tiene un 'alchemy_webhook_id' registrado. Saltando...");
                continue;
            }

            $this->info("🔄 Actualizando webhook {$webhookId} para: {$tenant->code}");

            // Según la API de Alchemy, el PATCH permite actualizar URL o configuración
            // Cambia tu llamada a PUT (como indica el cURL) y usa el payload correcto
            $payload = [
                'webhook_id' => $webhookId,
                'is_active' => true,
                'name' => $tenant->code
            ];

            $response = Http::withHeaders([
                'X-Alchemy-Token' => $alchemyToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(BehaviorService::timeout())
                ->put("https://dashboard.alchemy.com/api/update-webhook", $payload);

            if ($response->successful()) {
                $this->info("✅ Webhook {$webhookId} actualizado correctamente.");
            } else {
                $this->error("❌ Error actualizando webhook {$webhookId}: " . $response->body());
                $this->error("Status: " . $response->status());
                $this->error("Response Body: " . $response->body());
                $this->error("URL: https://dashboard.alchemy.com/api/update-webhook");
                $this->error("Payload enviado: " . json_encode($payload));
            }
        }
    }
}