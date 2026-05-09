<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Jobs\DeleteTelegramMessage;
use Tests\TestCase;

/**
 * Pruebas de TelegramController.
 *
 * Todas las llamadas HTTP están interceptadas con Http::fake(),
 * por lo que nunca se contacta la API real de Telegram.
 *
 * Ejecutar:
 *   php artisan test --filter TelegramControllerTest
 */
class TelegramControllerTest extends TestCase
{
    // ── Constantes de fixtures ─────────────────────────────────────────────────


    private const TOKEN = 'test_bot_token_123456';
    private const CHAT_ID = 987654321;
    private const MSG_ID = 42;
    private const USER_ID = 111222333;

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Respuesta estándar "ok" de Telegram. */
    private function ok(array $extra = []): array
    {
        return [
            'ok' => true,
            'result' => array_merge([
                'message_id' => self::MSG_ID,
                'chat' => ['id' => self::CHAT_ID],
            ], $extra),
        ];
    }

    /** Fakeea todas las llamadas a Telegram con una respuesta ok. */
    private function fakeOk(array $extra = []): void
    {
        Http::fake(['api.telegram.org/*' => Http::response($this->ok($extra), 200)]);
    }

    /** Construye un $request con la estructura que espera TelegramController. */
    private function req(array $message = []): array
    {
        return [
            'message' => array_merge([
                'chat' => ['id' => self::CHAT_ID],
                'text' => 'Test message',
                'message_id' => self::MSG_ID,
            ], $message)
        ];
    }

    /** Aserta que se envió una petición al método Telegram indicado. */
    private function assertSentTo(string $method): void
    {
        Http::assertSent(fn($r) => str_contains($r->url(), $method));
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  UTILIDADES PURAS (sin HTTP)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_buildTelegramUrl_sin_params(): void
    {
        // buildTelegramUrl es privado; lo accedemos vía sendMessage chequeando la URL capturada.
        // Probamos el patrón de URL correcto.
        $this->fakeOk();
        TelegramController::sendMessage($this->req(), self::TOKEN);

        Http::assertSent(function ($r) {
            return str_contains($r->url(), 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage');
        });
    }

    public function test_analizeUrl_extrae_path_y_query(): void
    {
        $result = TelegramController::analizeUrl('https://api.telegram.org/botTOKEN/sendMessage?chat_id=123&text=hello');

        $this->assertEquals('api.telegram.org', $result['host']);
        $this->assertContains('sendMessage', $result['path_parts']);
        $this->assertEquals('123', $result['query_parts']['chat_id']);
    }

    public function test_cleanText4Url_elimina_caracteres_problematicos(): void
    {
        $clean = TelegramController::cleanText4Url('hello_world+100%done&go#here=yes?/\\');
        $this->assertStringNotContainsString('_', $clean);
        $this->assertStringNotContainsString('+', $clean);
        $this->assertStringNotContainsString('%', $clean);
        $this->assertStringNotContainsString('&', $clean);
    }

    public function test_escapeText4Url_escapa_caracteres_especiales(): void
    {
        $escaped = TelegramController::escapeText4Url('hello_world');
        $this->assertStringContainsString('\_', $escaped);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  send() — núcleo del controlador
    // ══════════════════════════════════════════════════════════════════════════

    public function test_send_modo_demo_retorna_message_id_menos_uno(): void
    {
        $request = array_merge($this->req(), ['demo' => true]);
        $url = 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage?chat_id=' . self::CHAT_ID . '&text=Test';

        // En modo demo no se hace HTTP; ob_start evita el var_dump en consola
        ob_start();
        $response = TelegramController::send($request, $url);
        ob_end_clean();

        $data = json_decode($response, true);
        $this->assertEquals(-1, $data['result']['message_id']);
    }

    public function test_send_falla_http_retorna_json_fallback(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('', 500)]);

        $url = 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage?chat_id=1&text=x';
        $response = TelegramController::send($this->req(), $url);
        $data = json_decode($response, true);

        $this->assertArrayHasKey('ok', $data);
        $this->assertFalse($data['ok']);
    }

    public function test_send_body_vacio_retorna_json_error(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('', 200)]);

        $url = 'https://api.telegram.org/bot' . self::TOKEN . '/sendMessage?chat_id=1&text=x';
        $response = TelegramController::send($this->req(), $url);
        $data = json_decode($response, true);

        $this->assertFalse($data['ok']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  MENSAJES DE TEXTO
    // ══════════════════════════════════════════════════════════════════════════

    public function test_sendMessage_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        $response = TelegramController::sendMessage($this->req(), self::TOKEN);

        $this->assertSentTo('sendMessage');
        $data = json_decode($response, true);
        $this->assertEquals(self::MSG_ID, $data['result']['message_id']);
    }

    public function test_sendMessage_con_autodestroy_despacha_job(): void
    {
        Queue::fake();
        $this->fakeOk();

        TelegramController::sendMessage($this->req(), self::TOKEN, autodestroy: 5);

        Queue::assertPushed(DeleteTelegramMessage::class);
    }

    public function test_editMessageText_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::editMessageText($this->req(), self::TOKEN);
        $this->assertSentTo('editMessageText');
    }

    public function test_copyMessage_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::copyMessage($this->req(['from_chat_id' => 111]), self::TOKEN);
        $this->assertSentTo('copyMessage');
    }

    public function test_forwardMessage_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::forwardMessage($this->req(['from' => ['id' => 999]]), self::TOKEN);
        $this->assertSentTo('forwardMessage');
    }

    public function test_deleteMessage_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::deleteMessage($this->req(['id' => self::MSG_ID]), self::TOKEN);
        $this->assertSentTo('deleteMessage');
    }

    public function test_pinMessage_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::pinMessage($this->req(), self::TOKEN);
        $this->assertSentTo('pinChatMessage');
    }

    public function test_unpinChatMessage_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::unpinChatMessage($this->req(), self::TOKEN);
        $this->assertSentTo('unpinChatMessage');
    }

    public function test_unpinAllChatMessages_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::unpinAllChatMessages($this->req(), self::TOKEN);
        $this->assertSentTo('unpinAllChatMessages');
    }

    public function test_deleteMessages_con_array_de_ids(): void
    {
        $this->fakeOk(['message_ids' => [1, 2, 3]]);
        TelegramController::deleteMessages($this->req(['message_ids' => [1, 2, 3]]), self::TOKEN);
        $this->assertSentTo('deleteMessages');
    }

    public function test_forwardMessages_con_array_de_ids(): void
    {
        $this->fakeOk();
        TelegramController::forwardMessages($this->req(['message_ids' => [1, 2], 'from_chat_id' => 555]), self::TOKEN);
        $this->assertSentTo('forwardMessages');
    }

    public function test_copyMessages_con_array_de_ids(): void
    {
        $this->fakeOk();
        TelegramController::copyMessages($this->req(['message_ids' => [1, 2], 'from_chat_id' => 555]), self::TOKEN);
        $this->assertSentTo('copyMessages');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  MEDIOS — sendPhoto / sendDocument / sendMediaGroup
    // ══════════════════════════════════════════════════════════════════════════

    public function test_sendPhoto_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendPhoto($this->req(['photo' => 'https://example.com/img.jpg']), self::TOKEN);
        $this->assertSentTo('sendPhoto');
    }

    public function test_sendPhoto_fallback_a_sendMessage_si_falla(): void
    {
        Http::fake([
            'api.telegram.org/*/sendPhoto*' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: failed to get HTTP URL content',
            ], 400),
            'example.com/*' => Http::response('imagebytes', 200),
            'api.telegram.org/*' => Http::response($this->ok(), 200),
        ]);

        $response = TelegramController::sendPhoto(
            $this->req(['photo' => 'https://example.com/img.jpg']),
            self::TOKEN
        );

        $this->assertNotNull($response);
    }

    public function test_sendDocument_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendDocument($this->req(['document' => 'https://example.com/doc.pdf']), self::TOKEN);
        $this->assertSentTo('sendDocument');
    }

    public function test_sendMediaGroup_llama_endpoint_correcto(): void
    {
        $this->fakeOk([]);
        $media = [
            ['type' => 'photo', 'media' => 'https://example.com/1.jpg'],
            ['type' => 'photo', 'media' => 'https://example.com/2.jpg'],
        ];
        TelegramController::sendMediaGroup($this->req(['media' => $media]), self::TOKEN);
        $this->assertSentTo('sendMediaGroup');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  MEDIOS — nuevos métodos via sendMediaWithFallback
    // ══════════════════════════════════════════════════════════════════════════

    public function test_sendAudio_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendAudio($this->req(['audio' => 'https://example.com/song.mp3', 'duration' => 120]), self::TOKEN);
        $this->assertSentTo('sendAudio');
    }

    public function test_sendVideo_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendVideo($this->req(['video' => 'https://example.com/vid.mp4', 'width' => 1280, 'height' => 720]), self::TOKEN);
        $this->assertSentTo('sendVideo');
    }

    public function test_sendAnimation_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendAnimation($this->req(['animation' => 'https://example.com/anim.gif']), self::TOKEN);
        $this->assertSentTo('sendAnimation');
    }

    public function test_sendVoice_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendVoice($this->req(['voice' => 'https://example.com/voice.ogg']), self::TOKEN);
        $this->assertSentTo('sendVoice');
    }

    public function test_sendVideoNote_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendVideoNote($this->req(['video_note' => 'https://example.com/note.mp4', 'length' => 240]), self::TOKEN);
        $this->assertSentTo('sendVideoNote');
    }

    public function test_sendSticker_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendSticker($this->req(['sticker' => 'CAACAgIAAx']), self::TOKEN);
        $this->assertSentTo('sendSticker');
    }

    public function test_sendMedia_con_autodestroy_despacha_job(): void
    {
        Queue::fake();
        $this->fakeOk();

        TelegramController::sendVideo($this->req(['video' => 'https://example.com/vid.mp4']), self::TOKEN, autodestroy: 10);

        Queue::assertPushed(DeleteTelegramMessage::class);
    }

    public function test_sendMedia_fallback_manual_upload_cuando_telegram_falla(): void
    {
        Http::fake([
            'api.telegram.org/*/sendAudio*' => Http::response([
                'ok' => false,
                'description' => 'Bad Request: failed to get HTTP URL content',
            ], 400),
            'example.com/*' => Http::response('audiobytes', 200),
            'api.telegram.org/*' => Http::response($this->ok(), 200),
        ]);

        $response = TelegramController::sendAudio(
            $this->req(['audio' => 'https://example.com/song.mp3']),
            self::TOKEN
        );

        $this->assertNotNull($response);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  OTROS TIPOS DE MENSAJE
    // ══════════════════════════════════════════════════════════════════════════

    public function test_sendLocation_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendLocation($this->req(['latitude' => 23.13, 'longitude' => -82.38]), self::TOKEN);
        $this->assertSentTo('sendLocation');
    }

    public function test_editMessageLiveLocation_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::editMessageLiveLocation($this->req(['latitude' => 23.13, 'longitude' => -82.38]), self::TOKEN);
        $this->assertSentTo('editMessageLiveLocation');
    }

    public function test_stopMessageLiveLocation_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::stopMessageLiveLocation($this->req(), self::TOKEN);
        $this->assertSentTo('stopMessageLiveLocation');
    }

    public function test_sendVenue_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendVenue($this->req([
            'latitude' => 23.13,
            'longitude' => -82.38,
            'title' => 'Hotel Nacional',
            'address' => 'Calle O, Vedado',
        ]), self::TOKEN);
        $this->assertSentTo('sendVenue');
    }

    public function test_sendContact_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendContact($this->req([
            'phone_number' => '+5312345678',
            'first_name' => 'Donel',
        ]), self::TOKEN);
        $this->assertSentTo('sendContact');
    }

    public function test_sendPoll_con_opciones_como_array(): void
    {
        $this->fakeOk(['id' => 'poll_abc']);
        TelegramController::sendPoll($this->req([
            'question' => '¿Mejor framework?',
            'options' => [
                ['text' => 'Laravel'],
                ['text' => 'Symfony'],
            ],
        ]), self::TOKEN);
        $this->assertSentTo('sendPoll');
    }

    public function test_sendPoll_con_opciones_como_json_string(): void
    {
        $this->fakeOk();
        TelegramController::sendPoll($this->req([
            'question' => '¿Test?',
            'options' => json_encode([['text' => 'Sí'], ['text' => 'No']]),
        ]), self::TOKEN);
        $this->assertSentTo('sendPoll');
    }

    public function test_sendDice_llama_endpoint_correcto(): void
    {
        $this->fakeOk(['value' => 6]);
        TelegramController::sendDice($this->req(['emoji' => '🎲']), self::TOKEN);
        $this->assertSentTo('sendDice');
    }

    public function test_sendChatAction_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendChatAction($this->req(['action' => 'typing']), self::TOKEN);
        $this->assertSentTo('sendChatAction');
    }

    public function test_setMessageReaction_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::setMessageReaction($this->req([
            'reaction' => [['type' => 'emoji', 'emoji' => '👍']],
        ]), self::TOKEN);
        $this->assertSentTo('setMessageReaction');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  EDICIÓN DE MENSAJES
    // ══════════════════════════════════════════════════════════════════════════

    public function test_editMessageCaption_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::editMessageCaption($this->req(['caption' => 'New caption']), self::TOKEN);
        $this->assertSentTo('editMessageCaption');
    }

    public function test_editMessageMedia_con_media_como_array(): void
    {
        $this->fakeOk();
        TelegramController::editMessageMedia($this->req([
            'media' => ['type' => 'photo', 'media' => 'https://example.com/new.jpg'],
        ]), self::TOKEN);
        $this->assertSentTo('editMessageMedia');
    }

    public function test_editMessageReplyMarkup_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::editMessageReplyMarkup($this->req([
            'reply_markup' => json_encode(['inline_keyboard' => []]),
        ]), self::TOKEN);
        $this->assertSentTo('editMessageReplyMarkup');
    }

    public function test_stopPoll_llama_endpoint_correcto(): void
    {
        $this->fakeOk(['is_closed' => true]);
        TelegramController::stopPoll($this->req(), self::TOKEN);
        $this->assertSentTo('stopPoll');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  INFO DEL BOT Y USUARIO
    // ══════════════════════════════════════════════════════════════════════════

    public function test_getBotInfo_retorna_json(): void
    {
        Http::fake(['api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['id' => 1, 'username' => 'testbot']], 200)]);

        $response = TelegramController::getBotInfo(self::TOKEN);
        $data = json_decode($response, true);

        $this->assertTrue($data['ok']);
        $this->assertEquals('testbot', $data['result']['username']);
    }

    public function test_getBotInfo_retorna_false_si_falla(): void
    {
        Http::fake(['api.telegram.org/*' => fn() => throw new \Exception('timeout')]);

        $result = TelegramController::getBotInfo(self::TOKEN);
        $this->assertFalse($result);
    }

    public function test_getUserInfo_construye_full_name(): void
    {
        Http::fake([
            'api.telegram.org/*/getChat*' => Http::response([
                'ok' => true,
                'result' => [
                    'first_name' => 'Donel',
                    'last_name' => 'Vázquez',
                    'username' => 'dvzambrano',
                ],
            ], 200),
        ]);

        $response = TelegramController::getUserInfo(self::USER_ID, self::TOKEN);
        $data = json_decode($response, true);

        $this->assertStringContainsString('Donel', $data['result']['full_name']);
        $this->assertArrayHasKey('full_info', $data['result']);
    }

    public function test_getUserPhotos_retorna_array_vacio_si_no_hay_fotos(): void
    {
        Http::fake([
            'api.telegram.org/*/getUserProfilePhotos*' => Http::response(['ok' => true, 'result' => ['total_count' => 0, 'photos' => []]], 200),
        ]);

        $photos = TelegramController::getUserPhotos(self::USER_ID, self::TOKEN);
        $this->assertIsArray($photos);
        $this->assertEmpty($photos);
    }

    public function test_getUserPhotos_retorna_fotos_si_existen(): void
    {
        Http::fake([
            'api.telegram.org/*/getUserProfilePhotos*' => Http::response([
                'ok' => true,
                'result' => [
                    'total_count' => 1,
                    'photos' => [[['file_id' => 'abc123', 'width' => 160, 'height' => 160]]],
                ],
            ], 200),
        ]);

        $photos = TelegramController::getUserPhotos(self::USER_ID, self::TOKEN);
        $this->assertCount(1, $photos);
        $this->assertEquals('abc123', $photos[0][0]['file_id']);
    }

    public function test_getFileUrl_retorna_body_en_exito(): void
    {
        Http::fake([
            'api.telegram.org/*/getFile*' => Http::response([
                'ok' => true,
                'result' => ['file_id' => 'abc', 'file_path' => 'photos/file.jpg'],
            ], 200),
        ]);

        $response = TelegramController::getFileUrl('abc', self::TOKEN);
        $data = json_decode($response, true);

        $this->assertEquals('photos/file.jpg', $data['result']['file_path']);
    }

    public function test_getFileUrl_retorna_false_si_falla(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('', 404)]);

        $result = TelegramController::getFileUrl('bad_id', self::TOKEN);
        $this->assertFalse($result);
    }

    public function test_getFile_retorna_contenido_del_archivo(): void
    {
        Http::fake(['api.telegram.org/file/*' => Http::response('binarydata', 200)]);

        $result = TelegramController::getFile('photos/file.jpg', self::TOKEN);
        $this->assertEquals('binarydata', $result);
    }

    public function test_getFile_retorna_null_si_falla(): void
    {
        Http::fake(['api.telegram.org/file/*' => Http::response('', 404)]);

        $result = TelegramController::getFile('bad/path.jpg', self::TOKEN);
        $this->assertNull($result);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  GESTIÓN DE MIEMBROS
    // ══════════════════════════════════════════════════════════════════════════

    public function test_getChatAdministrators_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::getChatAdministrators(self::CHAT_ID, self::TOKEN);
        $this->assertSentTo('getChatAdministrators');
    }

    public function test_getChatMemberCount_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => 42], 200)]);
        TelegramController::getChatMemberCount(self::CHAT_ID, self::TOKEN);
        $this->assertSentTo('getChatMemberCount');
    }

    public function test_getChatMember_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['status' => 'member']], 200)]);
        TelegramController::getChatMember(self::CHAT_ID, self::USER_ID, self::TOKEN);
        $this->assertSentTo('getChatMember');
    }

    public function test_banChatMember_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::banChatMember($this->req(['user_id' => self::USER_ID, 'revoke_messages' => true]), self::TOKEN);
        $this->assertSentTo('banChatMember');
    }

    public function test_unbanChatMember_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::unbanChatMember($this->req(['user_id' => self::USER_ID]), self::TOKEN);
        $this->assertSentTo('unbanChatMember');
    }

    public function test_restrictChatMember_con_permisos_como_array(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::restrictChatMember($this->req([
            'user_id' => self::USER_ID,
            'permissions' => ['can_send_messages' => false],
        ]), self::TOKEN);
        $this->assertSentTo('restrictChatMember');
    }

    public function test_promoteChatMember_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::promoteChatMember($this->req([
            'user_id' => self::USER_ID,
            'can_delete_messages' => true,
        ]), self::TOKEN);
        $this->assertSentTo('promoteChatMember');
    }

    public function test_setChatAdministratorCustomTitle_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setChatAdministratorCustomTitle($this->req([
            'user_id' => self::USER_ID,
            'custom_title' => 'Dev Lead',
        ]), self::TOKEN);
        $this->assertSentTo('setChatAdministratorCustomTitle');
    }

    public function test_banChatSenderChat_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::banChatSenderChat($this->req(['sender_chat_id' => 99999]), self::TOKEN);
        $this->assertSentTo('banChatSenderChat');
    }

    public function test_unbanChatSenderChat_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::unbanChatSenderChat($this->req(['sender_chat_id' => 99999]), self::TOKEN);
        $this->assertSentTo('unbanChatSenderChat');
    }

    public function test_approveChatJoinRequest_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::approveChatJoinRequest($this->req(['user_id' => self::USER_ID]), self::TOKEN);
        $this->assertSentTo('approveChatJoinRequest');
    }

    public function test_declineChatJoinRequest_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::declineChatJoinRequest($this->req(['user_id' => self::USER_ID]), self::TOKEN);
        $this->assertSentTo('declineChatJoinRequest');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  CONFIGURACIÓN DEL CHAT
    // ══════════════════════════════════════════════════════════════════════════

    public function test_setChatPermissions_con_permisos_como_array(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setChatPermissions($this->req([
            'permissions' => ['can_send_messages' => true, 'can_send_polls' => false],
        ]), self::TOKEN);
        $this->assertSentTo('setChatPermissions');
    }

    public function test_setChatTitle_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setChatTitle($this->req(['title' => 'Nuevo título']), self::TOKEN);
        $this->assertSentTo('setChatTitle');
    }

    public function test_setChatDescription_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setChatDescription($this->req(['description' => 'Descripción nueva']), self::TOKEN);
        $this->assertSentTo('setChatDescription');
    }

    public function test_leaveChat_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::leaveChat(self::CHAT_ID, self::TOKEN);
        $this->assertSentTo('leaveChat');
    }

    public function test_exportChatInviteLink_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => 'https://t.me/+abc'], 200)]);
        TelegramController::exportChatInviteLink(self::CHAT_ID, self::TOKEN);
        $this->assertSentTo('exportChatInviteLink');
    }

    public function test_createChatInviteLink_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['invite_link' => 'https://t.me/+xyz']], 200)]);
        TelegramController::createChatInviteLink($this->req(['name' => 'Invite 1', 'member_limit' => 10]), self::TOKEN);
        $this->assertSentTo('createChatInviteLink');
    }

    public function test_editChatInviteLink_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::editChatInviteLink($this->req(['invite_link' => 'https://t.me/+xyz', 'name' => 'Updated']), self::TOKEN);
        $this->assertSentTo('editChatInviteLink');
    }

    public function test_revokeChatInviteLink_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::revokeChatInviteLink($this->req(['invite_link' => 'https://t.me/+xyz']), self::TOKEN);
        $this->assertSentTo('revokeChatInviteLink');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  CALLBACK QUERY / INLINE
    // ══════════════════════════════════════════════════════════════════════════

    public function test_answerCallbackQuery_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::answerCallbackQuery($this->req([
            'callback_query_id' => 'cq_abc',
            'text' => '✅ Acción completada',
            'show_alert' => true,
        ]), self::TOKEN);
        $this->assertSentTo('answerCallbackQuery');
    }

    public function test_answerInlineQuery_con_results_como_array(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::answerInlineQuery($this->req([
            'inline_query_id' => 'iq_123',
            'results' => [['type' => 'article', 'id' => '1', 'title' => 'Test']],
        ]), self::TOKEN);
        $this->assertSentTo('answerInlineQuery');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  COMANDOS DEL BOT
    // ══════════════════════════════════════════════════════════════════════════

    public function test_setMyCommands_con_array_de_comandos(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setMyCommands([
            ['command' => 'start', 'description' => 'Iniciar bot'],
            ['command' => 'help', 'description' => 'Ayuda'],
        ], self::TOKEN);
        $this->assertSentTo('setMyCommands');
    }

    public function test_setMyCommands_con_scope_y_language_code(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setMyCommands(
            [['command' => 'start', 'description' => 'Start']],
            self::TOKEN,
            scope: ['type' => 'all_private_chats'],
            language_code: 'es'
        );
        $this->assertSentTo('setMyCommands');
    }

    public function test_getMyCommands_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::getMyCommands(self::TOKEN);
        $this->assertSentTo('getMyCommands');
    }

    public function test_deleteMyCommands_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::deleteMyCommands(self::TOKEN);
        $this->assertSentTo('deleteMyCommands');
    }

    public function test_setMyName_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setMyName(self::TOKEN, 'Mi Bot', 'es');
        $this->assertSentTo('setMyName');
    }

    public function test_getMyName_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['name' => 'Mi Bot']], 200)]);
        TelegramController::getMyName(self::TOKEN, 'es');
        $this->assertSentTo('getMyName');
    }

    public function test_setMyDescription_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setMyDescription(self::TOKEN, 'Bot de pruebas', 'es');
        $this->assertSentTo('setMyDescription');
    }

    public function test_getMyDescription_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['description' => 'Bot de pruebas']], 200)]);
        TelegramController::getMyDescription(self::TOKEN, 'es');
        $this->assertSentTo('getMyDescription');
    }

    public function test_setMyShortDescription_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setMyShortDescription(self::TOKEN, 'Bot corto', 'es');
        $this->assertSentTo('setMyShortDescription');
    }

    public function test_getMyShortDescription_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['short_description' => 'Bot corto']], 200)]);
        TelegramController::getMyShortDescription(self::TOKEN);
        $this->assertSentTo('getMyShortDescription');
    }

    public function test_setMyDefaultAdministratorRights_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setMyDefaultAdministratorRights(self::TOKEN, ['can_manage_chat' => true], for_channels: false);
        $this->assertSentTo('setMyDefaultAdministratorRights');
    }

    public function test_getMyDefaultAdministratorRights_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::getMyDefaultAdministratorRights(self::TOKEN);
        $this->assertSentTo('getMyDefaultAdministratorRights');
    }

    public function test_setChatMenuButton_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setChatMenuButton(self::TOKEN, self::CHAT_ID, ['type' => 'commands']);
        $this->assertSentTo('setChatMenuButton');
    }

    public function test_getChatMenuButton_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['type' => 'commands']], 200)]);
        TelegramController::getChatMenuButton(self::TOKEN, self::CHAT_ID);
        $this->assertSentTo('getChatMenuButton');
    }

    public function test_getUserChatBoosts_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['boosts' => []]], 200)]);
        TelegramController::getUserChatBoosts(self::CHAT_ID, self::USER_ID, self::TOKEN);
        $this->assertSentTo('getUserChatBoosts');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  STICKERS
    // ══════════════════════════════════════════════════════════════════════════

    public function test_getStickerSet_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['name' => 'MyPack']], 200)]);
        TelegramController::getStickerSet('MyPack', self::TOKEN);
        $this->assertSentTo('getStickerSet');
    }

    public function test_getCustomEmojiStickers_con_array_de_ids(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::getCustomEmojiStickers(['emoji1', 'emoji2'], self::TOKEN);
        $this->assertSentTo('getCustomEmojiStickers');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  FORUM TOPICS
    // ══════════════════════════════════════════════════════════════════════════

    public function test_getForumTopicIconStickers_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::getForumTopicIconStickers(self::TOKEN);
        $this->assertSentTo('getForumTopicIconStickers');
    }

    public function test_createForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_thread_id' => 10]], 200)]);
        TelegramController::createForumTopic($this->req(['name' => 'Anuncios']), self::TOKEN);
        $this->assertSentTo('createForumTopic');
    }

    public function test_editForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::editForumTopic($this->req(['message_thread_id' => 10, 'name' => 'Nuevo nombre']), self::TOKEN);
        $this->assertSentTo('editForumTopic');
    }

    public function test_closeForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::closeForumTopic($this->req(['message_thread_id' => 10]), self::TOKEN);
        $this->assertSentTo('closeForumTopic');
    }

    public function test_reopenForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::reopenForumTopic($this->req(['message_thread_id' => 10]), self::TOKEN);
        $this->assertSentTo('reopenForumTopic');
    }

    public function test_deleteForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::deleteForumTopic($this->req(['message_thread_id' => 10]), self::TOKEN);
        $this->assertSentTo('deleteForumTopic');
    }

    public function test_editGeneralForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::editGeneralForumTopic($this->req(['name' => 'General']), self::TOKEN);
        $this->assertSentTo('editGeneralForumTopic');
    }

    public function test_closeGeneralForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::closeGeneralForumTopic($this->req(), self::TOKEN);
        $this->assertSentTo('closeGeneralForumTopic');
    }

    public function test_reopenGeneralForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::reopenGeneralForumTopic($this->req(), self::TOKEN);
        $this->assertSentTo('reopenGeneralForumTopic');
    }

    public function test_hideGeneralForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::hideGeneralForumTopic($this->req(), self::TOKEN);
        $this->assertSentTo('hideGeneralForumTopic');
    }

    public function test_unhideGeneralForumTopic_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::unhideGeneralForumTopic($this->req(), self::TOKEN);
        $this->assertSentTo('unhideGeneralForumTopic');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PAGOS
    // ══════════════════════════════════════════════════════════════════════════

    public function test_sendInvoice_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendInvoice($this->req([
            'title' => 'Producto',
            'description' => 'Descripción del producto',
            'payload' => 'order_123',
            'currency' => 'USD',
            'prices' => [['label' => 'Precio', 'amount' => 1000]],
        ]), self::TOKEN);
        $this->assertSentTo('sendInvoice');
    }

    public function test_answerShippingQuery_ok_true(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::answerShippingQuery($this->req([
            'shipping_query_id' => 'sq_abc',
            'ok' => true,
            'shipping_options' => [['id' => 'fast', 'title' => 'Rápido', 'prices' => [['label' => 'Envío', 'amount' => 500]]]],
        ]), self::TOKEN);
        $this->assertSentTo('answerShippingQuery');
    }

    public function test_answerPreCheckoutQuery_ok_true(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::answerPreCheckoutQuery($this->req([
            'pre_checkout_query_id' => 'pcq_abc',
            'ok' => true,
        ]), self::TOKEN);
        $this->assertSentTo('answerPreCheckoutQuery');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  JUEGOS
    // ══════════════════════════════════════════════════════════════════════════

    public function test_sendGame_llama_endpoint_correcto(): void
    {
        $this->fakeOk();
        TelegramController::sendGame($this->req(['game_short_name' => 'my_game']), self::TOKEN);
        $this->assertSentTo('sendGame');
    }

    public function test_setGameScore_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setGameScore($this->req([
            'user_id' => self::USER_ID,
            'score' => 1500,
            'chat_id' => self::CHAT_ID,
            'message_id' => self::MSG_ID,
        ]), self::TOKEN);
        $this->assertSentTo('setGameScore');
    }

    public function test_getGameHighScores_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::getGameHighScores(self::USER_ID, self::TOKEN, self::CHAT_ID, self::MSG_ID);
        $this->assertSentTo('getGameHighScores');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  WEBHOOK / UPDATES
    // ══════════════════════════════════════════════════════════════════════════

    public function test_setWebhook_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::setWebhook('https://mi-sitio.com/webhook', self::TOKEN);
        $this->assertSentTo('setWebhook');
    }

    public function test_deleteWebhook_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::deleteWebhook(self::TOKEN, drop_pending_updates: true);
        $this->assertSentTo('deleteWebhook');
    }

    public function test_getWebhookInfo_llama_endpoint_correcto(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['url' => 'https://mi-sitio.com/webhook', 'has_custom_certificate' => false, 'pending_update_count' => 0],
            ], 200)
        ]);

        $response = TelegramController::getWebhookInfo(self::TOKEN);
        $data = json_decode($response, true);

        $this->assertTrue($data['ok']);
        $this->assertSentTo('getWebhookInfo');
    }

    public function test_getUpdates_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        TelegramController::getUpdates(self::TOKEN, ['offset' => 100, 'limit' => 10, 'timeout' => 30]);
        $this->assertSentTo('getUpdates');
    }

    public function test_logOut_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::logOut(self::TOKEN);
        $this->assertSentTo('logOut');
    }

    public function test_close_llama_endpoint_correcto(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true], 200)]);
        TelegramController::close(self::TOKEN);
        $this->assertSentTo('close');
    }
}
