<?php

namespace Modules\ZentroPackageBot\Http\Controllers;

use App\Http\Controllers\JsonsController;
use Modules\TelegramBot\Traits\UsesTelegramBot;
use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Lang;
use Modules\ZentroPackageBot\Entities\Packages;
use Modules\ZentroPackageBot\Entities\Histories;
use Illuminate\Support\Facades\Log;

class ZentroPackageBotController extends JsonsController
{
    use UsesTelegramBot;


    public function __construct()
    {
        $this->tenant = app('active_bot');

        $this->ActorsController = new ActorsController();
        $this->TelegramController = new TelegramController();
    }

    public function processMessage()
    {
        $array = $this->getCommand($this->message["text"]);
        $this->strategies["/password"] =
            function () use ($array) {
                $key = strtolower($array["message"]);
                $demo = false;
                return array(
                    "text" =>
                        "🔐 *" . strtoupper($key) . " hash:*\n",
                );
            };

        if (isset($this->message['web_app_data'])) {
            $array = $this->message['web_app_data'];
            $this->strategies["/webappdata"] =
                function () use ($array) {
                    $text = "❌";
                    // Buscamos en la base de datos (donde el Seeder ya insertó este AWB)
                    $package = Packages::where('awb', $array["data"]["code"])
                        ->orWhere('tracking_number', $array["data"]["code"])
                        ->first();
                    if ($package)
                        $text = "👤 " . $array["data"]["user_id"] . "\n" .
                            "✅ *Carga Internacional Detectada*\n\n" .
                            "📦 *Item:* {$package->description}\n" .
                            "✈️ *AWB:* {$package->awb}\n" .
                            "📍 *Destino:* {$package->province} (SCU)\n" .
                            "⚖️ *Peso:* {$package->weight_kg} kg";

                    return array(
                        "text" => $text,
                    );
                };
        }

        //$this->message['web_app_data']

        return $this->getProcessedMessage();
    }

    public function mainMenu($actor)
    {
        $tenant = app('active_bot');

        $menu = array();

        $url = route('telegram-scanner-init', array(
            "gpsrequired" => config('metadata.system.app.zentropackagebot.scanner.gpsrequired'),
            "botname" => $tenant->code
        ));

        array_push($menu, [
            [
                "text" => "📷 Abrir Escáner",
                'web_app' => ['url' => $url]
            ],
        ]);

        return $this->getMainMenu(
            $actor,
            $menu
        );
    }

    public function configMenu($actor)
    {
        return $this->getConfigMenu(
            $actor
        );
    }

    public function afterScan($user_id, $codes)
    {
        Log::info("User {$user_id} scanned: " . json_encode($codes));

        $completed = 0;
        $summarytext = "";
        foreach ($codes as $array) {
            /*
            {
            "code":"9234690371245905087855-2",
            "date":"2026-01-30T17:43:03.879Z",
            "location":{"lat":20.8752058,"lng":-76.2653569,"acc":18.597000122070312}
            }
            */
            // 1. Buscamos todos los posibles candidatos
            $packages = Packages::where('awb', $array["code"])
                ->orWhere('tracking_number', $array["code"])
                ->orWhere('internal_ref', $array["code"])
                ->get();

            // no hay resultados para este codigo
            if ($packages->isEmpty()) {
                TelegramController::sendMessage(array(
                    "message" => array(
                        "text" => "❌ No se encontró ningún paquete `" . $array["code"] . "`.",
                        "chat" => array(
                            "id" => $user_id,
                        ),
                    ),
                ), $this->tenant->token);
            } else {
                // CASO A: Solo hay uno (Perfecto)
                if ($packages->count() === 1) {
                    $package = $packages->first();

                    // se crea el history escaneado
                    Histories::create([
                        'package_id' => $package->id,
                        'location' => json_encode($array["location"]),
                        'status' => $package->status,
                        'comment' => $array["date"],
                        'user_id' => $user_id,
                    ]);
                    $completed++;
                    $summarytext .=
                        "📦 *Item:* " . $array["code"] . "\n" .
                        "🆔 *Ref:* `" . $package->fingerprint . "`\n\n";

                } else {
                    // CASO B: Hay varios (Conflicto de duplicados)
                    $text = "⚠️ *Se encontraron {$packages->count()} coincidencias:*\n\n";
                    $text .= "Por favor, verifica el destinatario:\n";

                    foreach ($packages as $index => $pkg) {
                        $n = $index + 1;
                        $text .= "{$n}. {$pkg->recipient_name} ({$pkg->weight_kg} Kg)\n";
                    }

                    TelegramController::sendMessage(array(
                        "message" => array(
                            "text" => $text,
                            "chat" => array(
                                "id" => $user_id,
                            ),
                        ),
                    ), $this->tenant->token);

                }

            }

        }

        if ($completed > 0) {
            TelegramController::sendMessage(array(
                "message" => array(
                    "text" => $summarytext,
                    "chat" => array(
                        "id" => $user_id,
                    ),
                ),
            ), $this->tenant->token);
        }

        // es obligatoria enviar la respuesta para q la UI sepa q se recibio la info
        return response()->json(['success' => true]);
    }

}
