<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\TelegramBot\Entities\TelegramBots;
use App\Models\Metadatas;
use Illuminate\Support\Facades\DB;

class SyncAlchemyAddresses extends Command
{
    protected $signature = 'zentrotraderbot:alchemy-sync-wallets {module=ZentroTraderBot}';
    protected $description = 'Vuelca todas las wallets de los suscriptores hacia sus respectivos webhooks en Alchemy';

    public function handle()
    {
        $metadata = Metadatas::where('name', "app_zentrotraderbot_alchemy_authtoken")->first();
        if (!$metadata) {
            $this->error("❌ No se encontró el AuthToken de Alchemy.");
            return;
        }
        $alchemyToken = $metadata->value;

        $bots = TelegramBots::where('module', $this->argument('module'))->get();
        foreach ($bots as $bot) {
            $webhookId = $bot->data['alchemy_webhook_id'] ?? null;

            if (!$webhookId) {
                $this->warn("⚠️ El bot {$bot->code} no tiene un Webhook ID.");
                continue;
            }

            // --- CONFIGURACIÓN DINÁMICA DE LA CONEXIÓN ---
            $bot->connectToThisTenant();

            $this->info("🔍 Recopilando wallets para el bot: {$bot->code} en la BD: {$bot->database_name}");

            // Ahora usamos la conexión 'tenant' que acabamos de configurar
            try {
                $addresses = Suscriptions::on('tenant')
                    ->get()
                    ->pluck('data.wallet.address')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                if (empty($addresses)) {
                    $this->warn("ℹ️ No hay wallets registradas para {$bot->code}.");
                    continue;
                }

                $this->info("📡 Enviando " . count($addresses) . " direcciones a Alchemy (ID: {$webhookId})...");

                // 2. Actualizar el webhook en Alchemy
                // Usamos PATCH para reemplazar las direcciones del webhook con la lista actual
                $response = Http::withHeaders([
                    'X-Alchemy-Token' => $alchemyToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])->patch("https://dashboard.alchemy.com/api/update-webhook-addresses", [
                            'webhook_id' => $webhookId,
                            'addresses_to_add' => $addresses,
                            'addresses_to_remove' => [], // Podrías usar esto para ser más selectivo, pero enviar todo es más seguro para sincronizar
                        ]);

                if ($response->successful()) {
                    $this->info("✅ Wallets sincronizadas para {$bot->code}.");
                } else {
                    $this->error("❌ Error en Alchemy para {$bot->code}: " . $response->body());
                }

            } catch (\Exception $e) {
                $this->error("❌ Error conectando a la base de datos del bot {$bot->code}: " . $e->getMessage());
                continue;
            }
        }

        $this->info("🏁 Proceso de sincronización terminado.");
    }
}