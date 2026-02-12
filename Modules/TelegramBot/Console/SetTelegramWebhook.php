<?php
namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;

class SetTelegramWebhook extends Command
{
    protected $signature = 'bot:set-webhook {id} {--domain= : El dominio de producción (ej: tusitio.com)}';
    protected $description = 'Configura el webhook en Telegram para un bot específico';

    public function handle()
    {
        // 1. Buscar el bot
        $tenant = TelegramBots::where('name', "@" . $this->argument('id'))->first();

        // 2. Obtener el dominio del parámetro o del .env
        $inputDomain = $this->option('domain') ?: config('app.url');

        // 3. LIMPIEZA TOTAL:
        // - Quitamos http:// o https:// si el usuario lo escribió
        // - Quitamos espacios y barras diagonales al final
        $cleanDomain = str_replace(['http://', 'https://'], '', $inputDomain);
        $cleanDomain = rtrim(trim($cleanDomain), '/');

        // 4. FORZAMOS el HTTPS
        $webhookUrl = "https://{$cleanDomain}/telegram/bot/{$tenant->key}";

        $this->info("Configurando Webhook para: {$tenant->name}");

        $response = Http::post("https://api.telegram.org/bot{$tenant->token}/setWebhook", [
            'url' => $webhookUrl,
            'secret_token' => $tenant->secret, // IMPORTANTE: Telegram guardará esto y lo enviará en cada mensaje
            'drop_pending_updates' => true,
        ]);

        if ($response->successful()) {
            $this->info("✅ ¡Éxito! Webhook configurado.");
            $this->line("📍 URL registrada: <info>{$webhookUrl}</info>");
        } else {
            $this->error("❌ Error al configurar: " . $response->body());
        }
    }
}