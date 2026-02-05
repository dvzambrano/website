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

        foreach ($bots as $bot) {
            $this->line("---------------------------------------------------------");
            $this->info("🤖 Procesando: {$bot->name}");

            // 1. Generar nuevos valores aleatorios manualmente (ya que 'creating' no se dispara en updates)
            $bot->key = (string) Str::uuid();
            $bot->secret = Str::random(32);
            $bot->save();

            // 2. Construir la URL del webhook
            // Usamos la estructura: https://dominio/telegram/bot/{key}
            $webhookUrl = "https://" . rtrim($domain, '/') . "/telegram/bot/{$bot->key}";

            // 3. Notificar a Telegram
            $response = Http::post("https://api.telegram.org/bot{$bot->token}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $bot->secret,
                'drop_pending_updates' => true,
            ]);

            if ($response->successful()) {
                $this->info("✅ Webhook actualizado con éxito.");
                $this->line("   🗝️  Nueva Key: {$bot->key}");
                $this->line("   🔒 Nuevo Secret: {$bot->secret}");
                $this->line("   📍 URL: {$webhookUrl}");
            } else {
                $this->error("❌ Error en Telegram para {$bot->name}: " . $response->body());
            }
        }

        $this->line("---------------------------------------------------------");
        $this->info("🏁 Proceso finalizado.");
    }
}