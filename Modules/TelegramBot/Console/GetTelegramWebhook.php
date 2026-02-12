<?php
namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;

class GetTelegramWebhook extends Command
{
    protected $signature = 'bot:get-webhook {id}';
    protected $description = 'Obtiene el webhook de Telegram para un bot específico';

    public function handle()
    {
        // 1. Buscar el bot
        $tenant = TelegramBots::where('name', "@" . $this->argument('id'))->first();

        $this->info("Obteniendo datos del Webhook para: {$tenant->name} | {$tenant->key}");

        $response = Http::get("https://api.telegram.org/bot{$tenant->token}/getWebhookinfo");

        if ($response->successful()) {
            $this->info("✅ ¡Éxito! Datos del Webhook:");
            $this->line("📍 " . $response->body());
        } else {
            $this->error("❌ Error: " . $response->body());
        }
    }
}