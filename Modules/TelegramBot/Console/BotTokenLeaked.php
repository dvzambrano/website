<?php
namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\TelegramBot\Entities\TelegramBots;

class BotTokenLeaked extends Command
{
    // Uso: php artisan bot:test mi_bot "/start" 123456789 --real
    protected $signature = 'bot:leaked {id} {token}';
    protected $description = 'Cambia el token de un bot que ha sido comprometido';

    public function handle()
    {
        $bot = TelegramBots::where('name', "@" . $this->argument('id'))->first();
        if (!$bot) {
            $this->error("Bot @{$this->argument('id')} no encontrado.");
            return;
        }

        $token = $this->argument('token');
        $bot->token = $token;
        $bot->save();
        $this->info("✅ Recomendamos migrar a produccion la nueva info del bot");
    }
}