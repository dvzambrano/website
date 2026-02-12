<?php

namespace Modules\TelegramBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Cache;

class SendAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenantId;
    protected $userId;
    protected $text;
    protected $messageId;

    public function __construct($tenantId, $userId, $text, $messageId)
    {
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->text = $text;
        $this->messageId = $messageId ?? false;
    }

    public function handle()
    {
        // 1. Obtener Tenant (sin lanzar excepción fatal si falla)
        $tenant = TelegramBots::find($this->tenantId);
        if (!$tenant)
            return;

        $tenant->connectToThisTenant();

        // 2. Envío del mensaje y Pin (Tu lógica actual está perfecta)
        $payload = [
            "message" => [
                "text" => $this->text,
                "chat" => ["id" => $this->userId],
                "reply_markup" => json_encode([
                    "inline_keyboard" => [[["text" => "↖️ Menu", "callback_data" => "menu"]]],
                ]),
            ],
        ];

        $response = json_decode(TelegramController::sendMessage($payload, $tenant->token), true);

        if (isset($response["result"]["message_id"])) {
            TelegramController::pinMessage([
                "message" => [
                    "chat" => ["id" => $this->userId],
                    "message_id" => $response["result"]["message_id"],
                ],
            ], $tenant->token);
        }

        // 3. ACTUALIZACIÓN DE PROGRESO (Optimizada para concurrencia)
        if ($this->messageId) {
            $cacheKey = "bot_announcement_{$this->messageId}";

            // INCREMENTO ATÓMICO: Esta es la clave para que el contador no mienta
            $currentSent = Cache::increment($cacheKey . "_sent");
            $data = Cache::get($cacheKey); // Aquí tenemos 'total' y 'admin_id'

            if ($data) {
                $total = $data['total'];

                // Lógica de intervalo inteligente que diseñamos
                if ($total <= 10)
                    $interval = 1;
                elseif ($total <= 100)
                    $interval = 10;
                else
                    $interval = 50;

                if ($currentSent % $interval == 0 || $currentSent == $total) {
                    $status = ($currentSent == $total) ? "✅ *¡Envío de anuncios completado!*" : "⏳ *Enviando anuncios...*";

                    TelegramController::editMessageText([
                        "message" => [
                            "chat" => ["id" => $data['admin_id']],
                            "message_id" => $this->messageId,
                            "text" => "{$status}\n" .
                                "Progreso: {$currentSent} de {$total} anuncios enviados."
                        ]
                    ], $tenant->token);

                    // Limpieza de caché si ya terminó
                    if ($currentSent == $total) {
                        Cache::forget($cacheKey);
                        Cache::forget($cacheKey . "_sent");
                    }
                }
            }
        }
    }
}