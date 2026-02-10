<?php
namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;

class BotSimulate extends Command
{
    // Uso: php artisan bot:test mi_bot "/start" 123456789 --real
    protected $signature = 'bot:test {id} {text=/start} {user_id=816767995} {--real : Ejecutar como una operación real, no demo}';
    protected $description = 'Simula un webhook de Telegram localmente con ID de usuario dinámico';

    public function handle()
    {
        $bot = TelegramBots::where('name', "@" . $this->argument('id'))->first();
        if (!$bot) {
            $this->error("Bot @{$this->argument('id')} no encontrado.");
            return;
        }

        $text = $this->argument('text');
        $userId = (int) $this->argument('user_id'); // Obtenemos el ID del usuario
        // Si el usuario NO pone --real, es demo (true).
        // Si el usuario pone --real, demo es false.
        $isDemo = !$this->option('real');

        // Construimos la URL local
        // Asegúrate de que APP_URL en tu .env sea http://localhost o la IP de tu servidor local
        $url = route('telegram-bot-webhhok', array(
            "key" => $bot->key
        ));

        $statusMessage = $isDemo ? "<bg=yellow;fg=black> MODO DEMO ACTIVE </>" : "<bg=red;fg=white> MODO REAL (LIVE) </>";
        $this->line("🤖 Bot: {$bot->name} | 📝 MSG: '{$text}'");
        $this->line("👤 User ID: {$userId}");
        $this->line("📍 URL: {$url}");
        $this->line($statusMessage);
        $this->newLine();

        // Simulamos el JSON que enviaría Telegram
        $payload = [
            'demo' => $isDemo,
            'update_id' => rand(10000, 99999),
            'message' => [
                'message_id' => rand(1, 100),
                'from' => [
                    'id' => $userId,
                    'username' => 'sim_user',
                ],
                'chat' => [
                    'id' => $userId,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $text,
                // Inyectamos la propiedad demo para que el controlador la capture
            ]
        ];

        // Ejecutamos la petición POST incluyendo el SECRET TOKEN en el header
        $response = Http::withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => $bot->secret,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        // Mostramos el resultado del debug
        if ($response->successful()) {
            $this->info("✅ Respuesta del servidor (200):");
            $this->line($response->body());
        } else {
            $this->error("❌ Error ({$response->status()}):");
            $this->line($response->body());
        }
    }
}