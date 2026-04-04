<?php

namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Events\TelegramUpdateReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class BotSimulate extends Command
{
    // Uso: php artisan bot:test mi_bot "/start" 123456789 --real
    protected $signature = 'bot:test {id} {text=/start} {user_id=816767995} {language_code=es} {--real : Ejecutar como una operación real, no demo}';
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
        $languageCode = $this->argument('language_code');
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
        $this->line("👤 User ID: {$userId} 🗣  {$languageCode}");
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
                    "is_bot" => false,
                    "first_name" => "Donel",
                    "last_name" => "Vazquez Zambrano",
                    "username" => "dvzambrano",
                    "language_code" => $languageCode,
                    "is_premium" => true
                ],
                'chat' => [
                    'id' => $userId,
                    "first_name" => "Donel",
                    "last_name" => "Vazquez Zambrano",
                    "username" => "dvzambrano",
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $text,
                // Inyectamos la propiedad demo para que el controlador la capture
            ]
        ];

        // FORZAMOS EL DRIVER DE COLA A SYNC SOLO PARA ESTA EJECUCIÓN
        config(['queue.default' => 'sync']);

        // 3. DISPARAR EL EVENTO DIRECTAMENTE
        // Esto activará el ProcessTelegramUpdate Listener
        event(new TelegramUpdateReceived($bot->key, $payload));

        $this->info("✅ Evento disparado correctamente.");
    }
}