<?php

namespace Modules\TelegramBot\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Modules\Laravel\Http\Controllers\Controller;

class WizardController extends Controller
{
    /**
     * Run a multi-step wizard.
     *
     * @param object $bot    Bot context (actor, tenant, message)
     * @param array  $steps  [['name' => 'STEP_X', 'handler' => callable], ...]
     *
     *   Handler signature: fn($bot, array $state): array
     *   - Return ['__advance' => true, 'merge' => [...]] to consume input and advance
     *   - Return a response array (with 'text' key) to display a prompt or error
     *
     * @param array $options
     *   - controller   (string)   FQCN of the calling controller — required for routing
     *   - method       (string)   Method name of the calling entry point — required for routing
     *   - initialData  (array)    Initial state data merged into wizard start
     *   - cachePrefix  (string)   Cache key prefix, default 'wizard'
     *   - onComplete   (callable) fn($bot, $state): mixed — called after last step advances
     *   - onCancel     (callable) fn($bot): mixed         — called on /wizardcancel
     *
     * @return mixed
     */
    public function run($bot, array $steps, array $options = []): mixed
    {
        $text = $bot->message['text'] ?? null;
        $userId = $bot->actor->user_id;
        $prefix = $options['cachePrefix'] ?? 'wizard';
        $cacheKey = "{$prefix}_{$bot->tenant->key}_{$userId}";

        $state = Cache::get($cacheKey, [
            'controller' => $options['controller'] ?? null,
            'method'     => $options['method'] ?? null,
            'step'       => 'START',
            'data'       => $options['initialData'] ?? [],
            'history'    => [],
        ]);

        // --- CANCEL ---
        if ($text === '/wizardcancel') {
            Cache::forget($cacheKey);
            if (isset($options['onCancel'])) {
                return ($options['onCancel'])($bot);
            }
            return [
                'text' => '❌ Wizard cancelled.',
                'chat' => ['id' => $userId],
                'editprevious' => (isset($bot->callback_query) || ($bot->is_callback ?? false)) ? 1 : 0,
            ];
        }

        // --- PREVIOUS ---
        if ($text === '/wizardprevious' && !empty($state['history'])) {
            $last = array_pop($state['history']);
            $state['step'] = $last['step'];
            $state['data'] = $last['data'];
            Cache::forever($cacheKey, $state);
            $bot->message['text'] = null;
            return $this->run($bot, $steps, $options);
        }

        // --- START: transition to first step ---
        if ($state['step'] === 'START') {
            $state['step'] = $steps[0]['name'];
            Cache::forever($cacheKey, $state);
        }

        // --- Find current step index ---
        $currentStepName = $state['step'];
        $currentStepIndex = null;
        foreach ($steps as $i => $step) {
            if ($step['name'] === $currentStepName) {
                $currentStepIndex = $i;
                break;
            }
        }

        if ($currentStepIndex === null) {
            Cache::forget($cacheKey);
            return null;
        }

        // Capture data before handler so history snapshot is pre-input
        $dataBefore = $state['data'];

        // --- Execute step handler ---
        $result = ($steps[$currentStepIndex]['handler'])($bot, $state);

        // Handler signals advance
        if (is_array($result) && ($result['__advance'] ?? false)) {
            $state['data'] = array_merge($state['data'], $result['merge'] ?? []);
            $state['history'][] = ['step' => $currentStepName, 'data' => $dataBefore];

            $nextIndex = $currentStepIndex + 1;

            if (isset($steps[$nextIndex])) {
                $state['step'] = $steps[$nextIndex]['name'];
                Cache::forever($cacheKey, $state);
                $bot->message['text'] = null;
                return $this->run($bot, $steps, $options);
            }

            // All steps complete
            Cache::forget($cacheKey);
            if (isset($options['onComplete'])) {
                return ($options['onComplete'])($bot, $state);
            }
            return null;
        }

        // Handler returned a UI response — persist and return
        Cache::forever($cacheKey, $state);
        return $result;
    }
}
