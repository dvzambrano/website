<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Http\Controllers\WizardController;
use Modules\ZentroTraderBot\Entities\Paymentmethods;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;

class PaymentMethodsController extends Controller
{
    // Texto que inicia el wizard; no debe tratarse como datos del usuario
    private const TRIGGER_COMMAND = '/p2ppaymentmethods';

    public function wizard($bot)
    {
        $self = $this;

        $methods = Paymentmethods::orderBy('id')->get();
        $total = $methods->count();

        $steps = [];
        foreach ($methods as $index => $method) {
            $steps[] = [
                'name'    => 'STEP_' . strtoupper($method->identifier),
                'handler' => fn($b, $_s) => $self->stepMethod($b, $method, $index + 1, $total),
            ];
        }

        return (new WizardController())->run($bot, $steps, [
            'controller' => self::class,
            'method'     => 'wizard',
            'initialData' => [],
            'onComplete' => fn($b, $_s) => $self->completedResponse($b),
            'onCancel'   => fn($b) => $self->cancelResponse($b),
        ]);
    }

    private function stepMethod($bot, $method, int $stepNum, int $total): array
    {
        $this->deleteUserText($bot);

        $text = $bot->message['text'] ?? null;
        $userId = $bot->actor->user_id;
        $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);

        // El comando que abrió el wizard no es un dato válido
        if ($text === self::TRIGGER_COMMAND) {
            $text = null;
        }

        // Botón "Siguiente": avanza sin guardar nada nuevo para este método
        if ($text === '/wizardnext') {
            return ['__advance' => true, 'merge' => []];
        }

        // El usuario escribió sus datos → guardar inmediatamente y avanzar
        if ($text !== null) {
            $this->saveMethodForUser($userId, $method, $text);
            return ['__advance' => true, 'merge' => []];
        }

        // Renderizar el prompt para este método
        $suscriptor = Suscriptions::where('user_id', $userId)->first();
        $existing = $suscriptor->data['payment_methods'][$method->identifier]['details'] ?? null;

        $currentValueLine = $existing
            ? "\n▫️ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.current_value")) . ": `" . TextService::mdv2($existing) . "`"
            : "\n▫️ _" . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.not_configured")) . "_";

        $navButtons = [];
        if ($stepNum > 1) {
            $navButtons[] = [
                "text"          => "⬅️ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.options.back")),
                "callback_data" => "/wizardprevious",
            ];
        }
        $navButtons[] = [
            "text"          => "➡️ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.next")),
            "callback_data" => "/wizardnext",
        ];
        $navButtons[] = [
            "text"          => "❌ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.options.cancel")),
            "callback_data" => "/wizardcancel",
        ];

        return [
            "text" =>
                "💳 *" . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.title")) . "*\n" .
                "◾️ _" . TextService::mdv2(Lang::get("zentrotraderbot::bot.wizard.step", [
                    'n'     => TextService::getNumberAsEmoji($stepNum),
                    'total' => TextService::getNumberAsEmoji($total),
                ])) . "_\n\n" .
                ($method->icon ? $method->icon . " " : "") . "*" . TextService::mdv2($method->name) . "*" .
                $currentValueLine . "\n\n" .
                "▫️ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.ask_details", ['method' => $method->name])) . "\n" .
                "▫️ _" . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.next_hint")) . "_",
            "chat"         => ["id" => $userId],
            "reply_markup" => json_encode(["inline_keyboard" => [$navButtons]]),
            "editprevious" => $isCallback ? 1 : 0,
        ];
    }

    private function saveMethodForUser(int $userId, $method, string $details): void
    {
        $suscriptor = Suscriptions::where('user_id', $userId)->first();
        if (!$suscriptor) return;

        $data = $suscriptor->data ?? [];
        $data['payment_methods'][$method->identifier] = [
            'name'    => $method->name,
            'icon'    => $method->icon,
            'details' => $details,
        ];
        $suscriptor->update(['data' => $data]);
    }

    private function completedResponse($bot): array
    {
        $userId = $bot->actor->user_id;
        $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);
        return [
            "text" =>
                "✅ *" . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.saved_title")) . "*\n" .
                "▫️ _" . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.saved_body")) . "_\n\n" .
                "👇 " . TextService::mdv2(Lang::get("telegrambot::bot.prompts.whatsnext")),
            "chat"         => ["id" => $userId],
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "⬅️ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.options.backtop2pmenu")), "callback_data" => "/p2pmenu"]],
                    [["text" => "↖️ " . TextService::mdv2(Lang::get("telegrambot::bot.options.backtomainmenu")), "callback_data" => "menu"]],
                ],
            ]),
            "editprevious" => $isCallback ? 1 : 0,
        ];
    }

    private function cancelResponse($bot): array
    {
        $userId = $bot->actor->user_id;
        $isCallback = isset($bot->callback_query) || ($bot->is_callback ?? false);
        return [
            "text" =>
                "❌ *" . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.cancelled_title")) . "*\n" .
                "_" . TextService::mdv2(Lang::get("zentrotraderbot::bot.payment_wizard.cancelled")) . "_\n\n" .
                "👇 " . TextService::mdv2(Lang::get("telegrambot::bot.prompts.whatsnext")),
            "chat"         => ["id" => $userId],
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "⬅️ " . TextService::mdv2(Lang::get("zentrotraderbot::bot.options.backtop2pmenu")), "callback_data" => "/p2pmenu"]],
                    [["text" => "↖️ " . TextService::mdv2(Lang::get("telegrambot::bot.options.backtomainmenu")), "callback_data" => "menu"]],
                ],
            ]),
            "editprevious" => $isCallback ? 1 : 0,
        ];
    }

    private function deleteUserText($bot): void
    {
        $isCallback = isset($bot->callback_query) || isset($bot->message['reply_markup']);
        if (!$isCallback && !empty($bot->message["message_id"])) {
            try {
                $array = ["message" => ["id" => $bot->message["message_id"], "chat" => ["id" => $bot->message["chat"]["id"]]]];
                TelegramController::deleteMessage($array, $bot->tenant->token);
            } catch (\Throwable $th) {
            }
        }
    }
}
