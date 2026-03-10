<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Web3\Http\Controllers\MoralisController;
use Illuminate\Support\Facades\Log;

class MoralisAddAddressToStream implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $stream_id;
    protected $api_key;
    protected $address;

    public function __construct($webhookId, $authToken, $addressToAdd)
    {
        $this->stream_id = $webhookId;
        $this->api_key = $authToken;
        $this->address = $addressToAdd;
    }

    public function handle()
    {
        $response = MoralisController::addAddressToStream(
            $this->stream_id,
            $this->api_key,
            $this->address
        );
        if ($response->successful())
            Log::debug("🐞 MoralisAddAddressToStream handle", [
                "stream_id" => $this->stream_id,
                "address" => $this->address,
            ]);
        else
            Log::error('🆘 MoralisAddAddressToStream handle error', [
                "stream_id" => $this->stream_id,
                "api_key" => $this->api_key,
                "address" => $this->address,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
    }
}