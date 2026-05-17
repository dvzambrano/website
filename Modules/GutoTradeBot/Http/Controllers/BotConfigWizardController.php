<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Illuminate\Support\Facades\Cache;
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
            'text' => "⚙️ *" . TextService::mdv2('Configuración del bot') . "*\n\n"
                . "_" . TextService::mdv2('Selecciona qué configuración deseas editar:') . "_",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '📧 Email',           'callback_data' => 'botconfigemail']],
                    [['text' => '🔔 Notificaciones',  'callback_data' => 'botconfignotifications']],
                    [['text' => '↩️ Menú admin',      'callback_data' => 'adminmenu']],
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
        $self  = $this;
        $steps = [
            ['name' => 'STEP_HOST',       'handler' => fn($b, $s) => $self->stepHost($b, $s)],
            ['name' => 'STEP_PORT',       'handler' => fn($b, $s) => $self->stepPort($b, $s)],
            ['name' => 'STEP_USERNAME',   'handler' => fn($b, $s) => $self->stepUsername($b, $s)],
            ['name' => 'STEP_PASSWORD',   'handler' => fn($b, $s) => $self->stepPassword($b, $s)],
            ['name' => 'STEP_ENCRYPTION', 'handler' => fn($b, $s) => $self->stepEncryption($b, $s)],
            ['name' => 'STEP_CERT',       'handler' => fn($b, $s) => $self->stepCert($b, $s)],
        ];

        return (new WizardController())->run($bot, $steps, [
            'controller'  => static::class,
            'method'      => 'emailWizard',
            'initialData' => ['current' => $bot->tenant->data['email'] ?? []],
            'onComplete'  => fn($b, $s) => $self->saveEmailConfig($b, $s),
            'onCancel'    => fn($b) => $self->cancelResponse(),
        ]);
    }

    private function stepHost($bot, array $state): array
    {
        $text       = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            return ['__advance' => true, 'merge' => ['host' => trim($text)]];
        }

        $current = $state['data']['current']['host'] ?? '—';
        return $this->textInputPrompt(
            '📡', 'Servidor IMAP', $current,
            'Escribe el host del servidor IMAP (ej. imap.hostinger.com):'
        );
    }

    private function stepPort($bot, array $state): array
    {
        $text       = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            if (!is_numeric($text)) {
                return $this->errorPrompt('El puerto debe ser un número. Intenta de nuevo:');
            }
            return ['__advance' => true, 'merge' => ['port' => (int) $text]];
        }

        $current = $state['data']['current']['port'] ?? '—';
        return $this->textInputPrompt(
            '🔌', 'Puerto', $current,
            'Escribe el número de puerto (ej. 993, 143):'
        );
    }

    private function stepUsername($bot, array $state): array
    {
        $text       = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            return ['__advance' => true, 'merge' => ['username' => trim($text)]];
        }

        $current = $state['data']['current']['username'] ?? '—';
        return $this->textInputPrompt(
            '👤', 'Usuario (correo)', $current,
            'Escribe el correo de acceso al buzón:'
        );
    }

    private function stepPassword($bot, array $state): array
    {
        $text       = $bot->message['text'] ?? null;
        $isCallback = ($bot->message['_update_type'] ?? '') === 'callback_query';

        if ($text && !$isCallback) {
            return ['__advance' => true, 'merge' => ['password' => trim($text)]];
        }

        return [
            'text' => "🔐 *" . TextService::mdv2('Contraseña') . "*\n\n"
                . "⚠️ _" . TextService::mdv2('El valor actual no se muestra por seguridad.') . "_\n\n"
                . TextService::mdv2('Escribe la nueva contraseña de acceso:'),
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
            'text' => "🔒 *" . TextService::mdv2('Cifrado') . "*\n\n"
                . TextService::mdv2('Valor actual:') . " `" . TextService::mdv2((string) $current) . "`\n\n"
                . TextService::mdv2('Selecciona el tipo de cifrado:'),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => 'SSL',     'callback_data' => 'ssl'],
                        ['text' => 'TLS',     'callback_data' => 'tls'],
                        ['text' => 'Ninguno', 'callback_data' => 'none'],
                    ],
                    [['text' => '✋ Cancelar', 'callback_data' => '/wizardcancel']],
                ],
            ]),
        ];
    }

    private function stepCert($bot, array $state): array
    {
        $text = $bot->message['text'] ?? null;

        if ($text === 'certyes') return ['__advance' => true, 'merge' => ['cert' => true]];
        if ($text === 'certno')  return ['__advance' => true, 'merge' => ['cert' => false]];

        $raw     = $state['data']['current']['cert'] ?? null;
        $current = $raw === true ? 'Sí' : ($raw === false ? 'No' : '—');
        return [
            'text' => "📋 *" . TextService::mdv2('Verificar certificado SSL') . "*\n\n"
                . TextService::mdv2('Valor actual:') . " `" . TextService::mdv2($current) . "`\n\n"
                . TextService::mdv2('¿Verificar el certificado del servidor?'),
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Sí', 'callback_data' => 'certyes'],
                        ['text' => '❌ No', 'callback_data' => 'certno'],
                    ],
                    [['text' => '✋ Cancelar', 'callback_data' => '/wizardcancel']],
                ],
            ]),
        ];
    }

    private function saveEmailConfig($bot, array $state): array
    {
        $d    = $state['data'];
        $data = $bot->tenant->data ?? [];

        $data['email'] = [
            'host'       => $d['host'],
            'port'       => $d['port'],
            'username'   => $d['username'],
            'password'   => $d['password'],
            'encryption' => $d['encryption'],
            'cert'       => $d['cert'],
        ];

        $bot->tenant->data = $data;
        $bot->tenant->save();

        return [
            'text' => "✅ *" . TextService::mdv2('Configuración de email guardada.') . "*\n\n"
                . "📡 " . TextService::mdv2('Host:')    . " `" . TextService::mdv2((string) $d['host'])       . "`\n"
                . "🔌 " . TextService::mdv2('Puerto:')  . " `" . TextService::mdv2((string) $d['port'])       . "`\n"
                . "👤 " . TextService::mdv2('Usuario:') . " `" . TextService::mdv2((string) $d['username'])   . "`\n"
                . "🔒 " . TextService::mdv2('Cifrado:') . " `" . TextService::mdv2((string) $d['encryption']) . "`\n"
                . "📋 " . TextService::mdv2('Cert:')    . " `" . ($d['cert'] ? 'Sí' : 'No')                   . "`",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '↩️ Config del bot', 'callback_data' => 'botconfigmenu']],
                    [['text' => '↖️ Menú admin',      'callback_data' => 'adminmenu']],
                ],
            ]),
        ];
    }

    private function cancelResponse(): array
    {
        return [
            'text' => "✋ *" . TextService::mdv2('Configuración cancelada.') . "*",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '↩️ Config del bot', 'callback_data' => 'botconfigmenu']],
                    [['text' => '↖️ Menú admin',      'callback_data' => 'adminmenu']],
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

        $btn = fn(string $label, string $path) => [
            'text'          => ($this->getNestedFlag($notifs, $path) ? '🟢 ' : '⚫ ') . $label,
            'callback_data' => 'togglenotif-' . $path,
        ];

        return [
            'text' => "🔔 *" . TextService::mdv2('Notificaciones') . "*\n\n"
                . "_" . TextService::mdv2('Toca un botón para activar o desactivar la notificación.') . "_",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '— 💰 Capitales —',    'callback_data' => 'botconfignotifications']],
                    [$btn('Nuevo capital → gestores',         'capitals.new.togestors')],
                    [$btn('Capital insuficiente → capitales', 'capitals.noenough.tocapitals')],

                    [['text' => '— 💬 Comentarios —',   'callback_data' => 'botconfignotifications']],
                    [$btn('Nuevo comentario → gestores',     'comments.new.togestors')],
                    [$btn('Nuevo comentario → supervisores', 'comments.new.tosupervisors')],

                    [['text' => '— 💶 Pagos —',          'callback_data' => 'botconfignotifications']],
                    [$btn('Pago nuevo (bot) → gestores',        'payments.new.frombot.togestors')],
                    [$btn('Pago nuevo (bot) → capitales',       'payments.new.frombot.tocapitals')],
                    [$btn('Pago nuevo (capital) → gestores',    'payments.new.fromcapital.togestors')],
                    [$btn('Pago nuevo (remesador) → gestores',  'payments.new.fromremesador.togestors')],
                    [$btn('Pago nuevo (remesador) → capitales', 'payments.new.fromremesador.tocapitals')],
                    [$btn('Pago duplicado → gestores',          'payments.double.togestors')],
                    [$btn('Pago duplicado → capitales',         'payments.double.tocapitals')],

                    [['text' => '↩️ Config del bot', 'callback_data' => 'botconfigmenu']],
                ],
            ]),
        ];
    }

    public function toggleNotification($bot, string $dotPath): array
    {
        $data = $bot->tenant->data ?? [];
        $keys = explode('.', $dotPath);

        $current                 = $this->getNestedValue($data['notifications'] ?? [], $keys);
        $data['notifications']   = $this->setNestedValue($data['notifications'] ?? [], $keys, $current ? 0 : 1);
        $bot->tenant->data       = $data;
        $bot->tenant->save();

        return $this->notificationsMenu($bot);
    }

    // ==========================================================================
    // Helpers
    // ==========================================================================

    private function textInputPrompt(string $icon, string $label, mixed $current, string $instruction): array
    {
        return [
            'text' => "{$icon} *" . TextService::mdv2($label) . "*\n\n"
                . TextService::mdv2('Valor actual:') . " `" . TextService::mdv2((string) $current) . "`\n\n"
                . TextService::mdv2($instruction),
            'reply_markup' => $this->cancelKeyboard(),
        ];
    }

    private function errorPrompt(string $message): array
    {
        return [
            'text'         => "❌ " . TextService::mdv2($message),
            'reply_markup' => $this->cancelKeyboard(),
        ];
    }

    private function cancelKeyboard(): string
    {
        return json_encode([
            'inline_keyboard' => [
                [['text' => '✋ Cancelar', 'callback_data' => '/wizardcancel']],
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
            if (!is_array($data) || !array_key_exists($key, $data)) return null;
            $data = $data[$key];
        }
        return $data;
    }

    private function setNestedValue(array $data, array $keys, mixed $value): array
    {
        $key        = array_shift($keys);
        $data[$key] = empty($keys) ? $value : $this->setNestedValue($data[$key] ?? [], $keys, $value);
        return $data;
    }
}
