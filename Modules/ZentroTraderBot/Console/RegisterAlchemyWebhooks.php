<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\BehaviorService;

class RegisterAlchemyWebhooks extends Command
{
    // Cambié el nombre para que sea más descriptivo: solo registra el canal
    protected $signature = 'zentrotraderbot:alchemy-init-webhooks {module=ZentroTraderBot} {--domain=micalme.com}';
    protected $description = 'Crea los webhooks vacíos en Alchemy y guarda sus IDs en los bots';

    public function handle()
    {
        $alchemyToken = config("zentrotraderbot.alchemy_auth_token");
        $domain = $this->option('domain');

        $bots = TelegramBots::where('module', $this->argument('module'))->get();
        foreach ($bots as $tenant) {
            $this->info("🚀 Creando canal para: {$tenant->code}");

            // IMPORTANTE: Alchemy requiere al menos una dirección o un array vacío según la versión.
            // Si te da error con [], pon una dirección de prueba '0x0000000000000000000000000000000000000000'
            // https://micalme.com/webhook/wallet/alchemy/772aecb5-0f26-4a3d-9015-c2bee0e04d71
            $payload = [
                'network' => 'MATIC_MAINNET',
                'webhook_type' => 'ADDRESS_ACTIVITY',
                'webhook_url' => "https://" . rtrim($domain, '/') . "/webhook/wallet/alchemy/{$tenant->key}",
                'name' => $tenant->code,
                'addresses' => []
            ];

            $response = Http::withHeaders([
                'X-Alchemy-Token' => $alchemyToken,
                'Content-Type' => 'application/json',
            ])
                ->timeout(BehaviorService::timeout())
                ->post('https://dashboard.alchemy.com/api/create-webhook', $payload);

            if ($response->successful()) {
                $webhookId = $response->json('data.id');

                // Guardamos solo el ID para futuras actualizaciones
                $data = $tenant->data;
                $data["alchemy_webhook_id"] = $webhookId;
                $tenant->data = $data;
                $tenant->save();

                $this->info("✅ Canal creado. Webhook ID: {$webhookId}");
            } else {
                $this->error("❌ Error creando webhook para {$tenant->code}: " . $response->body());
            }
        }
    }
}