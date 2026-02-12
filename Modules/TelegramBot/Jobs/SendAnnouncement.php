<?php

namespace Modules\TelegramBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TelegramBot\Http\Controllers\TelegramController;

class SendAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;
    protected $userId;
    protected $text;

    public function __construct($tenant, $userId, $text)
    {
        $this->tenant = $tenant;
        $this->userId = $userId;
        $this->text = $text;
    }

    public function handle()
    {
        $payload = [
            "message" => [
                "text" => $this->text,
                "chat" => ["id" => $this->userId],
                "reply_markup" => json_encode([
                    "inline_keyboard" => [
                        [
                            ["text" => "↖️ Menu", "callback_data" => "menu"]
                        ]
                    ],
                ]),
            ],
        ];

        $response = json_decode(TelegramController::sendMessage($payload, $this->tenant->token), true);

        // Si se envió bien, lo fijamos (pin)
        if (isset($response["result"]["message_id"])) {
            TelegramController::pinMessage([
                "message" => [
                    "chat" => ["id" => $this->userId],
                    "message_id" => $response["result"]["message_id"],
                ],
            ], $this->tenant->token);
        }
    }
}