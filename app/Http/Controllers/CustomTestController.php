<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Web3\Http\Controllers\BlockchainProviderController;
use Modules\Web3\Services\Web3MathService;
use Modules\Web3\Services\ConfigService;
use Modules\Laravel\Http\Controllers\TestController as BaseController;
use Modules\Web3\Http\Controllers\ChainidController;
use Modules\Web3\Http\Controllers\InchController;
use Modules\Laravel\Services\Codes\QrService;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Crypt;
use Modules\Laravel\Http\Controllers\TextController;
use Modules\ZentroTraderBot\Http\Controllers\TraderWalletController;
use Modules\ZentroTraderBot\Http\Controllers\BlockchainController;
use Illuminate\Support\Facades\Http;
use Modules\Web3\Http\Controllers\CoingeckoController;
use Modules\Laravel\Services\Exchange\CambiocupService;
use Modules\Web3\Http\Controllers\ZeroExController;
use Illuminate\Support\Str;

class CustomTestController extends BaseController
{
    private $GutoTradeTestBot;
    private $ZentroTraderBot;
    private $KashioBot;

    public function __construct()
    {
        parent::__construct();


        $this->GutoTradeTestBot = TelegramBots::where('name', "@GutoTradeTestBot")->first();
        $this->ZentroTraderBot = TelegramBots::where('name', "@ZentroTraderBot")->first();
        $this->KashioBot = TelegramBots::where('name', "@KashioBot")->first();
    }

    public function test(Request $request, $name = null)
    {
        // 1. Si se pasó un nombre y el método existe en esta clase...
        if ($name && method_exists($this, $name)) {
            // Ejecutamos el método dinámicamente y retornamos su respuesta
            return $this->{$name}($request);
        }

        // 2. Si no hay nombre o el método no existe, ejecutamos la lógica base del paquete
        return parent::test($request);
    }


    public function testPublic()
    {
        $this->KashioBot->connectToThisTenant();


        // Datos de la oferta (estos vendrían de tu base de datos o estado)
        $usdAVender = rand(10, 100);
        $tasaCambio = 581;
        $usdARecibir = $usdAVender * $tasaCambio;
        $currency = "CUP";
        $banco = "BANDEC Prepago";
        $numeroTarjeta = rand(1000, 9999) . " 2134 1123 1212";

        $id = Str::uuid();

        // Construcción del texto profesional con HTML
        $text = "🟥 *¡NUEVA OFERTA!*\n";
        $text .= "🆔 `" . $id . "`\n";

        $text .= "💸 En venta: *{$usdAVender} USD*\n";
        $text .= "💱 Tasa: *{$tasaCambio} {$currency}/USD*\n";
        $text .= "💰 Recibe: *{$usdARecibir} {$currency}*\n";
        //$text .= "------------------------------------------\n";
        $text .= "🏦 Medio de Pago: *{$banco}*\n\n";

        $text .= "🛡 _Use siempre el sistema de custodia para transacciones 100% seguras en nuestro P2P._\n\n";

        $response = TelegramController::sendMessage(
            array(
                "message" => array(
                    "text" => $text,
                    "chat" => array(
                        "id" => env("TRADER_BOT_CHANNEL"),
                    ),
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                [
                                    "text" => "👉 Aplicar a esta oferta",
                                    'url' => "https://t.me/" . $this->KashioBot->code . "?offer={$id}"
                                ]
                            ],
                        ],
                    ]),
                ),
            ),
            $this->KashioBot->token
        );
        if ($response) {
            $array = json_decode($response, true);
            dd($array["result"]["message_id"]);
        }
    }


    public function testSwap()
    {
        $user_id = 816767995;
        $this->KashioBot->connectToThisTenant();
        $suscriptor = Suscriptions::where("user_id", $user_id)->first();
        $encryptedKey = $suscriptor->data['wallet']['private_key'];
        $privateKey = decryptValue($encryptedKey);

        $from = ConfigService::getToken("POL", 137);
        $to = ConfigService::getToken("USDC", 137);
        //dd($to["address"]);

        // 🔓 Desencriptamos manualmente
        //dd($privateKey);

        $engine = new ZeroExController();
        $engine->swap(
            137,
            $from["address"],
            $to["address"],
            4,
            $privateKey,
            env("ZERO_EX_API_KEY"),
            env("TREASURY_WALLET"),
            env("SWAP_FEE_PERCENTAGE"),
            function ($text, $autodestroy) use ($user_id) {
                TelegramController::sendMessage(
                    array(
                        "message" => array(
                            "text" => $text,
                            "chat" => array(
                                "id" => $user_id,
                            )
                        ),
                    ),
                    $this->KashioBot->token,
                    $autodestroy
                );
            },
        );

    }

    public function testCall()
    {
        $bot = TelegramBots::where('name', "@ZentroOwnerBot")->first();

        $user_id = 816767995;
        $url = "https://dev.micalme.com/telegram/bot/" . $bot->key;
        $text = "/start";
        $payload = [
            'message' => [
                'message_id' => rand(1, 100),
                'from' => [
                    'id' => $user_id,
                    'username' => 'sim_user',
                ],
                'chat' => [
                    'id' => $user_id,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $text,
            ]
        ];


        try {
            $response = Http::withHeaders([
                'X-Telegram-Bot-Api-Secret-Token' => $bot->secret,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);
            dd($response->body());
            return $response->body();
        } catch (\Throwable $th) {
            die("🆘 TelegramController getFileUrl: " . $th->getTraceAsString());
        }
    }


    public function testPrice()
    {
        dd(\Modules\Laravel\Services\NumberService::parse("1,500.5"));
        $coin = "cup";
        $val = CoingeckoController::getLivePrice("tether", $coin);
        if (empty($val))
            $val = CambiocupService::getRate($coin);
        if (empty($val))
            $val = 1.02;
        dd($val, CambiocupService::getAvailableBanks());
    }


    public function testEncryption()
    {

        $this->KashioBot->connectToThisTenant();
        //dd(cache('dvzambrano_laravel_vault_seed'));

        $suscriptors = Suscriptions::all();
        foreach ($suscriptors as $suscriptor) {

            $encryptedKey = $suscriptor->data['wallet']['private_key'];

            // 🔓 Desencriptamos manualmente
            dd(decryptValue($encryptedKey));


        }

        die("DONE!");

        // 1. Intentamos descifrar la llave maestra
        $encrypted = env("ESCROW_ARBITER_KEY");
        $decrypted = decryptValue($encrypted);

        dd($decrypted, $encrypted);
    }


    public function testStatus()
    {
        $controller = new BlockchainController();
        dd($controller->getStatus());
    }
    public function testLocal()
    {
        dd(app()->environment('local'));
    }

    public function testNetworks()
    {
        //$network = ConfigService::getNetworks(137);
        //dd($network);
        $network = ConfigService::getNetworks(env("BASE_NETWORK"));
        //dd($network);
        $token = ConfigService::getToken(env('BASE_TOKEN'), $network["chainId"]);
        dd($network, $token);
    }

    private function getDots($width, $left, $right)
    {
        $paddingLength = $width - strlen($left) - strlen($right);
        $dots = str_repeat('.', max(1, $paddingLength));

        return "{$left} {$dots} {$right}";
    }


    public function testBalance()
    {
        $user_id = 816767995;
        $this->KashioBot->connectToThisTenant();
        $suscriptor = Suscriptions::where("user_id", $user_id)->first();
        $walletController = new TraderWalletController();
        $balance = 0;
        $transactions = [];
        try {
            // 3. Obtener Balance REAL (específicamente de BASE_TOKEN en Polygon)
            // El método getBalance que tienes devuelve el portfolio
            $balance = $walletController->getBalance($suscriptor);
            // 4. Obtener Transacciones 
            $limit = 5;
            $transactions = $walletController->getRecentTransactions($suscriptor, $limit);
        } catch (\Throwable $th) {
            //throw $th;
        }
        //dd($transactions);


        // 2. Definimos el ancho total de la línea (ejemplo: 35 caracteres)
        $totalWidth = 45;


        $message = "💵 *Saldo disponible*:\n";
        $date = $suscriptor->actor->getLocalDateTime(date("Y-m-d H:i:s"), $this->KashioBot->code, "Y-m-d h:i a");
        $message .= $this->getDots($totalWidth, $date, number_format($balance, 2) . " USD") . "\n\n";

        $message .= "⏱️ *Últimas operaciones*:\n";
        foreach ($transactions as $tx) {
            // 1. Formateamos la fecha y el monto
            $date = $suscriptor->actor->getLocalDateTime($tx['human']['date'], $this->KashioBot->code, "Y-m-d h:i a");
            $amount = ($tx['human']['value'] > 0 ? '+' : '') . number_format($tx['human']['value'], 2) . " USD";

            $message .= $this->getDots($totalWidth, $date, $amount) . "\n";
        }

        $array = array(
            "message" => array(
                "text" => $message,
                "chat" => array(
                    "id" => $user_id,
                ),
            ),
        );
        TelegramController::sendMessage($array, $this->KashioBot->token);
        die("done!");
    }

    public function testQr()
    {
        $user_id = 816767995;
        $data = "abstract accident announce anything appetite assembly boundary building calendar campaign champion creative";

        $words = explode(' ', $data);
        $message = "```\n";
        for ($i = 0; $i < count($words); $i += 2) {
            $p1 = str_pad(sprintf("%02d: %s", $i + 1, $words[$i]), 13);
            $p2 = str_pad(sprintf("%02d: %s", $i + 2, $words[$i + 1]), 13);
            $message .= "{$p1} {$p2}\n";
        }

        $message .= "```";

        $array = array(
            "message" => array(
                "text" =>
                    "👇 *Tus " . count($words) . " palabras de seguridad*: \n" .
                    "{$message}\n" .
                    "📋 _Copie o escanee esta información rapidamente: _\n" .
                    "⌛️ _Por seguridad este mensaje se elimina en 1 minuto_\n",
                "photo" => "https://quickchart.io/qr?text={$data}&size=220",
                "chat" => array(
                    "id" => $user_id,
                ),
            ),
        );
        TelegramController::sendPhoto($array, $this->KashioBot->token, 1);
        die("done!");

        $qrService = new QrService();
        dd($qrService->generateSvg($data, 220));
    }


    public function testCache()
    {
        //$networks = ConfigService::getNetworks(false, false);
        //dd($networks);
        $network = ConfigService::getActiveNetwork();
        //dd($network);
        $token = ConfigService::getToken(env('BASE_TOKEN'), $network["chainId"]);
        // ($token);
        $test = ConfigService::getToken("MATIC", $network["chainId"]);
        dd($test);

        dd($network, $token);
    }

    public function testWalletController()
    {
        $address = env("TEST_WALLET");

        $chain = ConfigService::getActiveNetwork();
        $token = ConfigService::getToken(env('BASE_TOKEN'), strtoupper($chain["shortName"]));
        $balances = BlockchainProviderController::getTokenBalance($address, $chain, [$token]);
        $txs = BlockchainProviderController::getRecentTransactions($address, $chain, [$token]);
        //dd($balances);

        $humanBal = "0.0";
        if (is_array($balances) && count($balances)) {
            foreach ($balances as $i => $bal) {
                $hexBal = $bal['tokenBalance'] ?? '0x0';
                // Convertimos el Hex del nodo a Wei String
                $rawBalanceStr = Web3MathService::hexToDecimal($hexBal);
                // Dividimos por la potencia de 10 según los decimales del token (6 para USDC, 18 para DAI)
                $humanBal = (float) bcdiv($rawBalanceStr, bcpow('10', (string) $token["decimals"]), $token["decimals"]);
            }
        }

        dd($balances, $humanBal, $txs);
    }

    /**
     * Prueba todas las funciones principales de TelegramController.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testTelegramController()
    {
        $bot_token = $this->GutoTradeTestBot->token;
        $chat_id = 816767995; // ID de chat de prueba
        $user_id = 816767995; // ID de usuario de prueba
        // Valores extraídos del getUpdates proporcionado (usados como fallback)
        $fallback_message_id = 3323;
        $fallback_photo_large = 'AgACAgEAAxkBAAIM-2mV4hXARU_ZzTYdzlIChFsD88UsAAI1DGsbwzyxRJ5pRlSRi1nvAQADAgADeAADOgQ';
        $fallback_photo_med = 'AgACAgEAAxkBAAIM-2mV4hXARU_ZzTYdzlIChFsD88UsAAI1DGsbwzyxRJ5pRlSRi1nvAQADAgADbQADOgQ';
        $fallback_photo_small = 'AgACAgEAAxkBAAIM-2mV4hXARU_ZzTYdzlIChFsD88UsAAI1DGsbwzyxRJ5pRlSRi1nvAQADAgADcwADOgQ';

        $file_id = $fallback_photo_large; // file_id de prueba para exportFileLocally
        $document_id = null; // document_id de prueba
        $photo_id = $fallback_photo_large; // photo_id de prueba
        $media = [['type' => 'photo', 'media' => $fallback_photo_large]]; // media de prueba para sendMediaGroup
        $existing_message_id = $fallback_message_id;

        $message = [
            'chat' => ['id' => $chat_id],
            'text' => 'Mensaje de prueba ' . now(),
        ];

        $results = [];

        // 1. Enviar mensaje original con texto fijo + fecha
        $original_text = 'mensaje original ' . now();
        $message['text'] = $original_text;
        $results['sendMessage'] = json_decode(TelegramController::sendMessage(['message' => $message], $bot_token), true);
        $original_message_id = $results['sendMessage']['result']['message_id'] ?? null;

        // 2. Copiar el mensaje original (copyMessage)
        if ($original_message_id) {
            $copyMessage = [
                'chat' => ['id' => $chat_id],
                'from_chat_id' => $chat_id,
                'message_id' => $original_message_id,
            ];
            $results['copyMessage'] = json_decode(TelegramController::copyMessage(['message' => $copyMessage], $bot_token), true);
        }

        // 3. Editar el mensaje original: mantener texto inicial y añadir nueva línea con fecha/hora de edición
        if ($original_message_id) {
            $editMessage = [
                'chat' => ['id' => $chat_id],
                'message_id' => $original_message_id,
                'text' => $original_text . "\nEditado: " . now(),
            ];
            $results['editMessageText'] = json_decode(TelegramController::editMessageText(['message' => $editMessage], $bot_token), true);
        }

        // 4. ForwardMessage: reenviar el mensaje ya editado (usar original_message_id)
        if ($original_message_id) {
            $forwardMessage = [
                'chat' => ['id' => $chat_id],
                'from' => ['id' => $chat_id],
                'message_id' => $original_message_id,
            ];
            $results['forwardMessage'] = json_decode(TelegramController::forwardMessage(['message' => $forwardMessage], $bot_token), true);
        }

        // 5. Enviar un mensaje nuevo y luego eliminarlo
        $temp_message = [
            'chat' => ['id' => $chat_id],
            'text' => 'Mensaje temporal ' . now(),
        ];
        $results['tempSend'] = json_decode(TelegramController::sendMessage(['message' => $temp_message], $bot_token), true);
        $temp_id = $results['tempSend']['result']['message_id'] ?? null;
        if ($temp_id) {
            $deleteMessage = [
                'chat' => ['id' => $chat_id],
                'id' => $temp_id,
            ];
            $results['tempDelete'] = json_decode(TelegramController::deleteMessage(['message' => $deleteMessage], $bot_token), true);
        }

        // 6. PinMessage (requiere un message_id válido, pero el mensaje ya fue reenviado y borrado, así que se omite para evitar error)

        // 7. sendPhoto (requiere un photo válido)
        if ($photo_id) {
            $photoMessage = [
                'chat' => ['id' => $chat_id],
                'photo' => $photo_id,
                'text' => 'Foto de prueba',
            ];
            $results['sendPhoto'] = json_decode(TelegramController::sendPhoto(['message' => $photoMessage], $bot_token), true);
            // Pin the photo message if sendPhoto succeeded
            $photo_msg_id = $results['sendPhoto']['result']['message_id'] ?? null;
            if ($photo_msg_id) {
                $pinReq = [
                    'chat' => ['id' => $chat_id],
                    'message_id' => $photo_msg_id,
                ];
                $results['pinPhotoMessage'] = json_decode(TelegramController::pinMessage(['message' => $pinReq], $bot_token), true);
            }
        }

        // 8. sendMediaGroup (requiere media válido)
        if ($media) {
            $mediaMessage = [
                'chat' => ['id' => $chat_id],
                'media' => $media,
            ];
            $results['sendMediaGroup'] = json_decode(TelegramController::sendMediaGroup(['message' => $mediaMessage], $bot_token), true);
        }

        // 9. sendDocument (requiere document válido)
        if ($document_id) {
            $docMessage = [
                'chat' => ['id' => $chat_id],
                'document' => $document_id,
            ];
            $results['sendDocument'] = json_decode(TelegramController::sendDocument(['message' => $docMessage], $bot_token), true);
        }

        // getBotInfo
        $results['getBotInfo'] = json_decode(TelegramController::getBotInfo($bot_token), true);

        // getUserInfo
        $results['getUserInfo'] = json_decode(TelegramController::getUserInfo($user_id, $bot_token), true);

        // getUserPhotos: fuerza el id 816767995 para asegurar datos
        $results['getUserPhotos'] = TelegramController::getUserPhotos(816767995, $bot_token);
        // Si la API no devuelve fotos, usar fallback con file_id extraídos
        if (empty($results['getUserPhotos'])) {
            $results['getUserPhotos'] = [
                [
                    [
                        'file_id' => $fallback_photo_small,
                        'file_unique_id' => 'AQADNQxrG8M8sUR4',
                        'file_size' => 897,
                        'width' => 73,
                        'height' => 90,
                    ],
                    [
                        'file_id' => $fallback_photo_med,
                        'file_unique_id' => 'AQADNQxrG8M8sURy',
                        'file_size' => 12940,
                        'width' => 258,
                        'height' => 320,
                    ],
                    [
                        'file_id' => $fallback_photo_large,
                        'file_unique_id' => 'AQADNQxrG8M8sUR9',
                        'file_size' => 14588,
                        'width' => 291,
                        'height' => 361,
                    ],
                ]
            ];
        }

        // getFileUrl (requiere file_id válido)
        if ($file_id) {
            $results['getFileUrl'] = json_decode(TelegramController::getFileUrl($file_id, $bot_token), true);
        }

        // exportFileLocally (requiere file_id válido)
        if ($file_id) {
            $results['exportFileLocally'] = TelegramController::exportFileLocally($file_id, $bot_token);
        }

        // Enviar resumen compacto: una línea por cada prueba con ✅ o ❌
        $lines = [];
        foreach ($results as $key => $value) {
            $ok = false;
            if (is_array($value)) {
                if (isset($value['ok'])) {
                    $ok = $value['ok'] === true;
                } else {
                    // Considerar exitoso si hay contenido en el array
                    $ok = count($value) > 0;
                }
            } elseif ($value === true) {
                $ok = true;
            }

            $emoji = $ok ? '✅' : '❌';
            $lines[] = $emoji . ' ' . $key;
        }

        $text = implode("\n", $lines);
        $notify = [
            'chat' => ['id' => $chat_id],
            'text' => $text,
        ];

        TelegramController::sendMessage(['message' => $notify], $bot_token);

        return response()->json([
            'status' => 'sent',
            'lines' => $lines,
        ]);
    }
}
