<?php

namespace Modules\TelegramBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TelegramBot\Http\Controllers\TelegramController;

class DeleteTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $token;
    protected $chatId;
    protected $messageId;

    public function __construct($token, $chatId, $messageId)
    {
        $this->token = $token;
        $this->chatId = $chatId;
        $this->messageId = $messageId;
    }

    public function handle()
    {
        // Llamamos directamente al controlador
        TelegramController::deleteMessage([
            "message" => [
                "chat" => ["id" => $this->chatId],
                "id" => $this->messageId // Tu controlador usa "id" para borrar
            ]
        ], $this->token);
    }
}