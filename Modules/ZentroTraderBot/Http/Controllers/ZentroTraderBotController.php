<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Entities\Metadatas;
use Modules\Laravel\Http\Controllers\TextController;
use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Laravel\Http\Controllers\JsonsController;
use Modules\TelegramBot\Traits\UsesTelegramBot;
use Illuminate\Support\Facades\Lang;
use Modules\Web3\Http\Controllers\ZeroExController;
use Illuminate\Support\Facades\Log;
use Modules\Web3\Http\Controllers\ChainidController;
use Modules\ZentroTraderBot\Jobs\AlchemyUpdateWebhookAddresses;
use Modules\ZentroTraderBot\Jobs\MoralisAddAddressToStream;
use Modules\Web3\Services\ConfigService;
use Modules\ZentroTraderBot\Http\Controllers\BlockchainController;
use Modules\Laravel\Http\Controllers\LaravelController;

class ZentroTraderBotController extends JsonsController
{
    use UsesTelegramBot;
    public $AgentsController;
    public $engine;

    public function __construct()
    {
        $this->tenant = app('active_bot');

        $this->ActorsController = new ActorsController();
        $this->TelegramController = new TelegramController();
        $this->AgentsController = new AgentsController();
        $this->engine = new ZeroExController();
    }

    public function processMessage()
    {
        // Configurando idioma segun la interfaz de Telegram del usuario -------------------------------
        // Telegram envía el idioma en: message.from.language_code
        $langCode = 'es';
        if (isset($this->message["from"]) && isset($this->message["from"]['language_code']))
            $langCode = $this->message["from"]["language_code"];  // 'es', 'en', 'pt-br', 'fr'
        // Limpiamos el código (por si viene 'pt-BR', dejarlo en 'pt')
        $locale = substr($langCode, 0, 2);
        // Validamos que tengamos esa traducción, si no, default a español
        $availableLocales = LaravelController::getAvailableLanguages($this->tenant->module);
        if (!in_array($locale, $availableLocales)) {
            $locale = 'es';
        }
        // Establecemos el idioma para toda la ejecución de Laravel
        app()->setLocale($locale);
        /*
        // Tambien podria cogerse de la configuracion del usuario al procesar el mensaje:
        $user = Suscriptions::where('user_id', $telegramId)->first();
        $locale = $user->language ?? substr($payload['message']['from']['language_code'], 0, 2);
        app()->setLocale($locale);
        */

        // Analizando comando recibido ----------------------------------------------------
        $array = $this->getCommand($this->message["text"]);
        $suscriptor = Suscriptions::where("user_id", $this->actor->user_id)->first();
        if (!$suscriptor) {
            $suscriptor = new Suscriptions($this->actor->toArray());
            $suscriptor->save();
        }


        // Estrategias a utilizar para respuesta ------------------------------------------
        $this->strategies["/start"] = $this->strategies["start"] =
            function () use ($suscriptor, $array) {
                $wallet = $suscriptor->getWallet();
                if (strtolower($wallet["status"]) == "created") {
                    // Es necesario recargarlo porq en el getWallet se actualizaron datos!!
                    $suscriptor = Suscriptions::where("user_id", $this->actor->user_id)->first();
                    $data = $suscriptor->data;
                    $data["wallet"]["status"] = "suscribed";
                    $data["wallet"]["suscribed_at"] = now()->toIso8601String();
                    $suscriptor->data = $data;
                    $suscriptor->save();

                    // notificando a aministradores de nuevo usuario sin rol
                    $menu = $this->AgentsController->getRoleMenu($this->actor->user_id, 0);
                    array_push($menu["menu"], [["text" => "❌ " . Lang::get("telegrambot::bot.options.delete"), "callback_data" => "confirmation|deleteuser-{$this->actor->user_id}|menu"]]);
                    $this->notifyUserWithNoRole($this->actor->user_id, $menu);

                    // Registrar la wallet en el webhook de Moralis
                    MoralisAddAddressToStream::dispatch(
                        $this->tenant->data["moralis_stream_id"],
                        env("MORALIS_API_KEY"),
                        $wallet["address"]
                    )->delay(now()->addSeconds(10));

                    // Registrar la wallet en el webhook de Alchemy
                    $authToken = config('zentrotraderbot.alchemy_auth_token');
                    AlchemyUpdateWebhookAddresses::dispatch(
                        $this->tenant->data["alchemy_webhook_id"],
                        $authToken,
                        [$wallet["address"]]
                    )->delay(now()->addSeconds(10));
                }

                if (env("DEBUG_MODE", false))
                    Log::debug("🐞 ZentroTraderBotController processMessage /start:", [
                        "array" => $array,
                        "wallet" => $wallet,
                    ]);


                $reply = [
                    "text" => "",
                ];
                if ($array["message"] == "")
                    $reply = $this->mainMenu($this->actor);
                else {
                    if (str_starts_with($array["message"], 'offer_')) {
                        $code = str_replace('offer_', '', $array["message"]);
                        $controller = new OffersController();
                        $reply = $controller->showOffer($this, $code);
                    }
                }
                return $reply;
            };

        $this->strategies["suscribemenu"] = function () use ($suscriptor) {
            return $this->suscribeMenu($suscriptor);
        };
        $this->strategies["suscribelevel0"] =
            $this->strategies["suscribelevel1"] =
            $this->strategies["suscribelevel2"] =
            function () use ($suscriptor, $array) {
                return $this->suscribeMenu(
                    $suscriptor,
                    str_replace("suscribelevel", "", strtolower($array["command"]))
                );
            };

        $this->strategies["actionmenu"] =
            function () {
                if ($this->actor->isLevel(1, $this->tenant->code))
                    return $this->actionMenu();
                return $this->mainMenu($this->actor);
            };
        $this->strategies["actionlevel1"] =
            $this->strategies["actionlevel2"] =
            function () use ($array) {
                return $this->actionMenu(
                    str_replace("actionlevel", "", strtolower($array["command"]))
                );
            };

        $this->strategies["clienturl"] =
            function () {
                $reply = [
                    "text" => "",
                ];

                $uri = str_replace("telegram/bot/ZentroTraderBot", "tradingview/client/{$this->actor->user_id}", request()->fullUrl());
                $reply["text"] = "🌎 " . Lang::get("zentrotraderbot::bot.prompts.clienturl.header") . ":\n{$uri}\n\n👆 " . Lang::get("zentrotraderbot::bot.prompts.clienturl.warning") . ".";
                $reply["reply_markup"] = json_encode([
                    "inline_keyboard" => [
                        [
                            ["text" => "🔙 " . Lang::get("zentrotraderbot::bot.options.backtosuscribemenu"), "callback_data" => "suscribemenu"],
                        ],
                    ],
                ]);

                return $reply;
            };

        // /swap 5 POL DAI
        $this->strategies["/swap"] =
            function () use ($array) {
                if (env("DEBUG_MODE", false))
                    Log::debug("🐞 ZentroTraderBotController /swap:" . json_encode($array));

                $key = env('BASE_NETWORK');

                $network = ChainidController::getNetworkData();


                $wc = new TraderWalletController();
                $privateKey = $wc->getDecryptedPrivateKey($this->actor->user_id);
                $amount = $array["pieces"][1];     // Cantidad a vender (Empieza suave, ej. 2 POL)
                $from = $array["pieces"][2];   // Token que vendes
                $to = $array["pieces"][3];  // Token que compras
    
                $userId = $this->actor->user_id;
                $array = $this->engine->swap(
                    $network[$key]["chainId"],
                    $from,
                    $to,
                    $amount,
                    $privateKey,
                    config('zentrotraderbot.0x_api_key'),
                    config('zentrotraderbot.0x_treasury_wallet'),
                    config('zentrotraderbot.0x_swap_fee_percentage'),
                    function ($text, $autodestroy) use ($userId) {
                        TelegramController::sendMessage(
                            array(
                                "message" => array(
                                    "text" => $text,
                                    "chat" => array(
                                        "id" => $userId,
                                    )
                                ),
                            ),
                            $this->tenant->token,
                            $autodestroy
                        );
                    },
                );
                if (env("DEBUG_MODE", false))
                    Log::debug("🐞 ZentroTraderBotController /swap:" . json_encode($array));
                $explorer = $network[$key]["explorers"][0]["url"] . "/tx/" . $array["tx_hash"];
                $reply = array(
                    "text" => "✅ " . Lang::get("zentrotraderbot::bot.prompts.txsuccess") . ": " . $explorer,
                );

                return $reply;
            };

        // /balance POL
        $this->strategies["/balance"] =
            function () use ($array, $suscriptor) {
                $textController = new TextController();
                $walletController = new TraderWalletController();
                $balance = 0;
                $transactions = [];
                try {
                    // 3. Obtener Balance REAL (específicamente de BASE_TOKEN en Polygon)
                    $balance = $walletController->getBalance($suscriptor);
                    // 4. Obtener Transacciones 
                    $limit = 5;
                    $transactions = $walletController->getRecentTransactions($suscriptor, $limit);
                } catch (\Throwable $th) {
                    //throw $th;
                }

                // 2. Definimos el ancho total de la línea (ejemplo: 45 caracteres)
                $totalWidth = 45;

                $message = "💵 *" . Lang::get("zentrotraderbot::bot.prompts.balance.available") . "*:\n";
                $date = $suscriptor->actor->getLocalDateTime(date("Y-m-d H:i:s"), $this->tenant->code, "Y-m-d h:i a");
                $message .= $textController->getDots($totalWidth, $date, number_format($balance, 2) . " USD") . "\n\n";

                $message .= "⏱️ *" . Lang::get("zentrotraderbot::bot.prompts.balance.lastoperations") . "*:\n";
                foreach ($transactions as $tx) {
                    // 1. Formateamos la fecha y el monto
                    $date = $suscriptor->actor->getLocalDateTime($tx['timestamp'], $this->tenant->code, "Y-m-d h:i a");
                    $amount = ($tx['amount'] > 0 ? '+' : '') . number_format($tx['amount'], 2) . " USD";

                    $message .= $textController->getDots($totalWidth, $date, $amount) . "\n";
                }

                $reply = [
                    "text" => $message,
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                        ],
                    ]),
                ];

                return $reply;
            };

        // /withdraw POL 0x1aafFCaB3CB8Ec9b207b191C1b2e2EC662486666
        // /withdraw 5 POL 0x1aafFCaB3CB8Ec9b207b191C1b2e2EC662486666
        $this->strategies["/withdraw"] =
            function () use ($array) {
                $reply = [
                    "text" => "",
                ];

                $wc = new TraderWalletController();


                try {
                    $pieces = $array["pieces"];
                    $count = count($pieces);
                    $tokenSymbol = $pieces[$count - 1];
                    $toAddress = $pieces[$count - 2];
                    $amount = null;
                    if ($count == 4) {
                        $amount = $pieces[1];
                    }
                    $result = $wc->withdraw($this->actor->user_id, $toAddress, $tokenSymbol, $amount);

                    if (isset($result["explorer"]))
                        $reply = array(
                            "text" => "✅ " . Lang::get("zentrotraderbot::bot.prompts.txsuccess") . ": " . $result["explorer"],
                        );

                    if (isset($result["message"]))
                        $reply = array(
                            "text" => "❌ " . Lang::get("zentrotraderbot::bot.prompts.txfail") . ": " . $result["message"],
                        );

                } catch (\Exception $e) {
                    $reply = array(
                        "text" => "❌ " . Lang::get("telegrambot::bot.errors.header") . ": " . $e->getMessage(),
                    );
                }

                return $reply;
            };


        $this->strategies["/wallet"] = $this->strategies["wallet"] =
            function () use ($suscriptor) {
                $address = $suscriptor->getWallet()["address"];
                $data = "ethereum:" . $address;

                $network = ConfigService::getActiveNetwork();
                $token = ConfigService::getToken(env('BASE_TOKEN'), env('BASE_NETWORK'));

                $text =
                    "👇 *" . Lang::get("zentrotraderbot::bot.prompts.topup.cripto.header") . "*: \n" .
                    "`{$address}`\n\n" .
                    "🚨 *" . Lang::get("zentrotraderbot::bot.prompts.topup.cripto.line1", [
                                "token" => $token["symbol"],
                                "network" => $network["chain"]
                            ]) . "*:\n" .
                    "👉 _" . Lang::get("zentrotraderbot::bot.prompts.topup.cripto.line2", [
                                "token" => $token["symbol"],
                                "network" => $network["chain"]
                            ]) . "\n" .
                    "🙇🏻 " . Lang::get("zentrotraderbot::bot.prompts.topup.cripto.line3", [
                                "token" => $token["symbol"],
                            ]) . "_\n";


                $reply = [
                    "text" => $text,
                    "photo" => "https://quickchart.io/qr?text={$data}&size=220",
                    "chat" => array(
                        "id" => $suscriptor->user_id,
                    ),
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                [
                                    "text" => "🪢 " . Lang::get("zentrotraderbot::bot.prompts.topup.cripto.options.debridge"),
                                    "url" => route('zentrotraderbot.pay', array(
                                        "user" => $this->actor->data["telegram"]["username"],
                                    ))
                                ]
                            ],
                            [
                                ["text" => "🔑 " . Lang::get("zentrotraderbot::bot.prompts.topup.cripto.options.seedphrase"), "callback_data" => "showseedphraseconfirmation|showseedphrase|wallet"]
                            ],
                            [["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"]]
                        ],
                    ]),
                ];

                return $reply;
            };


        $this->strategies["showseedphrase"] =
            function () use ($suscriptor) {
                $key = $suscriptor->data['wallet']['seed_phrase'];
                // 🔓 Desencriptamos manualmente
                $data = decryptValue($key);
                $words = explode(' ', $data);
                $message = "```\n";
                for ($i = 0; $i < count($words); $i += 2) {
                    $p1 = str_pad(sprintf("%02d: %s", $i + 1, $words[$i]), 13);
                    $p2 = str_pad(sprintf("%02d: %s", $i + 2, $words[$i + 1]), 13);
                    $message .= "{$p1} {$p2}\n";
                }
                $message .= "```";
                $autodestroy = 1; // se elimina en 1 minuto
                $reply = [
                    "text" =>
                        "👇 *" . Lang::get("zentrotraderbot::bot.prompts.seedphrase.export.line1", [
                            "count" => count($words),
                        ]) . "*: \n" .
                        "{$message}\n" .
                        "📋 _" . Lang::get("zentrotraderbot::bot.prompts.seedphrase.export.line2") . "_\n" .
                        "⌛️ _" . Lang::choice('zentrotraderbot::bot.prompts.seedphrase.export.destroy.mins', $autodestroy, ['count' => $autodestroy]) . "_\n",
                    "photo" => "https://quickchart.io/qr?text={$data}&size=220",
                    "chat" => array(
                        "id" => $suscriptor->user_id,
                    ),
                    "autodestroy" => 1
                ];

                return $reply;
            };


        $this->strategies["showseedphraseconfirmation"] =
            function () use ($array) {
                $reply = $this->getAreYouSurePrompt(
                    $array["pieces"][1],
                    $array["pieces"][2],
                    "\n🚨 " . Lang::get("zentrotraderbot::bot.prompts.seedphrase.warning.line1") . "❗️\n" .
                    "🆘 " . Lang::get("zentrotraderbot::bot.prompts.seedphrase.warning.line2") . ":\n\n" .
                    "⚠️ _" . Lang::get("zentrotraderbot::bot.prompts.seedphrase.warning.line3") . "_\n",
                    false
                );
                return $reply;
            };

        $this->strategies["/network"] =
            function () {
                $reply = $this->notifyNetworkStatus();
                return $reply;
            };

        $this->strategies["/p2pbuy"] =
            function () {
                $controller = new OffersController();
                $reply = $controller->buy($this);
                return $reply;
            };

        $this->strategies["/p2psell"] =
            function () {
                $controller = new OffersController();
                $reply = $controller->sell($this);
                return $reply;
            };

        $this->strategies["/rateoffer"] =
            function () use ($array) {
                $controller = new OffersController();
                $reply = $controller->rateOfferPerformance($this, $array["pieces"][1], $array["pieces"][2]);
                return $reply;
            };

        return $this->getProcessedMessage();
    }

    public function mainMenu($actor)
    {
        $tenant = app('active_bot');

        $suscriptor = Suscriptions::where("user_id", $this->actor->user_id)->first();
        $wallet = $suscriptor->data["wallet"];
        $description = "";
        if (isset($wallet["address"])) {
            //$description = "_" . Lang::get("zentrotraderbot::bot.mainmenu.description") . ":_\n🫆 `" . $wallet["address"] . "`\n\n";
            $description = "_" . Lang::get("zentrotraderbot::bot.mainmenu.description") . ":_\n\n" .
                Lang::get("zentrotraderbot::bot.mainmenu.body") . "\n\n";
        }


        $menu = [];

        array_push($menu, [
            ["text" => "💵 " . Lang::get("zentrotraderbot::bot.options.balance"), "callback_data" => "/balance"],
        ]);

        if (env("P2P_ENABLED", true))
            array_push($menu, [
                ["text" => "🛒 " . Lang::get("zentrotraderbot::bot.options.buyoffer"), "callback_data" => "/p2pbuy"],
                ["text" => "💰 " . Lang::get("zentrotraderbot::bot.options.selloffer"), "callback_data" => "/p2psell"],
            ]);

        array_push($menu, [
            [
                "text" => "🫰 " . Lang::get("zentrotraderbot::bot.options.topupcripto"),
                "callback_data" => "/wallet"
            ]
        ]);

        if (env("RAMP_ENABLED", false))
            array_push($menu, [
                [
                    "text" => "💳 " . Lang::get("zentrotraderbot::bot.options.topupramp"),
                    "url" => route('ramp-redirect', array(
                        "action" => "buy",
                        "key" => $tenant->key,
                        "secret" => $tenant->secret,
                        "user_id" => $actor->user_id
                    ))
                ],
                [
                    "text" => "💲 " . Lang::get("zentrotraderbot::bot.options.withdraw"),
                    "url" => route('ramp-redirect', array(
                        "action" => "sell",
                        "key" => $tenant->key,
                        "secret" => $tenant->secret,
                        "user_id" => $actor->user_id
                    ))
                ]
            ]);


        return $this->getMainMenu(
            $suscriptor,
            $menu,
            $description
        );
    }

    public function adminMenu($suscriptor)
    {
        $menu = [];
        array_push($menu, [
            ["text" => "🫡 " . Lang::get("zentrotraderbot::bot.options.actionmenu"), "callback_data" => "suscribemenu"]
        ]);


        return $this->getAdminMenu(
            $suscriptor,
            $menu
        );
    }

    public function actionMenu($action = -1)
    {
        if ($action > -1) {
            $item = Metadatas::where("name", "=", "app_zentrotraderbot_tradingview_alert_action_level")->first();
            $item->value = $action;
            $item->save();
        }

        /*
        Acciones a realizar al recibir alerta desde TradingView [1: alertar en canal, 2: alertar y ejecutar ordenes en DEX]
         */
        $action_settings_menu = [];
        $option = "";
        switch (config("metadata.system.app.zentrotraderbot.tradingview.alert.action.level")) {
            case 1:
                $option = "NOTIFICATIONS";
                array_push($action_settings_menu, ["text" => "💵 " . Lang::get("zentrotraderbot::bot.options.actionlevel2"), "callback_data" => "actionlevel2"]);
                break;
            case 2:
                $option = "EXECUTE ORDERS";
                array_push($action_settings_menu, ["text" => "📣 " . Lang::get("zentrotraderbot::bot.options.actionlevel1"), "callback_data" => "actionlevel1"]);
                break;
            default:
                break;
        }
        $reply = array(
            "text" => "🔔 *" . Lang::get("zentrotraderbot::bot.actionmenu.header") . "*\n\n_" .
                Lang::get("zentrotraderbot::bot.actionmenu.line1") . ":\n" .
                "📣 " . Lang::get("zentrotraderbot::bot.actionmenu.line2") . ".\n" .
                "💵 " . Lang::get("zentrotraderbot::bot.actionmenu.line3") . "._\n\n" .
                "✅ " . Lang::get("zentrotraderbot::bot.actionmenu.line4", ["option" => $option]) . "\n\n" .
                "👇 " . Lang::get("telegrambot::bot.prompts.chooseoneoption") . ":",
        );

        $reply["reply_markup"] = json_encode([
            "inline_keyboard" => [
                $action_settings_menu,
                [
                    ["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"],
                ],
            ],
        ]);

        return $reply;
    }

    public function configMenu($actor)
    {
        $menu = [];
        array_push($menu, [["text" => "🔑 " . Lang::get("zentrotraderbot::bot.prompts.topup.cripto.options.seedphrase"), "callback_data" => "showseedphraseconfirmation|showseedphrase|wallet"]]);

        return $this->getConfigMenu(
            $actor,
            $menu,
        );
    }

    public function suscribeMenu($suscriptor, $level = -1)
    {
        if ($level > -1) {
            $this->ActorsController->updateData(Suscriptions::class, "user_id", $this->actor->user_id, "suscription_level", $level);
            $suscriptor = Suscriptions::where("user_id", $this->actor->user_id)->first();
        }


        $suscription_settings_menu = array();
        $extrainfo = "";
        switch ($suscriptor->data["suscription_level"]) {
            case 1:
            case "1":
                array_push($suscription_settings_menu, [
                    "text" => Lang::get("zentrotraderbot::bot.options.subscribtionlevel", ["icon" => "🅰️", "char" => "A"]),
                    "callback_data" => "suscribelevel0"
                ]);
                array_push($suscription_settings_menu, [
                    "text" => Lang::get("zentrotraderbot::bot.options.subscribtionlevel", ["icon" => "🆎", "char" => "AB"]),
                    "callback_data" => "suscribelevel2"
                ]);
                $extrainfo = "🌎 _" . Lang::get("zentrotraderbot::bot.subscribtionmenu.line6", ["level" => "🅱️"]) . " " .
                    Lang::get("zentrotraderbot::bot.subscribtionmenu.therefore") . "._\n\n";
                break;
            case 2:
            case "2":
                array_push($suscription_settings_menu, [
                    "text" => Lang::get("zentrotraderbot::bot.options.subscribtionlevel", ["icon" => "🅰️", "char" => "A"]),
                    "callback_data" => "suscribelevel0"
                ]);
                array_push($suscription_settings_menu, [
                    "text" => Lang::get("zentrotraderbot::bot.options.subscribtionlevel", ["icon" => "🅱️", "char" => "B"]),
                    "callback_data" => "suscribelevel1"
                ]);
                $extrainfo = "🌎 _" . Lang::get("zentrotraderbot::bot.subscribtionmenu.line6", ["level" => "🆎"]) . " " .
                    Lang::get("zentrotraderbot::bot.subscribtionmenu.therefore") . "._\n\n";
                break;

            default:
                array_push($suscription_settings_menu, [
                    "text" => Lang::get("zentrotraderbot::bot.options.subscribtionlevel", ["icon" => "🅱️", "char" => "B"]),
                    "callback_data" => "suscribelevel1"
                ]);
                array_push($suscription_settings_menu, [
                    "text" => Lang::get("zentrotraderbot::bot.options.subscribtionlevel", ["icon" => "🆎", "char" => "AB"]),
                    "callback_data" => "suscribelevel2"
                ]);
                $extrainfo = "🌎 _" . Lang::get("zentrotraderbot::bot.subscribtionmenu.line6", ["level" => "🅰️"]) . "._\n\n";
                break;
        }
        $reply = array(
            "text" => "🔔 *" . Lang::get("zentrotraderbot::bot.subscribtionmenu.header") . "*\n" .
                Lang::get("zentrotraderbot::bot.subscribtionmenu.line1") . ":\n\n" .
                "🧩 _" . Lang::get("zentrotraderbot::bot.subscribtionmenu.line2") . ":_\n" .
                "🅰️ _" . Lang::get("zentrotraderbot::bot.subscribtionmenu.line3") . "._\n" .
                "🅱️ _" . Lang::get("zentrotraderbot::bot.subscribtionmenu.line4") . "._\n" .
                "🆎 _" . Lang::get("zentrotraderbot::bot.subscribtionmenu.line5") . "._\n\n" .
                $extrainfo .
                "👇 " . Lang::get("telegrambot::bot.prompts.chooseoneoption") . ":",
        );
        if ($suscriptor->data["suscription_level"] > 0) {
            array_push($suscription_settings_menu, ["text" => "🌎 " . Lang::get("zentrotraderbot::bot.options.clienturl"), "callback_data" => "clienturl"]);
        }

        $reply["reply_markup"] = json_encode([
            "inline_keyboard" => [
                $suscription_settings_menu,
                [
                    ["text" => "↖️ " . Lang::get("telegrambot::bot.options.backtomainmenu"), "callback_data" => "menu"],
                ],
            ],
        ]);

        return $reply;
    }

    public function notifyDepositConfirmed($user_id, $amount, $token_address)
    {
        $token = ConfigService::getToken($token_address, env("BASE_NETWORK"));

        $autodestroy = 3;
        $text =
            "👍 *" . Lang::get("zentrotraderbot::bot.prompts.buy.badcurrency.header") . "* \n" .
            "💵 " . Lang::get("zentrotraderbot::bot.prompts.buy.badcurrency.warning", [
                        "amount" => $amount,
                        "currency" => $token["symbol"]
                    ]) . "\n" .
            "🧏 _" . Lang::get("zentrotraderbot::bot.prompts.buy.badcurrency.text", [
                        "currency" => $token["symbol"]
                    ]) . "_";
        if (strtolower($token_address) == strtolower(env('BASE_TOKEN'))) {
            $text =
                "✅ *" . Lang::get("zentrotraderbot::bot.prompts.buy.completed.header") . "* \n" .
                "💵 " . Lang::get("zentrotraderbot::bot.prompts.buy.completed.warning", [
                            "amount" => $amount,
                            "currency" => $token["symbol"]
                        ]) . "\n" .
                "✨ _" . Lang::get("zentrotraderbot::bot.prompts.buy.completed.text") . "_";
            $autodestroy = 0;
        }

        $array = array(
            "message" => array(
                "text" => $text,
                "chat" => array(
                    "id" => $user_id,
                ),
            ),
        );
        TelegramController::sendMessage($array, $this->tenant->token, $autodestroy);
    }

    private function notifyNetworkStatus()
    {
        // 1. Instanciamos el controlador que centraliza la data
        $blockchain = new BlockchainController();
        $status = $blockchain->getStatus();

        if (!$status) {
            return [
                "text" => "❌ Error: No se pudo conectar con la Blockchain.",
                "chat" => ["id" => $this->actor->user_id]
            ];
        }

        try {
            // 2. Extraemos variables del array unificado
            $network = $status['network'];
            $token = $status['token'];
            $costInUsd = $status['costInUsd'];
            $gasPriceGwei = $status['gasPriceGwei'];
            $feePercentage = $status['feePercentage'];
            $currentMinFeeUsd = $status['currentMinFeeUsd'];
            $referenceTrade = $status['referenceTrade'];
            $breakEvenTrade = $status['breakEvenTrade'];

            // 3. Construimos el reporte de estado
            $msg = "🌐 *ESTADO DE : {$network['title']}*\n\n";
            $msg .= "💰 *Token Principal:* `{$token['symbol']}`\n";
            $msg .= "⛽ *Gas Actual:* `" . number_format($gasPriceGwei, 2) . "` Gwei\n";
            $msg .= "💸 *Costo de Tx:* `\$" . number_format($costInUsd, 4) . "`\n";
            $msg .= "📈 *Fee Escrow:* `" . ($feePercentage / 100) . "%` (" . round($feePercentage) . " bps)\n";
            $msg .= "💲 *MinFee Actual:* `\$" . number_format($currentMinFeeUsd, 4) . "`\n\n";

            // Diagnóstico dinámico
            if ($costInUsd > $currentMinFeeUsd) {
                $msg .= "💡 Basado en trades promedio de: 💲*" . number_format($referenceTrade, 2) . "*\n";
                $msg .= "⚠️ *ALERTA:* Estás operando en pérdida con trades de: 💲" . number_format($breakEvenTrade, 2);
            } else {
                $margin = (($currentMinFeeUsd - $costInUsd) / $currentMinFeeUsd) * 100;
                $msg .= "✅ *SISTEMA SALUDABLE:* Tienes un margen del `" . round($margin) . "%` sobre el MinFee.";
            }

            return [
                "text" => $msg,
                "chat" => ["id" => $this->actor->user_id],
            ];

        } catch (\Exception $e) {
            return [
                "text" => "❌ Error al procesar el reporte: " . $e->getMessage(),
                "chat" => ["id" => $this->actor->user_id]
            ];
        }
    }

}
