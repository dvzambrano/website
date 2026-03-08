<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Contracts\BlockchainProviderInterface;
use Illuminate\Http\Request;

class MoralisController extends Controller implements BlockchainProviderInterface
{
    protected $apiKey;
    protected $apiSecret;
    protected $apiBaseUrl;
    protected $gatewayBaseUrl;
    protected $environment;

    public function __construct()
    {
        parent::__construct();

        $this->apiKey = config("metadata.system.app.zentrotraderbot.ramp.apikey");
        $this->apiSecret = config("metadata.system.app.zentrotraderbot.ramp.apisecret");

        $this->environment = config("metadata.system.app.zentrotraderbot.ramp.environment");

        $this->apiBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".api");
        $this->gatewayBaseUrl = config("metadata.system.app.zentrotraderbot.ramp.urls." . $this->environment . ".gateway");
    }

    public function processWebhook(Request $request): array
    {
        $array = array();
        $payload = $request->all();
        // Logueamos siempre para auditoría interna
        Log::debug("🐞 MoralisController processWebhook: Evento recibido " . json_encode($payload));

        return $array;
    }
}