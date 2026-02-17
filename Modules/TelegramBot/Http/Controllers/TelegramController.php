<?php

namespace Modules\TelegramBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Http\Controllers\FileController;
use Modules\TelegramBot\Jobs\DeleteTelegramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramController extends Controller
{
    public static function analizeUrl($url)
    {
        // Primero, utilizamos parse_url para obtener la parte del "path"
        $array = parse_url($url);
        $array["url"] = $url;
        // Ahora obtenemos el "path" completo
        // Luego, usamos explode para dividir la cadena y obtener solo el último segmento
        $array["path_parts"] = explode('/', $array['path']);
        // Si hay una query string, la extraemos
        $query = [];
        if (isset($array['query'])) {
            parse_str($array['query'], $query);
        }
        $array["query_parts"] = $query;

        return $array;
    }

    public static function cleanText4Url($text)
    {
        // Lista de caracteres problemáticos a reemplazar
        $chars = [
            '_' => ' ',
            '+' => '',
            '%' => '',
            '&' => '',
            '#' => '',
            '=' => '',
            '?' => '',
            '/' => '',
            '\\' => '',
            //' ' => '',
        ];
        return strtr($text, $chars);
    }
    public static function escapeText4Url($text)
    {
        // Lista de caracteres problemáticos a reemplazar
        $chars = [
            '_' => '\_', // Escapar el guion bajo
            '+' => '\+', // Escapar el símbolo más
            '%' => '\%', // Escapar el porcentaje
            '&' => '\&', // Escapar el ampersand
            '#' => '\#', // Escapar el símbolo de número
            '=' => '\=', // Escapar el signo igual
            '?' => '\?', // Escapar el signo de interrogación
            '/' => '\/', // Escapar la barra
            '\\' => '\\\\', // Escapar la barra invertida
        ];
        return strtr($text, $chars);
    }

    // ["result":["message_id":ID]] ID = 0 ERROR; ID = -1 DEMO
    public static function send($request, $url, $attempt = 1, $data = false)
    {
        try {
            // si es DEMO escribimos en la consola y retornamos message_id -1
            if (isset($request["demo"]) && $request["demo"] == true) {
                echo "message = ";
                var_dump(
                    array(
                        "url" => $url,
                        "message" => $request["message"],
                    )
                );

                return json_encode(
                    array(
                        "result" => array(
                            "message_id" => -1,
                        ),
                    )
                );
            }

            $url .= "&parse_mode=Markdown";
            if (isset($request["message"]["reply_to_message_id"]) && $request["message"]["reply_to_message_id"] != "") {
                $url .= "&reply_to_message_id={$request["message"]["reply_to_message_id"]}";
            }
            if (isset($request["message"]["reply_markup"]) && $request["message"]["reply_markup"] != "") {
                $reply_markup = urlencode($request["message"]["reply_markup"]);
                $url .= "&reply_markup={$reply_markup}";
            }

            $response = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded",
                    'content' => $data ? http_build_query($data) : false,
                ],
            ]));

            return $response;

        } catch (\Throwable $th) {
            $array = TelegramController::analizeUrl($url);
            $method = $array["path_parts"][count($array["path_parts"]) - 1];
            Log::error("TelegramController {$method} attempt {$attempt}, CODE: {$th->getCode()}, line {$th->getLine()}, URL: {$url}, Message: {$th->getMessage()}");
            //Log::error("TelegramController TraceAsString: " . $th->getTraceAsString());

            // si hay algun error retornamos message_id 0
            return json_encode(
                array(
                    "result" => array(
                        "message_id" => 0,
                        "text" => $th->getMessage(),
                    ),
                )
            );
        }

    }

    public static function sendMessage($request, $bot_token, $autodestroy = 0)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/sendMessage?chat_id={$request["message"]["chat"]["id"]}" .
            "&text=" . urlencode($request["message"]["text"]);

        $response = TelegramController::send($request, $url);

        if ($autodestroy > 0) {
            $array = json_decode($response, true);
            //Log::info("TelegramController sendMessage array: " . json_encode($array));

            if (isset($array["result"]["message_id"]) && $array["result"]["message_id"] > -1) {
                DeleteTelegramMessage::dispatch(
                    (string) $bot_token,
                    (int) $array["result"]["chat"]["id"],
                    (int) $array["result"]["message_id"]
                )->delay(now()->addMinutes((int) $autodestroy));
            }
        }

        return $response;
    }

    public static function editMessageText($request, $bot_token)
    {
        // Estructura básica: chat_id, message_id y el nuevo texto
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/editMessageText?chat_id={$request["message"]["chat"]["id"]}" .
            "&message_id={$request["message"]["message_id"]}" .
            "&text=" . urlencode($request["message"]["text"]);

        // Reutilizamos tu método send que ya maneja parse_mode, reply_markup y logs de errores
        return TelegramController::send($request, $url);
    }

    public static function copyMessage($request, $bot_token)
    {
        // chat_id: a quién se lo mandas
        // from_chat_id: de dónde viene el mensaje original (el chat del admin)
        // message_id: el ID del mensaje que el admin quiere anunciar
        $url = "https://api.telegram.org/bot{$bot_token}/copyMessage?" .
            "chat_id={$request["message"]["chat"]["id"]}&" .
            "from_chat_id={$request["message"]["from_chat_id"]}&" .
            "message_id={$request["message"]["message_id"]}";

        return TelegramController::send($request, $url);
    }

    public static function sendPhoto($request, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/sendPhoto?chat_id={$request["message"]["chat"]["id"]}" .
            "&photo={$request["message"]["photo"]}" .
            "&caption=" . urlencode($request["message"]["text"]);

        $response = TelegramController::send($request, $url);
        $array = json_decode($response, true);

        if (isset($array["result"]) && isset($array["result"]["message_id"]) && $array["result"]["message_id"] == 0) {
            return TelegramController::sendMessage($request, $bot_token);
        }

        return $response;
    }

    public static function sendMediaGroup($request, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/sendMediaGroup?chat_id={$request["message"]["chat"]["id"]}" .
            "&media={$request["message"]["media"]}";

        return TelegramController::send($request, $url);
    }

    public static function sendDocument($request, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/sendDocument?chat_id={$request["message"]["chat"]["id"]}" .
            "&document={$request["message"]["document"]}"
            //."&caption=" . urlencode($request["message"]["text"])
        ;

        return TelegramController::send($request, $url);

    }
    public static function pinMessage($request, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/pinChatMessage?chat_id={$request["message"]["chat"]["id"]}" .
            "&message_id={$request["message"]["message_id"]}";

        return TelegramController::send($request, $url);
    }

    public static function deleteMessage($request, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/deleteMessage?chat_id={$request["message"]["chat"]["id"]}" .
            "&message_id={$request["message"]["id"]}";

        return TelegramController::send($request, $url);
    }

    public static function forwardMessage($request, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/forwardMessage";

        return TelegramController::send($request, $url, 1, [
            'chat_id' => $request["message"]["chat"]["id"],
            'from_chat_id' => $request["message"]["from"]["id"],
            'message_id' => $request["message"]["message_id"],
        ]);
    }

    public static function getBotInfo($bot_token)
    {
        $response = false;
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/getMe";

        try {
            $response = file_get_contents($url);

        } catch (\Throwable $th) {
            //Log::error("TelegramController getBotInfo: " . $th->getTraceAsString());
        }

        return $response;
    }

    public static function getUserInfo($userId, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/getChat?chat_id={$userId}";

        $json = array(
            "result" => array(
                "full_name" => "👤 {$userId}",
                "full_info" => "👤 {$userId}",
            ),
        );

        try {
            $response = file_get_contents($url);

            $json = json_decode($response, true);

            // Formando un text personalizado con los datos del usuario
            $text = "👤 ";
            if (isset($json["result"]["first_name"])) {
                $text .= TelegramController::cleanText4Url($json["result"]["first_name"]);
            }
            if (isset($json["result"]["last_name"])) {
                $text .= " " . TelegramController::cleanText4Url($json["result"]["last_name"]);
            }
            $json["result"]["full_name"] = $text;
            if (isset($json["result"]["username"])) {
                $json["result"]["formated_username"] = TelegramController::escapeText4Url($json["result"]["username"]);
                $text .= " \n✉️ @" . $json["result"]["formated_username"];

            }
            $text .= " \n🆔 `" . $userId . "`";
            $json["result"]["full_info"] = $text;

        } catch (\Throwable $th) {
            //Log::error("TelegramController getUserInfo: " . $th->getTraceAsString());
        }

        return json_encode($json);
    }

    private static function getUserProfilePhotos($userId, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/getUserProfilePhotos?user_id={$userId}";
        try {
            $response = file_get_contents($url);
            return $response;

        } catch (\Throwable $th) {
            //Log::error("TelegramController getFileUrl: " . $th->getTraceAsString());
        }
    }

    public static function getUserPhotos($userId, $bot_token)
    {
        $array = array();
        $response = json_decode(TelegramController::getUserProfilePhotos($userId, $bot_token), true);
        if (isset($response["result"]) && isset($response["result"]["photos"]) && count($response["result"]["photos"]) > 0) {
            $array = $response["result"]["photos"];
        }
        return $array;
    }

    public static function getFileUrl($fileId, $bot_token)
    {
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/getFile?file_id={$fileId}";
        try {
            $response = file_get_contents($url);
            return $response;

        } catch (\Throwable $th) {
            //Log::error("TelegramController getFileUrl: " . $th->getTraceAsString());
        }
    }

    public static function getFile($filePath, $bot_token)
    {
        $url = "https://api.telegram.org/file/bot" .
            $bot_token .
            "/{$filePath}";

        $contents = file_get_contents($url);

        return $contents;
    }

    public static function exportFileLocally($fileId, $bot_token)
    {
        $response = TelegramController::getFileUrl($fileId, $bot_token);
        $response = json_decode($response, true);
        $filePath = $response["result"]["file_path"];
        $imageUrl = "https://api.telegram.org/file/bot{$bot_token}/{$filePath}";
        // Descargar y guardar la imagen localmente
        $imageContent = file_get_contents($imageUrl);
        $filename = FileController::getFileNameAsUnixTime("jpg", 1, "HOURS");
        file_put_contents(public_path() . FileController::$AUTODESTROY_DIR . "/" . $filename, $imageContent);


        $array = explode(".", $filename);
        return array(
            "filename" => $array[0],
            "extension" => $array[1],
            "url" => request()->root() . FileController::$AUTODESTROY_DIR . "/" . $filename,
        );
    }
    public function loginCallback(Request $request)
    {
        $botToken = $request->attributes->get('bot_token');
        $auth_data = $request->all();

        if (!$this->checkTelegramAuthorization($auth_data, $botToken)) {
            return redirect('/')->with('error', 'Fallo de integridad.');
        }

        // En lugar de base de datos, guardamos en la sesión de Laravel
        session([
            'telegram_user' => [
                'id' => $auth_data['id'],
                'name' => $auth_data['first_name'] . ' ' . ($auth_data['last_name'] ?? ''),
                'username' => $auth_data['username'] ?? null,
                'photo_url' => $auth_data['photo_url'] ?? null,
            ]
        ]);

        return redirect()->intended('/dashboard');
    }

    protected function checkTelegramAuthorization($auth_data, $botToken)
    {
        //Log::error("TelegramController checkAuthorization: " . json_encode($auth_data));
        /*
        {
    "id": "1741391257",
    "first_name": "Crypto",
    "last_name": "Dev",
    "username": "criptodev1981",
    "photo_url": "https:\/\/t.me\/i\/userpic\/320\/OTuPwnXNYWQdvow2ThDsPptkNZ6mYYJV80hnQpR8mSM.jpg",
    "auth_date": "1771339825",
    "hash": "048d49f88eceab46dbf338fe3152eb72796d08d9f30681b092f6b8ea9946253f"
}
        */

        $check_hash = $auth_data['hash'];
        unset($auth_data['hash']);

        $data_check_arr = [];
        foreach ($auth_data as $key => $value) {
            $data_check_arr[] = $key . '=' . $value;
        }
        sort($data_check_arr);
        $data_check_string = implode("\n", $data_check_arr);

        $secret_key = hash('sha256', $botToken, true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);

        return hash_equals($hash, $check_hash);
    }
}
