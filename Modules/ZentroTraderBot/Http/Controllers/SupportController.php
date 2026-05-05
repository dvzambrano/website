<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Services\TextService;

class SupportController extends Controller
{
    // =========================================================
    // OPEN TICKET — Cliente abre un nuevo ticket de soporte
    // =========================================================

    public function openTicket($bot)
    {
        $botTenant = app('active_bot');
        $userId = (int) $bot->actor->user_id;
        $supportGroupId = env('TRADER_BOT_SUPPORT');

        if (!$supportGroupId) {
            return [
                "text" => "❌ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.error_no_group")),
            ];
        }

        // Si ya está en modo chat activo, recordarle y mostrar botón de salida
        $chatKey = "support_chat_{$botTenant->key}_{$userId}";
        if (Cache::has($chatKey)) {
            return [
                "text" => "ℹ️ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.already_open")),
                "reply_markup" => json_encode([
                    "inline_keyboard" => [[
                        ["text" => "🚪 " . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.exit_btn")), "callback_data" => "/exitsupportchat"],
                    ]],
                ]),
            ];
        }

        $username = $bot->actor->data['telegram']['username'] ?? null;
        $userLabel = $username ? "@{$username}" : "ID: {$userId}";

        // Verificar si el usuario ya tiene un topic previo (ticket persistente)
        $userTicketKey = "support_user_ticket_{$botTenant->key}_{$userId}";
        $existingTicket = Cache::get($userTicketKey);

        if ($existingTicket && isset($existingTicket['thread_id'])) {
            // Reutilizar el topic existente — re-entrar en modo chat sin crear uno nuevo
            $threadId = (int) $existingTicket['thread_id'];

            TelegramController::sendMessage([
                'message' => [
                    'chat' => ['id' => $supportGroupId],
                    'message_thread_id' => $threadId,
                    'text' => "🔄 " . Lang::get("zentrotraderbot::bot.support.user_reconnected") . " {$userLabel}",
                    'parse_mode' => 'MarkdownV2',
                ],
            ], $botTenant->token);

            $this->enterSupportChatMode($userId, $threadId, $botTenant);
            return ["text" => ""];
        }

        // No existe topic previo — crear uno nuevo
        $topicName = "TICKET " . date("Ymd") . "-{$userId}";

        $rawTopic = TelegramController::createForumTopic([
            'message' => [
                'chat' => ['id' => $supportGroupId],
                'name' => $topicName,
            ],
        ], $botTenant->token);

        $topicData = json_decode($rawTopic, true);

        if (!isset($topicData['ok']) || !$topicData['ok'] || !isset($topicData['result']['message_thread_id'])) {
            Log::error("🆘 SupportController openTicket: Failed to create forum topic", [
                'response' => $rawTopic,
                'userId' => $userId,
            ]);
            return [
                "text" => "❌ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.error_create_ticket")),
            ];
        }

        $threadId = (int) $topicData['result']['message_thread_id'];

        // Guardar relación thread_id → user_id (para cuando soporte responda)
        $ticketKey = "support_ticket_{$botTenant->key}_{$threadId}";
        Cache::put($ticketKey, ['user_id' => $userId], now()->addDays(30));

        // Guardar relación user_id → thread_id (para reutilizar en próximas sesiones)
        Cache::put($userTicketKey, ['thread_id' => $threadId], now()->addDays(30));

        // Enviar mensaje de bienvenida al topic
        TelegramController::sendMessage([
            'message' => [
                'chat' => ['id' => $supportGroupId],
                'message_thread_id' => $threadId,
                'text' => "🎫 " . Lang::get("zentrotraderbot::bot.support.new_ticket_intro") . " {$userLabel}\n🆔 `{$userId}`",
                'parse_mode' => 'MarkdownV2',
            ],
        ], $botTenant->token);

        // Poner al usuario en modo chat de soporte
        $this->enterSupportChatMode($userId, $threadId, $botTenant);

        return ["text" => ""];
    }

    // =========================================================
    // RELAY TO SUPPORT — Reenvía mensaje del cliente al topic
    // =========================================================

    public function relayToSupport($bot)
    {
        $botTenant = app('active_bot');
        $userId = (int) $bot->actor->user_id;
        $supportGroupId = (int) env('TRADER_BOT_SUPPORT');
        $chatKey = "support_chat_{$botTenant->key}_{$userId}";
        $chatData = Cache::get($chatKey);

        if (!$chatData) {
            return ["text" => ""];
        }

        $text = $bot->message['text'] ?? '';

        if (str_starts_with(ltrim($text), '/exitsupportchat')) {
            return $this->exitSupportChat($bot);
        }

        $threadId = (int) $chatData['thread_id'];
        $prefix = "";

        $this->forwardSupportMessage($bot->message, $prefix, $supportGroupId, null, $botTenant->token, $threadId);

        return [
            "text" => "✔️ _" . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.message_sent")) . "_",
            "chat" => ["id" => $userId],
        ];
    }

    // =========================================================
    // RELAY TO USER — Reenvía respuesta de soporte al cliente
    // =========================================================

    public function relayToUser($bot)
    {
        $botTenant = app('active_bot');
        $threadId = (int) ($bot->message['message_thread_id'] ?? 0);

        if (!$threadId) {
            return ["text" => ""];
        }

        $ticketKey = "support_ticket_{$botTenant->key}_{$threadId}";
        $ticketData = Cache::get($ticketKey);

        if (!$ticketData || !isset($ticketData['user_id'])) {
            return ["text" => ""];
        }

        $userId = (int) $ticketData['user_id'];

        // No reenviar mensajes del propio bot
        $botInfo = $botTenant->data['info'] ?? [];
        $fromId = (int) ($bot->message['from']['id'] ?? 0);
        if ($botInfo && isset($botInfo['id']) && $fromId === (int) $botInfo['id']) {
            return ["text" => ""];
        }

        $fromUsername = $bot->message['from']['username'] ?? null;
        $fromName = trim(($bot->message['from']['first_name'] ?? '') . ' ' . ($bot->message['from']['last_name'] ?? ''));
        $agentLabel = $fromUsername ? "@{$fromUsername}" : ($fromName ?: 'Soporte');
        $prefix = "_" . TextService::mdv2($agentLabel) . ":_\n";

        $this->forwardSupportMessage($bot->message, $prefix, $userId, null, $botTenant->token);

        return ["text" => ""];
    }

    // =========================================================
    // EXIT SUPPORT CHAT — El cliente sale del modo chat
    // =========================================================

    public function exitSupportChat($bot)
    {
        $botTenant = app('active_bot');
        $userId = (int) $bot->actor->user_id;
        $chatKey = "support_chat_{$botTenant->key}_{$userId}";
        $chatData = Cache::get($chatKey);

        if ($chatData && !empty($chatData['pinned_message_id'])) {
            $msgId = $chatData['pinned_message_id'];
            try {
                TelegramController::unpinChatMessage([
                    "message" => ["chat" => ["id" => $userId], "message_id" => $msgId],
                ], $botTenant->token);
                TelegramController::deleteMessage([
                    "message" => ["chat" => ["id" => $userId], "id" => $msgId],
                ], $botTenant->token);
            } catch (\Throwable $th) {
            }
        }

        Cache::forget($chatKey);

        return [
            "text" => "🚪 " . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.exited")),
            "chat" => ["id" => $userId],
        ];
    }

    // =========================================================
    // ENTER SUPPORT CHAT MODE — Activa modo chat para el usuario
    // =========================================================

    private function enterSupportChatMode(int $userId, int $threadId, object $botTenant): void
    {
        $exitBtn = [[["text" => "🚪 " . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.exit_btn")), "callback_data" => "/exitsupportchat"]]];

        $reminderText =
            "🎫 *" . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.ticket_opened")) . "*\n" .
            "📡 _" . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.chat_line2")) . "_\n" .
            "👇 " . TextService::mdv2(Lang::get("zentrotraderbot::bot.support.chat_line3"));

        $raw = TelegramController::sendMessage([
            "message" => [
                "text" => $reminderText,
                "chat" => ["id" => $userId],
                "parse_mode" => "MarkdownV2",
                "reply_markup" => json_encode(["inline_keyboard" => $exitBtn]),
            ],
        ], $botTenant->token);

        $pinnedMsgId = json_decode($raw, true)['result']['message_id'] ?? null;
        if ($pinnedMsgId) {
            TelegramController::pinMessage([
                "message" => ["chat" => ["id" => $userId], "message_id" => $pinnedMsgId],
            ], $botTenant->token);
        }

        $chatKey = "support_chat_{$botTenant->key}_{$userId}";
        Cache::put($chatKey, [
            'thread_id' => $threadId,
            'pinned_message_id' => $pinnedMsgId,
        ], now()->addDays(30));
    }

    // =========================================================
    // FORWARD MESSAGE — Envía cualquier tipo de media al destino
    // =========================================================

    private function forwardSupportMessage(array $msg, string $prefix, int $chatId, ?string $markup, string $token, ?int $threadId = null): void
    {
        $base = ['chat' => ['id' => $chatId], 'parse_mode' => 'MarkdownV2'];
        if ($threadId)
            $base['message_thread_id'] = $threadId;
        if ($markup)
            $base['reply_markup'] = $markup;

        $quote = function (string $text): string {
            if ($text === '')
                return '';
            return '>' . str_replace("\n", "\n>", $text);
        };

        if (!empty($msg['photo'])) {
            $photo = end($msg['photo']);
            TelegramController::sendPhoto([
                'message' => array_merge($base, [
                    'photo' => $photo['file_id'],
                    'text' => $prefix . $quote(TextService::mdv2($msg['caption'] ?? '')),
                ])
            ], $token);
        } elseif (!empty($msg['document'])) {
            TelegramController::sendDocument([
                'message' => array_merge($base, [
                    'document' => $msg['document']['file_id'],
                    'text' => $prefix . $quote(TextService::mdv2($msg['caption'] ?? '')),
                ])
            ], $token);
        } elseif (!empty($msg['video'])) {
            TelegramController::sendVideo([
                'message' => array_merge($base, [
                    'video' => $msg['video']['file_id'],
                    'text' => $prefix . $quote(TextService::mdv2($msg['caption'] ?? '')),
                ])
            ], $token);
        } elseif (!empty($msg['audio'])) {
            TelegramController::sendAudio([
                'message' => array_merge($base, [
                    'audio' => $msg['audio']['file_id'],
                    'text' => $prefix . $quote(TextService::mdv2($msg['caption'] ?? '')),
                ])
            ], $token);
        } elseif (!empty($msg['voice'])) {
            TelegramController::sendVoice([
                'message' => array_merge($base, [
                    'voice' => $msg['voice']['file_id'],
                    'text' => $prefix . $quote(TextService::mdv2($msg['caption'] ?? '')),
                ])
            ], $token);
        } elseif (!empty($msg['video_note'])) {
            TelegramController::sendVideoNote([
                'message' => array_merge($base, [
                    'video_note' => $msg['video_note']['file_id'],
                ])
            ], $token);
        } elseif (!empty($msg['sticker'])) {
            TelegramController::sendSticker([
                'message' => array_merge($base, [
                    'sticker' => $msg['sticker']['file_id'],
                ])
            ], $token);
        } elseif (!empty($msg['animation'])) {
            TelegramController::sendAnimation([
                'message' => array_merge($base, [
                    'animation' => $msg['animation']['file_id'],
                    'text' => $prefix . $quote(TextService::mdv2($msg['caption'] ?? '')),
                ])
            ], $token);
        } elseif (!empty($msg['text'])) {
            TelegramController::sendMessage([
                'message' => array_merge($base, [
                    'text' => $prefix . $quote(TextService::mdv2($msg['text'])),
                ])
            ], $token);
        } else {
            TelegramController::sendMessage([
                'message' => array_merge($base, [
                    'text' => $prefix . $quote(TextService::mdv2(Lang::get('zentrotraderbot::bot.chat.unsupported_media'))),
                ])
            ], $token);
        }
    }
}
