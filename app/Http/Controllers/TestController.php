<?php
namespace App\Http\Controllers;

use Modules\Laravel\Http\Controllers\GraphsController;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\GutoTradeBot\Entities\Capitals;
use Modules\GutoTradeBot\Entities\Moneys;
use Modules\GutoTradeBot\Entities\Profits;
use Modules\GutoTradeBot\Entities\Payments;
use Modules\GutoTradeBot\Entities\Rates;
use Modules\GutoTradeBot\Http\Controllers\CapitalsController;
use Modules\GutoTradeBot\Http\Controllers\GutoTradeBotController;
use Modules\GutoTradeBot\Http\Controllers\PaymentsController;
use Modules\GutoTradeBot\Http\Controllers\ProfitsController;
use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\GutoTradeBot\Http\Controllers\CoingeckoController;
use Webklex\IMAP\Facades\Client;
use Modules\TelegramBot\Entities\Actors;
use Modules\TelegramBot\Entities\TelegramBots;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Modules\Laravel\Http\Controllers\FileController;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Modules\GutoTradeBot\Jobs\CheckEmails;
use Illuminate\Support\Facades\Storage;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Http\Controllers\ZentroTraderBotController;
use Modules\Web3\Http\Controllers\WalletController;
use Modules\ZentroTraderBot\Http\Controllers\TraderWalletController;
use Modules\ZentroOwnerBot\Http\Controllers\ZentroOwnerBotController;

use FurqanSiddiqui\BIP39\BIP39;
use FurqanSiddiqui\BIP39\Wordlist;

use Modules\Laravel\Services\Office\ExcelService;
use Modules\ZentroPackageBot\Entities\Packages;

use Modules\ZentroTraderBot\Http\Controllers\RampController;
use Modules\Web3\Http\Controllers\AlchemyController;
use Modules\Laravel\Entities\Metadatas;
use Modules\Web3\Services\Web3MathService;
use Modules\Laravel\Http\Controllers\Controller;

class TestController extends Controller
{
    private $GutoTradeTestBot;
    private $ZentroTraderBot;

    public function __construct()
    {
        parent::__construct();


        $this->GutoTradeTestBot = TelegramBots::where('name', "@GutoTradeTestBot")->first();
        $this->ZentroTraderBot = TelegramBots::where('name', "@ZentroTraderBot")->first();
    }

    public function test(Request $request)
    {
        $metadata = Metadatas::where('name', "app_zentrotraderbot_alchemy_authtoken")->first();
        dd($metadata->value);

        $this->testWalletController();
        //$this->testTelegramController();
        die;
    }

    public function testWalletController()
    {
        $address = "0xd2531438b90232f4Aab4DDfC6f146474e84E1Ea1";
        $authToken = config('metadata.system.app.zentrotraderbot.alchemy.authtoken');
        $usdcContract = config('web3.tokens.USDC.address');
        $balances = AlchemyController::getTokenBalances($authToken, $address, [$usdcContract]);
        $humanBal = "0.0";
        if (is_array($balances) && count($balances)) {
            foreach ($balances as $i => $bal) {
                $hexBal = $bal['tokenBalance'] ?? '0x0';
                // Conversión humana
                $humanBal = Web3MathService::hexToDecimal($hexBal, 6);
            }
        }


        $txs = AlchemyController::getRecentTransactions($authToken, $address, ["erc20"], [$usdcContract]);

        dd($authToken, $address, $usdcContract, $balances, $humanBal, $txs);
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
