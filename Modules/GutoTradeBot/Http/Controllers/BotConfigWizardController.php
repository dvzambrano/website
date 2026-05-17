<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Http\Controllers\Controller;
use Modules\Laravel\Services\TextService;
use Modules\TelegramBot\Http\Controllers\WizardController;

class BotConfigWizardController extends Controller
{
    // ==========================================================================
    // Bot Config Menu
    // ==========================================================================

    public function configMenu($bot): array
    {
        return [
            'text' => "⚙️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.menu.header')) . "*\n\n"
                . "_" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.menu.desc')) . "_",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "📧 " . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.menu.email')), 'callback_data' => 'botconfigemail']],
                    [['text' => "🔔 " . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.menu.notifications')), 'callback_data' => 'botconfignotifications']],
                    [['text' => "↖️ " . Lang::get('telegrambot::bot.options.backtoadminmenu'), 'callback_data' => 'adminmenu']],
                ],
            ]),
        ];
    }

    // ==========================================================================
    // Email Wizard
    // ==========================================================================

    public function startEmailWizard($bot): mixed
    {
        Cache::forget("wizard_{$bot->tenant->key}_{$bot->actor->user_id}");
        return $this->emailWizard($bot);
    }

    public function emailWizard($bot): mixed
    {
        $self = $this;
        $steps = [
            ['name' => 'STEP_HOST', 'handler' => fn($b, $s) => $this->stepHost($b, $s)],
            ['name' => 'STEP_PORT', 'handler' => fn($b, $s) => $this->stepPort($b, $s)],
            ['name' => 'STEP_USERNAME', 'handler' => fn($b, $s) => $self->stepUsername($b, $s)],
            ['name' => 'STEP_PASSWORD', 'handler' => fn($b, $s) => $self->stepPassword($b, $s)],
            ['name' => 'STEP_ENCRYPTION', 'handler' => fn($b, $s) => $self->stepEncryption($b, $s)],
            ['name' => 'STEP_CERT', 'handler' => fn($b, $s) => $self->stepCert($b, $s)],
        ];

        return (new WizardController())->run($bot, $steps, [
            'controller' => static::class,
            'method' => 'emailWizard',
            'initialData' => ['current' => $bot->tenant->data['email'] ?? []],
            'onComplete' => fn($b, $s) => $self->saveEmailConfig($b, $s),
            'onCancel' => $self->cancelResponse(...),
        ]);
    }

    private function stepHost($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            return ['__advance' => true, 'merge' => ['host' => trim($text)]];
        }

        $current = $state['data']['current']['host'] ?? '—';
        return $this->textInputPrompt(
            '📡',
            Lang::get('gutotradebot::bot.botconfig.email.host_label'),
            $current,
            Lang::get('gutotradebot::bot.botconfig.email.host_prompt')
        );
    }

    private function stepPort($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            if (!is_numeric($text)) {
                return $this->errorPrompt(Lang::get('gutotradebot::bot.botconfig.email.port_error'));
            }
            return ['__advance' => true, 'merge' => ['port' => (int) $text]];
        }

        $current = $state['data']['current']['port'] ?? '—';
        return $this->textInputPrompt(
            '🔌',
            Lang::get('gutotradebot::bot.botconfig.email.port_label'),
            $current,
            Lang::get('gutotradebot::bot.botconfig.email.port_prompt')
        );
    }

    private function stepUsername($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            return ['__advance' => true, 'merge' => ['username' => trim($text)]];
        }

        $current = $state['data']['current']['username'] ?? '—';
        return $this->textInputPrompt(
            '👤',
            Lang::get('gutotradebot::bot.botconfig.email.username_label'),
            $current,
            Lang::get('gutotradebot::bot.botconfig.email.username_prompt')
        );
    }

    private function stepPassword($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            return ['__advance' => true, 'merge' => ['password' => trim($text)]];
        }

        return [
            'text' => "🔐 *" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.password_label')) . "*\n\n"
                . "⚠️ _" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.password_hidden')) . "_\n\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.password_prompt')),
            'reply_markup' => $this->cancelKeyboard(),
        ];
    }

    private function stepEncryption($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;

        if (in_array($text, ['ssl', 'tls', 'none'])) {
            return ['__advance' => true, 'merge' => ['encryption' => $text]];
        }

        $current = $state['data']['current']['encryption'] ?? '—';
        return [
            'text' => "🔒 *" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.encryption_label')) . "*\n\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.common.current_value')) . " `" . TextService::mdv2((string) $current) . "`\n\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.encryption_prompt')),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => 'SSL', 'callback_data' => 'ssl'],
                        ['text' => 'TLS', 'callback_data' => 'tls'],
                        ['text' => Lang::get('gutotradebot::bot.botconfig.email.encryption_none'), 'callback_data' => 'none'],
                    ],
                    [['text' => "✋ " . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), 'callback_data' => '/wizardcancel']],
                ],
            ]),
        ];
    }

    private function stepCert($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;

        if ($text === 'certyes')
            return ['__advance' => true, 'merge' => ['cert' => true]];
        if ($text === 'certno')
            return ['__advance' => true, 'merge' => ['cert' => false]];

        $raw = $state['data']['current']['cert'] ?? null;
        $current = $raw === true ? 'Sí' : ($raw === false ? 'No' : '—');
        return [
            'text' => "📋 *" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.cert_label')) . "*\n\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.common.current_value')) . " `" . TextService::mdv2($current) . "`\n\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.cert_prompt')),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => "✅ " . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.cert_yes')), 'callback_data' => 'certyes'],
                        ['text' => "❌ " . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.cert_no')), 'callback_data' => 'certno'],
                    ],
                    [['text' => "✋ " . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), 'callback_data' => '/wizardcancel']],
                ],
            ]),
        ];
    }

    private function saveEmailConfig($bot, array $state): array
    {
        $d = $state['data'];
        $data = $bot->tenant->data ?? [];

        $data['email'] = [
            'host' => $d['host'],
            'port' => $d['port'],
            'username' => $d['username'],
            'password' => $d['password'],
            'encryption' => $d['encryption'],
            'cert' => $d['cert'],
        ];

        $bot->tenant->data = $data;
        $bot->tenant->save();
        Cache::forget('tenant_' . $bot->tenant->key);

        return [
            'text' => "✅ *" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.saved')) . "*\n\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.field_host')) . " `" . TextService::mdv2((string) $d['host']) . "`\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.field_port')) . " `" . TextService::mdv2((string) $d['port']) . "`\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.field_username')) . " `" . TextService::mdv2((string) $d['username']) . "`\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.field_encryption')) . " `" . TextService::mdv2((string) $d['encryption']) . "`\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.field_cert')) . " `" . ($d['cert'] ? 'Sí' : 'No') . "`",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "⚙️ " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.botconfig')), 'callback_data' => 'botconfigmenu']],
                    [['text' => "↖️ " . Lang::get('telegrambot::bot.options.backtoadminmenu'), 'callback_data' => 'adminmenu']],
                ],
            ]),
        ];
    }

    private function cancelResponse(): array
    {
        return [
            'text' => "✋ *" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.email.cancelled')) . "*",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "⚙️ " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.botconfig')), 'callback_data' => 'botconfigmenu']],
                    [['text' => "↖️ " . Lang::get('telegrambot::bot.options.backtoadminmenu'), 'callback_data' => 'adminmenu']],
                ],
            ]),
        ];
    }

    // ==========================================================================
    // Notifications Menu
    // ==========================================================================

    public function notificationsMenu($bot): array
    {
        $notifs = $bot->tenant->data['notifications'] ?? [];

        $btn = fn(string $langKey, string $path) => [
            'text' => ($this->getNestedFlag($notifs, $path) ? '🟢 ' : '⚫ ') . Lang::get($langKey),
            'callback_data' => 'togglenotif-' . $path,
        ];

        return [
            'text' => "🔔 *" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.notifications.header')) . "*\n\n"
                . "_" . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.notifications.desc')) . "_",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [$btn('gutotradebot::bot.botconfig.notifications.payments_new_fromremesador_togestors', 'payments.new.fromremesador.togestors')],
                    [$btn('gutotradebot::bot.botconfig.notifications.payments_new_fromremesador_tocapitals', 'payments.new.fromremesador.tocapitals')],
                    [$btn('gutotradebot::bot.botconfig.notifications.payments_double_togestors', 'payments.double.togestors')],
                    [$btn('gutotradebot::bot.botconfig.notifications.payments_double_tocapitals', 'payments.double.tocapitals')],
                    [$btn('gutotradebot::bot.botconfig.notifications.payments_new_fromcapital_togestors', 'payments.new.fromcapital.togestors')],
                    [$btn('gutotradebot::bot.botconfig.notifications.payments_new_frombot_togestors', 'payments.new.frombot.togestors')],
                    [$btn('gutotradebot::bot.botconfig.notifications.payments_new_frombot_tocapitals', 'payments.new.frombot.tocapitals')],

                    [$btn('gutotradebot::bot.botconfig.notifications.capitals_new_togestors', 'capitals.new.togestors')],
                    [$btn('gutotradebot::bot.botconfig.notifications.capitals_noenough_tocapitals', 'capitals.noenough.tocapitals')],

                    [$btn('gutotradebot::bot.botconfig.notifications.comments_new_togestors', 'comments.new.togestors')],
                    [$btn('gutotradebot::bot.botconfig.notifications.comments_new_tosupervisors', 'comments.new.tosupervisors')],

                    [['text' => "⚙️ " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.botconfig')), 'callback_data' => 'botconfigmenu']],
                ],
            ]),
        ];
    }

    public function toggleNotification($bot, string $dotPath): array
    {
        $data = $bot->tenant->data ?? [];
        $keys = explode('.', $dotPath);

        $current = $this->getNestedValue($data['notifications'] ?? [], $keys);
        $data['notifications'] = $this->setNestedValue($data['notifications'] ?? [], $keys, $current ? 0 : 1);
        $bot->tenant->data = $data;
        $bot->tenant->save();
        Cache::forget('tenant_' . $bot->tenant->key);

        return $this->notificationsMenu($bot);
    }

    // ==========================================================================
    // Helpers
    // ==========================================================================

    private function textInputPrompt(string $icon, string $label, mixed $current, string $instruction): array
    {
        return [
            'text' => "{$icon} *" . TextService::mdv2($label) . "*\n\n"
                . TextService::mdv2(Lang::get('gutotradebot::bot.botconfig.common.current_value')) . " `" . TextService::mdv2((string) $current) . "`\n\n"
                . TextService::mdv2($instruction),
            'reply_markup' => $this->cancelKeyboard(),
        ];
    }

    private function errorPrompt(string $message): array
    {
        return [
            'text' => "❌ " . TextService::mdv2($message),
            'reply_markup' => $this->cancelKeyboard(),
        ];
    }

    private function cancelKeyboard(): string
    {
        return json_encode([
            'inline_keyboard' => [
                [['text' => "✋ " . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), 'callback_data' => '/wizardcancel']],
            ],
        ]);
    }

    private function getNestedFlag(array $data, string $dotPath): bool
    {
        return (bool) $this->getNestedValue($data, explode('.', $dotPath));
    }

    private function getNestedValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (!is_array($data) || !array_key_exists($key, $data))
                return null;
            $data = $data[$key];
        }
        return $data;
    }

    private function setNestedValue(array $data, array $keys, mixed $value): array
    {
        $key = array_shift($keys);
        $data[$key] = empty($keys) ? $value : $this->setNestedValue($data[$key] ?? [], $keys, $value);
        return $data;
    }
}
