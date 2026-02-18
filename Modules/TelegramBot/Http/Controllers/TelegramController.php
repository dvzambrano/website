<?php

namespace Modules\TelegramBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Laravel\Http\Controllers\FileController;
use Modules\TelegramBot\Jobs\DeleteTelegramMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class TelegramController extends Controller
{
    /**
     * Construye una URL para la API de Telegram.
     *
     * @param string $bot_token
     * @param string $method
     * @param array $params
     * @return string
     */
    private static function buildTelegramUrl($bot_token, $method, $params = [])
    {
        $base = "https://api.telegram.org/bot{$bot_token}/{$method}";
        if (!empty($params)) {
            $base .= '?' . http_build_query($params);
        }
        return $base;
    }

    public static function analizeUrl($url)
    {
        // Primero, utilizamos parse_url para obtener la parte del "path"
        $array = parse_url($url);
        $array["url"] = $url;
        // Usamos Str::of para explotar el path
        $array["path_parts"] = Str::of($array['path'])->explode('/')->toArray();
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
    /**
     * Envía una petición a la API de Telegram.
     *
     * @param array $request
     * @param string $url
     * @param int $attempt
     * @param array|bool $data
     * @return string
     */
    public static function send($request, $url, $attempt = 1, $data = false)
    {
        try {
            // si es DEMO escribimos en la consola y retornamos message_id -1
            if (isset($request["demo"]) && $request["demo"] == true) {
                Log::debug('message = ', [
                    'url' => $url,
                    'message' => $request["message"]
                ]);
                return response()->json([
                    'result' => [
                        'message_id' => -1,
                    ],
                ])->getContent();
            }

            $url .= "&parse_mode=Markdown";
            if (isset($request["message"]["reply_to_message_id"]) && $request["message"]["reply_to_message_id"] != "") {
                $url .= "&reply_to_message_id={$request["message"]["reply_to_message_id"]}";
            }
            if (isset($request["message"]["reply_markup"]) && $request["message"]["reply_markup"] != "") {
                $reply_markup = urlencode($request["message"]["reply_markup"]);
                $url .= "&reply_markup={$reply_markup}";
            }

            $options = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ];
            $http = Http::withOptions($options);
            if ($data) {
                $response = $http->asForm()->post($url, $data);
            } else {
                $response = $http->post($url);
            }
            return $response->body();

        } catch (\Throwable $th) {
            $array = TelegramController::analizeUrl($url);
            $method = $array["path_parts"][count($array["path_parts"]) - 1];
            Log::error("TelegramController {$method} attempt {$attempt}, CODE: {$th->getCode()}, line {$th->getLine()}, URL: {$url}, Message: {$th->getMessage()}");
            //Log::error("TelegramController TraceAsString: " . $th->getTraceAsString());

            // si hay algun error retornamos message_id 0
            return response()->json([
                'result' => [
                    'message_id' => 0,
                    'text' => $th->getMessage(),
                ],
            ])->getContent();
        }

    }

    /**
     * Envía un mensaje de texto a un chat de Telegram.
     *
     * @param array $request
     * @param string $bot_token
     * @param int $autodestroy
     * @return string
     */
    public static function sendMessage($request, $bot_token, $autodestroy = 0)
    {
        $url = self::buildTelegramUrl($bot_token, 'sendMessage', [
            'chat_id' => $request["message"]["chat"]["id"],
            'text' => $request["message"]["text"]
        ]);

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

    /**
     * Edita el texto de un mensaje ya enviado.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function editMessageText($request, $bot_token)
    {
        // Estructura básica: chat_id, message_id y el nuevo texto
        $url = self::buildTelegramUrl($bot_token, 'editMessageText', [
            'chat_id' => $request["message"]["chat"]["id"],
            'message_id' => $request["message"]["message_id"],
            'text' => $request["message"]["text"]
        ]);

        // Reutilizamos tu método send que ya maneja parse_mode, reply_markup y logs de errores
        return TelegramController::send($request, $url);
    }

    /**
     * Copia un mensaje de un chat a otro.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function copyMessage($request, $bot_token)
    {
        // chat_id: a quién se lo mandas
        // from_chat_id: de dónde viene el mensaje original (el chat del admin)
        // message_id: el ID del mensaje que el admin quiere anunciar
        $url = self::buildTelegramUrl($bot_token, 'copyMessage', [
            'chat_id' => $request["message"]["chat"]["id"],
            'from_chat_id' => $request["message"]["from_chat_id"],
            'message_id' => $request["message"]["message_id"]
        ]);

        return TelegramController::send($request, $url);
    }

    /**
     * Envía una foto a un chat de Telegram.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function sendPhoto($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'sendPhoto', [
            'chat_id' => $request["message"]["chat"]["id"],
            'photo' => $request["message"]["photo"],
            'caption' => $request["message"]["text"]
        ]);

        $response = TelegramController::send($request, $url);
        $array = json_decode($response, true);

        if (isset($array["result"]) && isset($array["result"]["message_id"]) && $array["result"]["message_id"] == 0) {
            return TelegramController::sendMessage($request, $bot_token);
        }

        return $response;
    }

    /**
     * Envía un grupo de medios a un chat de Telegram.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function sendMediaGroup($request, $bot_token)
    {
        $chat_id = $request["message"]["chat"]["id"];
        $media = $request["message"]["media"] ?? null;

        // Normalizar media: puede venir como arreglo o como JSON string
        if (is_string($media)) {
            $decoded = json_decode($media, true);
            $media = is_array($decoded) ? $decoded : $media;
        }

        // Aseguramos que la API reciba un JSON válido en el campo 'media' del formulario
        $mediaPayload = is_array($media) ? json_encode($media) : $media;

        $url = self::buildTelegramUrl($bot_token, 'sendMediaGroup', [
            'chat_id' => $chat_id,
        ]);

        return TelegramController::send($request, $url, 1, [
            'media' => $mediaPayload,
        ]);
    }

    /**
     * Envía un documento a un chat de Telegram.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function sendDocument($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'sendDocument', [
            'chat_id' => $request["message"]["chat"]["id"],
            'document' => $request["message"]["document"]
        ]);

        return TelegramController::send($request, $url);

    }
    /**
     * Fija un mensaje en un chat de Telegram.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function pinMessage($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'pinChatMessage', [
            'chat_id' => $request["message"]["chat"]["id"],
            'message_id' => $request["message"]["message_id"]
        ]);

        return TelegramController::send($request, $url);
    }

    /**
     * Elimina un mensaje de un chat de Telegram.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function deleteMessage($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'deleteMessage', [
            'chat_id' => $request["message"]["chat"]["id"],
            'message_id' => $request["message"]["id"]
        ]);

        return TelegramController::send($request, $url);
    }

    /**
     * Reenvía un mensaje de un chat a otro.
     *
     * @param array $request
     * @param string $bot_token
     * @return string
     */
    public static function forwardMessage($request, $bot_token)
    {
        // Construir la URL con parámetros para asegurar que existe la '?' antes de añadir '&parse_mode'
        $url = self::buildTelegramUrl($bot_token, 'forwardMessage', [
            'chat_id' => $request["message"]["chat"]["id"],
            'from_chat_id' => $request["message"]["from"]["id"],
            'message_id' => $request["message"]["message_id"],
        ]);

        // Enviar sin cuerpo adicional (los parámetros ya están en la URL)
        return TelegramController::send($request, $url);
    }

    public static function getBotInfo($bot_token)
    {
        $response = false;
        $url = "https://api.telegram.org/bot" .
            $bot_token .
            "/getMe";

        try {
            $response = Http::get($url);
            return $response->body();
        } catch (\Throwable $th) {
            //Log::error("TelegramController getBotInfo: " . $th->getTraceAsString());
        }

        return false;
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
            $response = Http::get($url);
            $json = $response->json();

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
            $response = Http::get($url);
            return $response->body();
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
            $response = Http::withOptions(['verify' => true, 'timeout' => 10])->get($url);
            if ($response->successful()) {
                return $response->body();
            }
            Log::warning("TelegramController getFileUrl HTTP status {$response->status()} for URL: {$url}");
        } catch (\Throwable $th) {
            Log::error("TelegramController getFileUrl error: " . $th->getMessage());
        }

        return false;
    }

    public static function getFile($filePath, $bot_token)
    {
        $url = "https://api.telegram.org/file/bot" .
            $bot_token .
            "/{$filePath}";

        $response = Http::get($url);
        return $response->body();
    }

    public static function exportFileLocally($fileId, $bot_token)
    {
        $response = TelegramController::getFileUrl($fileId, $bot_token);
        $response = json_decode($response, true);
        $filePath = $response["result"]["file_path"];
        $imageUrl = "https://api.telegram.org/file/bot{$bot_token}/{$filePath}";
        // Descargar y guardar la imagen localmente
        $response = Http::get($imageUrl);
        $imageContent = $response->body();
        $filename = FileController::getFileNameAsUnixTime("jpg", 1, "HOURS");
        Storage::disk('public')->put(FileController::$AUTODESTROY_DIR . "/" . $filename, $imageContent);


        $array = Str::of($filename)->explode('.')->toArray();
        return [
            'filename' => $array[0],
            'extension' => $array[1],
            'url' => request()->root() . FileController::$AUTODESTROY_DIR . "/" . $filename,
        ];
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
        Arr::sort($data_check_arr);
        $data_check_string = collect($data_check_arr)->implode("\n");

        $secret_key = hash('sha256', $botToken, true);
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);

        return hash_equals($hash, $check_hash);
    }
}
