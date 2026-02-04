<?php
namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;

class BotSimulate extends Command
{
    // Uso: php artisan bot:test {id} {mensaje}
    protected $signature = 'bot:test {id} {text=/start}';
    protected $description = 'Simula un webhook de Telegram localmente';

    public function handle()
    {
        $bot = TelegramBots::where('name', "@" . $this->argument('id'))->first();
        $text = $this->argument('text');

        // Construimos la URL local
        // Asegúrate de que APP_URL en tu .env sea http://localhost o la IP de tu servidor local
        $url = route('telegram-bot-webhhok', array(
            "key" => $bot->key
        ));

        $this->line("Simulando mensaje '{$text}' para el bot: {$bot->name} en {$url}");

        // Simulamos el JSON que enviaría Telegram
        $payload = [
            'demo' => true,
            'update_id' => rand(10000, 99999),
            'message' => [
                'message_id' => rand(1, 100),
                'from' => [
                    'id' => 1741391257,
                    'is_bot' => false,
                    'first_name' => 'Donel',
                    'username' => 'dvzambrano',
                    'language_code' => 'es'
                ],
                'chat' => [
                    'id' => 1741391257,
                    'type' => 'private',
                    'first_name' => 'Donel',
                    'username' => 'dvzambrano'
                ],
                'date' => time(),
                'text' => $text
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