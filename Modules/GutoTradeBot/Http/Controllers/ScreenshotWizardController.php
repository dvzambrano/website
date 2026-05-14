<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Http\Controllers\Controller;
use Modules\Laravel\Services\TextService;
use Modules\TelegramBot\Http\Controllers\WizardController;

class ScreenshotWizardController extends Controller
{
    /**
     * Inicia el wizard limpiando cualquier sesión previa y arrancando desde cero.
     * Llamar desde las estrategias del bot (clicks de botones).
     *
     * @param object $bot        Contexto del bot (actor, tenant, message, PaymentsController, CapitalsController)
     * @param string $type       'payment' | 'capital'
     * @param int    $sender     Rol que envía: 1=gestor, 2=remesador, 3=receptor, 4=admin capital
     * @param int    $moneysType Tipo de registro: 1=capital, 2=pago
     */
    public function startWizard($bot, string $type = 'payment', int $sender = 2, int $moneysType = 2): mixed
    {
        Cache::forget("wizard_{$bot->tenant->key}_{$bot->actor->user_id}");
        return $this->wizard($bot, $type, $sender, $moneysType);
    }

    /**
     * Punto de entrada del wizard — usado tanto para arrancar como para reanudar desde caché.
     * El WizardController lo invoca directamente al enrutar mensajes de texto entrantes.
     * Para fotos entrantes, GutoTradeBotController lo llama tras detectar el wizard activo.
     */
    public function wizard($bot, string $type = 'payment', int $sender = 2, int $moneysType = 2): mixed
    {
        $self = $this;
        $steps = [
            ['name' => 'STEP_SCREENSHOT', 'handler' => fn($b, $s) => $self->stepScreenshot($b, $s)],
            ['name' => 'STEP_CAPTION',    'handler' => fn($b, $s) => $self->stepCaption($b, $s)],
        ];

        return (new WizardController())->run($bot, $steps, [
            'controller'  => static::class,
            'method'      => 'wizard',
            'initialData' => ['type' => $type, 'sender' => $sender, 'moneysType' => $moneysType],
            'onComplete'  => fn($b, $s) => $self->finish($b, $s),
            'onCancel'    => fn($b) => $self->cancelResponse($b),
        ]);
    }

    // -------------------------------------------------------------------------
    // Pasos
    // -------------------------------------------------------------------------

    private function stepScreenshot($bot, array $state): array
    {
        $message    = $bot->message;
        $moneysType = $state['data']['moneysType'] ?? 2;

        if (isset($message['photo']) || isset($message['document'])) {
            $path        = $bot->getScreenshotPath();
            $captionText = $message['caption'] ?? null;

            $merge = [
                'screenshot_path'  => $path,
                'original_message' => $message,
            ];

            if ($captionText !== null) {
                $result = $bot->PaymentsController->processCaption($captionText);

                if ($result['success']) {
                    $merge['fullname']     = $result['fullname'];
                    $merge['amount']       = $result['amount'];
                    $merge['caption_text'] = $captionText;
                    return ['__advance' => true, 'merge' => $merge];
                }

                // Caption inválido: mostrar error y quedarse en este paso
                return $this->invalidCaptionResponse($captionText, $moneysType);
            }

            // Foto sin caption → avanzar al paso 2 para pedirlo
            return ['__advance' => true, 'merge' => $merge];
        }

        // Aún no hay foto → mostrar prompt
        return $this->screenshotPrompt($moneysType);
    }

    private function stepCaption($bot, array $state): array
    {
        // Si el caption ya fue recibido junto a la foto, saltar este paso
        if (isset($state['data']['fullname'])) {
            return ['__advance' => true, 'merge' => []];
        }

        $moneysType = $state['data']['moneysType'] ?? 2;
        $text       = $bot->message['text'] ?? null;

        if (!$text) {
            return $this->captionPrompt($moneysType);
        }

        $result = $bot->PaymentsController->processCaption($text);

        if ($result['success']) {
            return ['__advance' => true, 'merge' => [
                'fullname'     => $result['fullname'],
                'amount'       => $result['amount'],
                'caption_text' => $text,
            ]];
        }

        return $this->invalidCaptionResponse($text, $moneysType);
    }

    // -------------------------------------------------------------------------
    // Finalización
    // -------------------------------------------------------------------------

    private function finish($bot, array $state): array
    {
        $data            = $state['data'];
        $originalMessage = $data['original_message'];

        // Añadir el caption (puede haber venido con la foto o en el paso 2)
        $originalMessage['caption'] = $data['caption_text'] ?? ($originalMessage['caption'] ?? '');

        // Pagos reenviados: usar el ID del remitente original como from.id
        if (isset($originalMessage['forward_from'])) {
            $originalMessage['from']['id'] = $originalMessage['forward_from']['id'];
        }

        // Restaurar el mensaje con foto en el request para que processMoney() lo lea correctamente
        request()->merge(['message' => $originalMessage]);
        $bot->message = $originalMessage;

        $sender     = $data['sender']     ?? 2;
        $moneysType = $data['moneysType'] ?? 2;

        return $moneysType == 1
            ? $bot->CapitalsController->processMoney($bot, $sender, $moneysType)
            : $bot->PaymentsController->processMoney($bot, $sender, $moneysType);
    }

    // -------------------------------------------------------------------------
    // Respuestas de interfaz
    // -------------------------------------------------------------------------

    private function screenshotPrompt(int $moneysType): array
    {
        $icon    = $moneysType == 1 ? '💰' : '💶';
        $titleKey = $moneysType == 1
            ? 'gutotradebot::bot.screenshot_wizard.capital.step1_title'
            : 'gutotradebot::bot.screenshot_wizard.payment.step1_title';
        $example = $moneysType == 1 ? '`Juan Perez 1200`' : '`Juan Perez 20`';

        $text = "{$icon} *" . TextService::mdv2(Lang::get($titleKey)) . "*\n\n"
            . "_" . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step1_instructions')) . "_\n\n"
            . "📎 " . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step1_prompt')) . "\n"
            . "💬 " . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.caption_hint')) . ": {$example}";

        return [
            'text'         => $text,
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => '✋ ' . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), 'callback_data' => '/wizardcancel'],
            ]]]),
        ];
    }

    private function captionPrompt(int $moneysType): array
    {
        $example = $moneysType == 1 ? '`Juan Perez 1200`' : '`Juan Perez 20`';

        $text = "✍️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step2_title')) . "*\n\n"
            . "_" . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step2_instructions')) . "_\n\n"
            . "📝 " . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step2_format')) . ": `Nombre Apellido monto`\n"
            . "💡 " . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step2_example')) . ": {$example}";

        return [
            'text'         => $text,
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => '✋ ' . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), 'callback_data' => '/wizardcancel'],
            ]]]),
        ];
    }

    private function invalidCaptionResponse(string $badCaption, int $moneysType): array
    {
        $example = $moneysType == 1 ? '`Juan Perez 1200`' : '`Juan Perez 20`';

        $text = "❌ *" . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.caption_error_title')) . "*\n\n"
            . "_" . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.caption_error_desc')) . "_\n\n"
            . "📝 " . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step2_format')) . ": `Nombre Apellido monto`\n"
            . "💡 " . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.step2_example')) . ": {$example}\n"
            . "❌ " . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.received')) . ": `" . TextService::mdv2($badCaption) . "`";

        return [
            'text'         => $text,
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => '✋ ' . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), 'callback_data' => '/wizardcancel'],
            ]]]),
        ];
    }

    private function cancelResponse($bot): array
    {
        return [
            'text'         => "✋ *" . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot_wizard.cancelled')) . "*",
            'reply_markup' => json_encode(['inline_keyboard' => [[
                ['text' => '↖️ ' . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), 'callback_data' => 'menu'],
            ]]]),
        ];
    }
}
