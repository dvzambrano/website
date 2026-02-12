<?php

namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Str;

class ResetTelegramWebhooks extends Command
{
    // El comando no pide ID porque actuará sobre TODOS
    protected $signature = 'bot:reset-all-webhooks {--domain=dev.micalme.com : El dominio para el webhook}';
    protected $description = 'Genera nuevas llaves/secretos para todos los bots y actualiza sus webhooks';

    public function handle()
    {
        $bots = TelegramBots::all();

        if ($bots->isEmpty()) {
            $this->error("No se encontraron bots en la base de datos.");
            return;
        }

        $domain = $this->option('domain');
        $this->info("🔄 Iniciando reseteo masivo para el dominio: https://{$domain}");
        $this->warn("Se generarán nuevas Keys y Secretos para " . $bots->count() . " bots.");

        foreach ($bots as $tenant) {
            $this->line("---------------------------------------------------------");
            $this->info("🤖 Procesando: {$tenant->name}");

            // 1. Generar nuevos valores aleatorios manualmente (ya que 'creating' no se dispara en updates)
            $tenant->key = (string) Str::uuid();
            $tenant->secret = Str::random(32);
            $tenant->save();

            // 2. Construir la URL del webhook
            // Usamos la estructura: https://dominio/telegram/bot/{key}
            $webhookUrl = "https://" . rtrim($domain, '/') . "/telegram/bot/{$tenant->key}";

            // 3. Notificar a Telegram
            $response = Http::post("https://api.telegram.org/bot{$tenant->token}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $tenant->secret,
                'drop_pending_updates' => true,
            ]);

            if ($response->successful()) {
                $this->info("✅ Webhook actualizado con éxito.");
                $this->line("   🗝️  Nueva Key: {$tenant->key}");
                $this->line("   🔒 Nuevo Secret: {$tenant->secret}");
                $this->line("   📍 URL: {$webhookUrl}");
            } else {
                $this->error("❌ Error en Telegram para {$tenant->name}: " . $response->body());
            }
        }

        $this->line("---------------------------------------------------------");
        $this->info("🏁 Proceso finalizado.");
    }
}