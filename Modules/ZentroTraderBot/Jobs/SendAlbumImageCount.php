<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\BehaviorService;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Entities\Offers;

/**
 * Envía un único mensaje de confirmación con el conteo real de imágenes de un album.
 * ShouldBeUnique garantiza que aunque múltiples webhooks simultáneos despachen este
 * job (uno por foto del album), solo se encola y ejecuta UNO por grupo de album.
 */
class SendAlbumImageCount implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $tenantKey,
        protected string $offerCode,
        protected int    $userId,
        protected string $mediaGroupId,
        protected string $dataField,    // 'proofs' or 'evidence'
        protected string $moreCallback, // 'proofmore' or 'evimore'
        protected string $doneCallback, // 'proofdone' or 'evidone'
        protected string $langPrefix,   // e.g. 'zentrotraderbot::bot.proof_wizard'
    ) {}

    public function uniqueId(): string
    {
        return "{$this->tenantKey}_{$this->offerCode}_{$this->userId}_{$this->mediaGroupId}";
    }

    public function handle(): void
    {
        $bot = BehaviorService::cache("tenant_{$this->tenantKey}", function () {
            return TelegramBots::where('key', $this->tenantKey)->first();
        });
        if (!$bot) return;
        $bot->connectToThisTenant();

        $offer = Offers::findByCode($this->offerCode);
        if (!$offer) return;

        $data   = $offer->data ?? [];
        $images = $this->dataField === 'proofs'
            ? ($data['proofs'][$this->userId] ?? [])
            : ($data['evidence'][(string) $this->userId] ?? []);

        $count = count($images);
        if ($count === 0) return;

        TelegramController::sendMessage([
            "message" => [
                "text" => "✅ " . Lang::get("{$this->langPrefix}.image_received", ['count' => $count])
                    . "\n\n❓ " . Lang::get("{$this->langPrefix}.ask_more"),
                "chat"         => ["id" => $this->userId],
                "reply_markup" => json_encode([
                    "inline_keyboard" => [[
                        ["text" => Lang::get("{$this->langPrefix}.yes_more"), "callback_data" => "{$this->moreCallback} {$this->offerCode}"],
                        ["text" => Lang::get("{$this->langPrefix}.no_done"),  "callback_data" => "{$this->doneCallback} {$this->offerCode}"],
                    ]],
                ]),
            ],
        ], $bot->token);
    }
}
