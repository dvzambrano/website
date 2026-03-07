<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Web3\Http\Controllers\AlchemyController;
use Illuminate\Support\Facades\Log;

class AlchemyUpdateWebhookAddresses implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $webhook_id;
    protected $auth_token;
    protected $addreses_to_add;
    protected $addreses_to_remove;

    public function __construct($webhookId, $authToken, $addressesToAdd = [], $addressesToRemove = [])
    {
        $this->webhook_id = $webhookId;
        $this->auth_token = $authToken;
        $this->addreses_to_add = $addressesToAdd;
        $this->addreses_to_remove = $addressesToRemove;
    }

    public function handle()
    {
        $response = AlchemyController::updateWebhookAddresses(
            $this->webhook_id,
            $this->auth_token,
            $this->addreses_to_add,
            $this->addreses_to_remove
        );
        if ($response->successful())
            Log::debug("🐞 AlchemyUpdateWebhookAddresses handle webhook_id=" . $this->webhook_id . ", addreses_to_add: " .
                json_encode($this->addreses_to_add) . ", addreses_to_remove: " . json_encode($this->addreses_to_remove));
        else
            Log::error('🆘 AlchemyUpdateWebhookAddresses handle error', [
                'webhook_id' => $this->webhook_id,
                'addreses_to_add' => $this->addreses_to_add,
                'addreses_to_remove' => $this->addreses_to_remove,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
    }
}