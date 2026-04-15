<?php

namespace Modules\ZentroOwnerBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class ServicesController extends Controller
{
    public function processWebhook($request)
    {
        $text = $request["service"] ?? "nada";
        //{ "service": "gmail", "seed": "apple-river-stone-cloud-flame-tiger" }
        Log::debug("🐞 ServicesController webhook", [
            "seed" => $request["seed"],
            "service" => $request["service"],
        ]);

        // Se espera: { "password": "xK9#mP2!qR5" }
        return response()->json(["password" => "xK9#mP2!qR5-" . $text], 200);
    }
}