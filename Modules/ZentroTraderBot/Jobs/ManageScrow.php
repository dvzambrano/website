<?php

namespace Modules\ZentroTraderBot\Jobs;



use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Entities\TelegramBots;

class ManageScrow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $text;

    public function __construct($userId, $text)
    {
        $this->userId = $userId;
        $this->text = $text;
    }

    public function handle()
    {
        $bot = TelegramBots::where('name', "@ZentroOwnerBot")->first();

        $url = "https://dev.micalme.com/telegram/bot/" . $bot->key;
        $text = $this->text;
        $payload = [
            'message' => [
                'message_id' => rand(1, 100),
                'from' => [
                    'id' => $this->userId,
                    'username' => 'sim_user',
                ],
                'chat' => [
                    'id' => $this->userId,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $text,
            ]
        ];


        try {
            Http::withHeaders([
                'X-Telegram-Bot-Api-Secret-Token' => $bot->secret,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);
        } catch (\Throwable $th) {
            Log::error("🆘 ManageScrow job handle: ", [
                'id' => $this->userId,
                'text' => $this->text,
            ]);
        }
    }
}