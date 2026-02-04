<?php

namespace Modules\TelegramBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $url = "https://api.telegram.org/bot{$this->token}/deleteMessage";

        $response = Http::post($url, [
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId
        ]);

        if ($response->successful()) {
            Log::info("✅ Mensaje {$this->messageId} eliminado correctamente.");
        } else {
            Log::error("❌ Error al eliminar mensaje: " . $response->body());
        }
    }
}