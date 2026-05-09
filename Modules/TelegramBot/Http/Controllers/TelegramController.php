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
use Modules\Laravel\Services\BehaviorService;

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

    private static function sanitizeUrl(string $url): string
    {
        return preg_replace('#(https://api\.telegram\.org/(?:file/)?bot)[^/]+(/.*)#', '$1[REDACTED]$2', $url);
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
        $safeUrl = self::sanitizeUrl($url);
        try {
            // si es DEMO escribimos en la consola y retornamos message_id -1
            if (isset($request["demo"]) && $request["demo"] == true) {
                var_dump([
                    "url" => $url,
                    "message" => $request["message"],
                ]);

                return response()->json([
                    'result' => [
                        'message_id' => -1,
                    ],
                ])->getContent();
            }

            $url .= "&parse_mode=" . ($request["message"]["parse_mode"] ?? "Markdown");
            if (isset($request["message"]["reply_to_message_id"]) && $request["message"]["reply_to_message_id"] != "") {
                $url .= "&reply_to_message_id={$request["message"]["reply_to_message_id"]}";
            }
            if (isset($request["message"]["message_thread_id"]) && $request["message"]["message_thread_id"] != "") {
                $url .= "&message_thread_id={$request["message"]["message_thread_id"]}";
            }
            if (isset($request["message"]["reply_markup"]) && $request["message"]["reply_markup"] != "") {
                $reply_markup = urlencode($request["message"]["reply_markup"]);
                $url .= "&reply_markup={$reply_markup}";
            }

            $options = [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                // ensure we don't hang indefinitely
                'timeout' => 10,
            ];
            $http = Http::withOptions($options);
            if ($data) {
                $response = $http->asForm()->post($url, $data);
            } else {
                $response = $http->post($url);
            }
            // log non-successful HTTP codes
            if (!$response->successful()) {
                Log::warning("⚠️ TelegramController send HTTP failure", [
                    'status' => $response->status(),
                    'url' => $safeUrl,
                    'body' => $response->body(),
                ]);
            }
            $body = $response->body();
            if (trim($body) === '') {
                Log::warning("⚠️ TelegramController send empty body", ['url' => $safeUrl]);
                // return a generic failure JSON so callers can handle it
                return json_encode(['ok' => false, 'description' => 'empty response from telegram']);
            }
            // attempt to validate json
            json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("⚠️ TelegramController send invalid JSON", ['body' => $body, 'url' => $safeUrl]);
                // still return the raw body so higher layers can inspect
            }
            return $body;

        } catch (\Throwable $th) {
            $array = TelegramController::analizeUrl($url);
            $method = $array["path_parts"][count($array["path_parts"]) - 1];
            Log::error("🆘 TelegramController {$method} attempt {$attempt}, CODE: {$th->getCode()}, line {$th->getLine()}, URL: {$safeUrl}, Message: {$th->getMessage()}");
            //Log::error("🆘 TelegramController TraceAsString: " . $th->getTraceAsString());

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

        self::autodestroyMessage($bot_token, $response, $autodestroy);

        return $response;
    }

    private static function autodestroyMessage($bot_token, $response, $autodestroy)
    {
        if ($autodestroy > 0) {
            $array = json_decode($response, true);
            //Log::info("✅ TelegramController sendMessage array: " . json_encode($array));

            if (isset($array["result"]["message_id"]) && $array["result"]["message_id"] > -1) {
                DeleteTelegramMessage::dispatch(
                    (string) $bot_token,
                    (int) $array["result"]["chat"]["id"],
                    (int) $array["result"]["message_id"]
                )->delay(now()->addMinutes((int) $autodestroy));
            }
        }
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
    public static function sendPhoto($request, $bot_token, $autodestroy = 0)
    {
        $url = self::buildTelegramUrl($bot_token, 'sendPhoto', [
            'chat_id' => $request["message"]["chat"]["id"],
            'photo' => $request["message"]["photo"],
            'caption' => $request["message"]["text"]
        ]);

        $response = TelegramController::send($request, $url);

        self::autodestroyMessage($bot_token, $response, $autodestroy);

        $array = json_decode($response, true);
        // if Telegram could not fetch the URL, try uploading ourselves
        if (
            isset($array['ok']) && $array['ok'] === false
            && isset($array['description'])
            && str_contains($array['description'], 'failed to get HTTP URL content')
        ) {
            Log::warning('⚠️ sendPhoto remote fetch failed; attempting manual upload', [
                'url' => self::sanitizeUrl($url),
                'description' => $array['description'],
                'photo' => $request['message']['photo'] ?? null,
            ]);

            $photoUrl = $request['message']['photo'] ?? null;
            // gather extra parameters that we want to keep (buttons, reply_to, etc.)
            $extras = ['caption' => $request['message']['text'] ?? ''];
            if (!empty($request['message']['reply_markup'])) {
                $extras['reply_markup'] = $request['message']['reply_markup'];
            }
            if (!empty($request['message']['reply_to_message_id'])) {
                $extras['reply_to_message_id'] = $request['message']['reply_to_message_id'];
            }
            $result = self::manualUpload(
                $bot_token,
                $request['message']['chat']['id'],
                $photoUrl,
                'photo',
                $extras
            );
            if ($result !== false) {
                return $result;
            }

            // if recovery fails, fall back to text
            return TelegramController::sendMessage($request, $bot_token);
        }

        if (!$array || !isset($array['result'])) {
            Log::warning('⚠️ sendPhoto unexpected response', [
                'url' => self::sanitizeUrl($url),
                'response' => $response,
                'request' => $request,
            ]);
            // fallback to sending plain text message so caller always has something
            return TelegramController::sendMessage($request, $bot_token);
        }

        if (isset($array['result']['message_id']) && $array['result']['message_id'] == 0) {
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

        $response = TelegramController::send($request, $url, 1, [
            'media' => $mediaPayload,
        ]);

        $array = json_decode($response, true);
        if (
            isset($array['ok']) && $array['ok'] === false
            && isset($array['description'])
            && str_contains($array['description'], 'failed to get HTTP URL content')
        ) {
            Log::warning('⚠️ sendMediaGroup remote fetch failed; attempting manual upload', [
                'url' => self::sanitizeUrl($url),
                'description' => $array['description'],
                'media' => $media,
            ]);

            // try to download each media item and resend multipart
            // also preserve extras (reply_markup / reply_to)
            $extras = [];
            if (!empty($request['message']['reply_markup'])) {
                $extras['reply_markup'] = $request['message']['reply_markup'];
            }
            if (!empty($request['message']['reply_to_message_id'])) {
                $extras['reply_to_message_id'] = $request['message']['reply_to_message_id'];
            }

            $multipart = Http::withOptions(['timeout' => 10]);
            $finalUrl = "https://api.telegram.org/bot{$bot_token}/sendMediaGroup";
            $form = array_merge(['chat_id' => $chat_id], $extras);
            foreach ($media as $idx => $item) {
                if (!empty($item['media']) && filter_var($item['media'], FILTER_VALIDATE_URL)) {
                    try {
                        $dl = Http::timeout(BehaviorService::timeout())->get($item['media']);
                        if ($dl->successful()) {
                            $contents = $dl->body();
                            $name = "media{$idx}." . (pathinfo(parse_url($item['media'], PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg');
                            $multipart = $multipart->attach("media{$idx}", $contents, $name);
                        }
                    } catch (\Throwable $th) {
                        Log::error('🆘 sendMediaGroup download error', ['message' => $th->getMessage()]);
                    }
                }
            }
            $retry = $multipart->asMultipart()->post($finalUrl, $form);
            if ($retry->successful()) {
                return $retry->body();
            }
            Log::warning('⚠️ sendMediaGroup manual upload failed', ['status' => $retry->status(), 'body' => $retry->body()]);
            return json_encode(['ok' => false, 'description' => 'manual sendMediaGroup failed']);
        }

        return $response;
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

        $response = TelegramController::send($request, $url);
        $array = json_decode($response, true);
        if (
            isset($array['ok']) && $array['ok'] === false
            && isset($array['description'])
            && str_contains($array['description'], 'failed to get HTTP URL content')
        ) {
            // collect extras similar to sendPhoto
            $extras = [];
            if (!empty($request['message']['reply_markup'])) {
                $extras['reply_markup'] = $request['message']['reply_markup'];
            }
            if (!empty($request['message']['reply_to_message_id'])) {
                $extras['reply_to_message_id'] = $request['message']['reply_to_message_id'];
            }

            $docUrl = $request['message']['document'] ?? null;
            $result = self::manualUpload(
                $bot_token,
                $request['message']['chat']['id'],
                $docUrl,
                'document',
                $extras
            );
            if ($result !== false) {
                return $result;
            }
            Log::warning('⚠️ sendDocument recovery failed');
            return json_encode(['ok' => false, 'description' => 'manual sendDocument failed']);
        }

        return $response;

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
            //Log::error("🆘 TelegramController getBotInfo: " . $th->getTraceAsString());
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
                "full_name" => "{$userId}",
                "full_info" => "{$userId}",
            ),
        );

        try {
            $response = Http::get($url);
            $json = $response->json();

            // Formando un text personalizado con los datos del usuario
            $text = "";
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
            //Log::error("🆘 TelegramController getUserInfo: " . $th->getTraceAsString());
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
            //Log::error("🆘 TelegramController getFileUrl: " . $th->getTraceAsString());
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
            Log::warning("⚠️ TelegramController getFileUrl HTTP status {$response->status()} for URL: " . self::sanitizeUrl($url));
        } catch (\Throwable $th) {
            Log::error("🆘 TelegramController getFileUrl error: " . $th->getMessage());
        }

        return false;
    }

    public static function getFile($filePath, $bot_token)
    {
        $url = "https://api.telegram.org/file/bot" .
            $bot_token .
            "/{$filePath}";

        $response = Http::get($url);

        if ($response->successful()) {
            return $response->body();
        }

        Log::error("🆘 TelegramController getFile: Fallo al descargar archivo de Telegram: " . self::sanitizeUrl($url));
        return null;
    }

    /**
     * Descarga un recurso remoto y lo sube al bot en un solo paso.
     *
     * @param string $bot_token
     * @param int $chat_id
     * @param string $fileUrl URL que Telegram no pudo alcanzar
     * @param string $fieldName nombre del campo en el formulario (photo/document/media)
     * @param array $extra parámetros adicionales para el formulario
     * @return string|false respuesta del API o false si la recuperación falló
     */
    private static function manualUpload($bot_token, $chat_id, $fileUrl, $fieldName, $extra = [], $uploadMethod = null)
    {
        if (!$fileUrl || !filter_var($fileUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        try {
            $dl = Http::timeout(BehaviorService::timeout())->get($fileUrl);
            if (!$dl->successful()) {
                Log::warning('⚠️ TelegramController manualUpload download failed', ['status' => $dl->status(), 'url' => $fileUrl]);
                return false;
            }
            $contents = $dl->body();
            $basename = pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_BASENAME) ?: 'file';
            $method = $uploadMethod ?? "send{$fieldName}";
            $uploadUrl = "https://api.telegram.org/bot{$bot_token}/{$method}";

            // filter extras: remove null entries
            foreach ($extra as $k => $v) {
                if ($v === null) {
                    unset($extra[$k]);
                    continue;
                }
                if ($k === 'reply_markup' && is_array($v)) {
                    $extra[$k] = json_encode($v);
                }
            }

            if (isset($extra['caption']) && !isset($extra['parse_mode'])) {
                $extra['parse_mode'] = 'Markdown';
            }

            $request = Http::attach($fieldName, $contents, $basename)->asMultipart();
            $form = array_merge(['chat_id' => $chat_id], $extra);
            $retry = $request->post($uploadUrl, $form);
            if ($retry->successful()) {
                return $retry->body();
            }
            Log::warning('⚠️ TelegramController manualUpload post failed', ['status' => $retry->status(), 'body' => $retry->body()]);
        } catch (\Throwable $th) {
            Log::error('🆘 TelegramController manualUpload exception', ['message' => $th->getMessage(), 'url' => $fileUrl]);
        }
        return false;
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
    // ===================== HELPERS INTERNOS =====================

    private static function apiPost($bot_token, $method, $params = [])
    {
        $url = self::buildTelegramUrl($bot_token, $method);
        try {
            $response = Http::withOptions(['timeout' => 10])->asForm()->post($url, $params);
            if (!$response->successful()) {
                Log::warning("⚠️ TelegramController {$method} HTTP failure", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
            return $response->body();
        } catch (\Throwable $th) {
            Log::error("🆘 TelegramController {$method} error: " . $th->getMessage());
            return json_encode(['ok' => false, 'description' => $th->getMessage()]);
        }
    }

    private static function apiGet($bot_token, $method, $params = [])
    {
        $url = self::buildTelegramUrl($bot_token, $method, $params);
        try {
            $response = Http::withOptions(['timeout' => 10])->get($url);
            if (!$response->successful()) {
                Log::warning("⚠️ TelegramController {$method} HTTP failure", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
            return $response->body();
        } catch (\Throwable $th) {
            Log::error("🆘 TelegramController {$method} error: " . $th->getMessage());
            return json_encode(['ok' => false, 'description' => $th->getMessage()]);
        }
    }

    /**
     * Lógica compartida para enviar medios con fallback de upload manual.
     *
     * @param string $telegramMethod  Nombre exacto del método Telegram (ej. 'sendVideo')
     * @param string $fieldKey        Clave dentro de $request['message'] con el archivo/URL
     */
    private static function sendMediaWithFallback($request, $bot_token, $telegramMethod, $fieldKey, $autodestroy = 0)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            $fieldKey => $request['message'][$fieldKey],
        ];

        foreach (['duration', 'width', 'height', 'title', 'performer', 'supports_streaming', 'thumbnail', 'emoji', 'length'] as $opt) {
            if (!empty($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        if (empty($params['caption']) && !empty($request['message']['caption'])) {
            $params['caption'] = $request['message']['caption'];
        }
        if (empty($params['caption']) && !empty($request['message']['text'])) {
            $params['caption'] = $request['message']['text'];
        }

        $url = self::buildTelegramUrl($bot_token, $telegramMethod, $params);
        $response = TelegramController::send($request, $url);

        self::autodestroyMessage($bot_token, $response, $autodestroy);

        $array = json_decode($response, true);
        if (
            isset($array['ok']) && $array['ok'] === false
            && isset($array['description'])
            && str_contains($array['description'], 'failed to get HTTP URL content')
        ) {
            $fileUrl = $request['message'][$fieldKey] ?? null;
            $extras = [];
            if (!empty($params['caption'])) {
                $extras['caption'] = $params['caption'];
            }
            if (!empty($request['message']['reply_markup'])) {
                $extras['reply_markup'] = $request['message']['reply_markup'];
            }
            if (!empty($request['message']['reply_to_message_id'])) {
                $extras['reply_to_message_id'] = $request['message']['reply_to_message_id'];
            }
            $result = self::manualUpload($bot_token, $request['message']['chat']['id'], $fileUrl, $fieldKey, $extras, $telegramMethod);
            if ($result !== false) {
                return $result;
            }
        }

        return $response;
    }

    // ===================== ENVÍO DE MEDIOS =====================

    public static function sendAudio($request, $bot_token, $autodestroy = 0)
    {
        return self::sendMediaWithFallback($request, $bot_token, 'sendAudio', 'audio', $autodestroy);
    }

    public static function sendVideo($request, $bot_token, $autodestroy = 0)
    {
        return self::sendMediaWithFallback($request, $bot_token, 'sendVideo', 'video', $autodestroy);
    }

    public static function sendAnimation($request, $bot_token, $autodestroy = 0)
    {
        return self::sendMediaWithFallback($request, $bot_token, 'sendAnimation', 'animation', $autodestroy);
    }

    public static function sendVoice($request, $bot_token, $autodestroy = 0)
    {
        return self::sendMediaWithFallback($request, $bot_token, 'sendVoice', 'voice', $autodestroy);
    }

    public static function sendVideoNote($request, $bot_token, $autodestroy = 0)
    {
        return self::sendMediaWithFallback($request, $bot_token, 'sendVideoNote', 'video_note', $autodestroy);
    }

    public static function sendSticker($request, $bot_token, $autodestroy = 0)
    {
        return self::sendMediaWithFallback($request, $bot_token, 'sendSticker', 'sticker', $autodestroy);
    }

    // ===================== OTROS TIPOS DE MENSAJE =====================

    public static function sendLocation($request, $bot_token, $autodestroy = 0)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'latitude' => $request['message']['latitude'],
            'longitude' => $request['message']['longitude'],
        ];
        foreach (['horizontal_accuracy', 'live_period', 'heading', 'proximity_alert_radius'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        $url = self::buildTelegramUrl($bot_token, 'sendLocation', $params);
        $response = TelegramController::send($request, $url);
        self::autodestroyMessage($bot_token, $response, $autodestroy);
        return $response;
    }

    public static function editMessageLiveLocation($request, $bot_token)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'message_id' => $request['message']['message_id'],
            'latitude' => $request['message']['latitude'],
            'longitude' => $request['message']['longitude'],
        ];
        foreach (['horizontal_accuracy', 'heading', 'proximity_alert_radius'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        $url = self::buildTelegramUrl($bot_token, 'editMessageLiveLocation', $params);
        return TelegramController::send($request, $url);
    }

    public static function stopMessageLiveLocation($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'stopMessageLiveLocation', [
            'chat_id' => $request['message']['chat']['id'],
            'message_id' => $request['message']['message_id'],
        ]);
        return TelegramController::send($request, $url);
    }

    public static function sendVenue($request, $bot_token, $autodestroy = 0)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'latitude' => $request['message']['latitude'],
            'longitude' => $request['message']['longitude'],
            'title' => $request['message']['title'],
            'address' => $request['message']['address'],
        ];
        foreach (['foursquare_id', 'foursquare_type', 'google_place_id', 'google_place_type'] as $opt) {
            if (!empty($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        $url = self::buildTelegramUrl($bot_token, 'sendVenue', $params);
        $response = TelegramController::send($request, $url);
        self::autodestroyMessage($bot_token, $response, $autodestroy);
        return $response;
    }

    public static function sendContact($request, $bot_token, $autodestroy = 0)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'phone_number' => $request['message']['phone_number'],
            'first_name' => $request['message']['first_name'],
        ];
        foreach (['last_name', 'vcard'] as $opt) {
            if (!empty($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        $url = self::buildTelegramUrl($bot_token, 'sendContact', $params);
        $response = TelegramController::send($request, $url);
        self::autodestroyMessage($bot_token, $response, $autodestroy);
        return $response;
    }

    /**
     * $request['message']['question'] y $request['message']['options'] (array de InputPollOption) son requeridos.
     */
    public static function sendPoll($request, $bot_token, $autodestroy = 0)
    {
        $options = $request['message']['options'];
        if (is_array($options)) {
            $options = json_encode($options);
        }
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'question' => $request['message']['question'],
            'options' => $options,
        ];
        foreach (['is_anonymous', 'type', 'allows_multiple_answers', 'correct_option_id', 'explanation', 'is_closed', 'open_period', 'close_date'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        $url = self::buildTelegramUrl($bot_token, 'sendPoll', $params);
        $response = TelegramController::send($request, $url);
        self::autodestroyMessage($bot_token, $response, $autodestroy);
        return $response;
    }

    public static function sendDice($request, $bot_token, $autodestroy = 0)
    {
        $params = ['chat_id' => $request['message']['chat']['id']];
        if (!empty($request['message']['emoji'])) {
            $params['emoji'] = $request['message']['emoji'];
        }
        $url = self::buildTelegramUrl($bot_token, 'sendDice', $params);
        $response = TelegramController::send($request, $url);
        self::autodestroyMessage($bot_token, $response, $autodestroy);
        return $response;
    }

    /**
     * $request['message']['action'] requerido (typing, upload_photo, record_video, etc.).
     */
    public static function sendChatAction($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'sendChatAction', [
            'chat_id' => $request['message']['chat']['id'],
            'action' => $request['message']['action'],
        ]);
        return TelegramController::send($request, $url);
    }

    /**
     * $request['message']['message_id'] y $request['message']['reaction'] (array JSON) requeridos.
     */
    public static function setMessageReaction($request, $bot_token)
    {
        $reaction = $request['message']['reaction'] ?? [];
        if (is_array($reaction)) {
            $reaction = json_encode($reaction);
        }
        return self::apiPost($bot_token, 'setMessageReaction', [
            'chat_id' => $request['message']['chat']['id'],
            'message_id' => $request['message']['message_id'],
            'reaction' => $reaction,
            'is_big' => $request['message']['is_big'] ?? false,
        ]);
    }

    // ===================== EDICIÓN DE MENSAJES =====================

    public static function editMessageCaption($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'editMessageCaption', [
            'chat_id' => $request['message']['chat']['id'],
            'message_id' => $request['message']['message_id'],
            'caption' => $request['message']['caption'] ?? $request['message']['text'] ?? '',
        ]);
        return TelegramController::send($request, $url);
    }

    /**
     * $request['message']['media'] debe ser array o JSON con InputMedia (type, media, caption...).
     */
    public static function editMessageMedia($request, $bot_token)
    {
        $media = $request['message']['media'];
        if (is_array($media)) {
            $media = json_encode($media);
        }
        $url = self::buildTelegramUrl($bot_token, 'editMessageMedia', [
            'chat_id' => $request['message']['chat']['id'],
            'message_id' => $request['message']['message_id'],
            'media' => $media,
        ]);
        return TelegramController::send($request, $url);
    }

    public static function editMessageReplyMarkup($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'editMessageReplyMarkup', [
            'chat_id' => $request['message']['chat']['id'],
            'message_id' => $request['message']['message_id'],
        ]);
        return TelegramController::send($request, $url);
    }

    public static function stopPoll($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'stopPoll', [
            'chat_id' => $request['message']['chat']['id'],
            'message_id' => $request['message']['message_id'],
        ]);
        return TelegramController::send($request, $url);
    }

    // ===================== GESTIÓN DE MENSAJES =====================

    public static function unpinChatMessage($request, $bot_token)
    {
        $params = ['chat_id' => $request['message']['chat']['id']];
        if (!empty($request['message']['message_id'])) {
            $params['message_id'] = $request['message']['message_id'];
        }
        $url = self::buildTelegramUrl($bot_token, 'unpinChatMessage', $params);
        return TelegramController::send($request, $url);
    }

    public static function unpinAllChatMessages($request, $bot_token)
    {
        $url = self::buildTelegramUrl($bot_token, 'unpinAllChatMessages', [
            'chat_id' => $request['message']['chat']['id'],
        ]);
        return TelegramController::send($request, $url);
    }

    /**
     * $request['message']['message_ids'] debe ser array de IDs (máx. 100).
     */
    public static function deleteMessages($request, $bot_token)
    {
        $ids = $request['message']['message_ids'];
        if (is_array($ids)) {
            $ids = json_encode($ids);
        }
        return self::apiPost($bot_token, 'deleteMessages', [
            'chat_id' => $request['message']['chat']['id'],
            'message_ids' => $ids,
        ]);
    }

    /**
     * $request['message']['message_ids'] debe ser array de IDs (máx. 100).
     */
    public static function forwardMessages($request, $bot_token)
    {
        $ids = $request['message']['message_ids'];
        if (is_array($ids)) {
            $ids = json_encode($ids);
        }
        return self::apiPost($bot_token, 'forwardMessages', [
            'chat_id' => $request['message']['chat']['id'],
            'from_chat_id' => $request['message']['from_chat_id'],
            'message_ids' => $ids,
        ]);
    }

    /**
     * $request['message']['message_ids'] debe ser array de IDs (máx. 100).
     */
    public static function copyMessages($request, $bot_token)
    {
        $ids = $request['message']['message_ids'];
        if (is_array($ids)) {
            $ids = json_encode($ids);
        }
        return self::apiPost($bot_token, 'copyMessages', [
            'chat_id' => $request['message']['chat']['id'],
            'from_chat_id' => $request['message']['from_chat_id'],
            'message_ids' => $ids,
        ]);
    }

    // ===================== GESTIÓN DE MIEMBROS DEL CHAT =====================

    public static function getChatAdministrators($chat_id, $bot_token)
    {
        return self::apiGet($bot_token, 'getChatAdministrators', ['chat_id' => $chat_id]);
    }

    public static function getChatMemberCount($chat_id, $bot_token)
    {
        return self::apiGet($bot_token, 'getChatMemberCount', ['chat_id' => $chat_id]);
    }

    public static function getChatMember($chat_id, $user_id, $bot_token)
    {
        return self::apiGet($bot_token, 'getChatMember', [
            'chat_id' => $chat_id,
            'user_id' => $user_id,
        ]);
    }

    public static function banChatMember($request, $bot_token)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'user_id' => $request['message']['user_id'],
        ];
        foreach (['until_date', 'revoke_messages'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'banChatMember', $params);
    }

    public static function unbanChatMember($request, $bot_token)
    {
        return self::apiPost($bot_token, 'unbanChatMember', [
            'chat_id' => $request['message']['chat']['id'],
            'user_id' => $request['message']['user_id'],
            'only_if_banned' => $request['message']['only_if_banned'] ?? false,
        ]);
    }

    /**
     * $request['message']['permissions'] debe ser array o JSON con campos ChatPermissions.
     */
    public static function restrictChatMember($request, $bot_token)
    {
        $permissions = $request['message']['permissions'];
        if (is_array($permissions)) {
            $permissions = json_encode($permissions);
        }
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'user_id' => $request['message']['user_id'],
            'permissions' => $permissions,
        ];
        if (isset($request['message']['until_date'])) {
            $params['until_date'] = $request['message']['until_date'];
        }
        return self::apiPost($bot_token, 'restrictChatMember', $params);
    }

    public static function promoteChatMember($request, $bot_token)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'user_id' => $request['message']['user_id'],
        ];
        foreach ([
            'is_anonymous',
            'can_manage_chat',
            'can_post_messages',
            'can_edit_messages',
            'can_delete_messages',
            'can_manage_video_chats',
            'can_restrict_members',
            'can_promote_members',
            'can_change_info',
            'can_invite_users',
            'can_pin_messages',
            'can_manage_topics',
            'can_post_stories',
            'can_edit_stories',
            'can_delete_stories',
        ] as $flag) {
            if (isset($request['message'][$flag])) {
                $params[$flag] = $request['message'][$flag];
            }
        }
        return self::apiPost($bot_token, 'promoteChatMember', $params);
    }

    public static function setChatAdministratorCustomTitle($request, $bot_token)
    {
        return self::apiPost($bot_token, 'setChatAdministratorCustomTitle', [
            'chat_id' => $request['message']['chat']['id'],
            'user_id' => $request['message']['user_id'],
            'custom_title' => $request['message']['custom_title'],
        ]);
    }

    public static function banChatSenderChat($request, $bot_token)
    {
        return self::apiPost($bot_token, 'banChatSenderChat', [
            'chat_id' => $request['message']['chat']['id'],
            'sender_chat_id' => $request['message']['sender_chat_id'],
        ]);
    }

    public static function unbanChatSenderChat($request, $bot_token)
    {
        return self::apiPost($bot_token, 'unbanChatSenderChat', [
            'chat_id' => $request['message']['chat']['id'],
            'sender_chat_id' => $request['message']['sender_chat_id'],
        ]);
    }

    // ===================== CONFIGURACIÓN DEL CHAT =====================

    /**
     * $request['message']['permissions'] debe ser array o JSON con ChatPermissions.
     */
    public static function setChatPermissions($request, $bot_token)
    {
        $permissions = $request['message']['permissions'];
        if (is_array($permissions)) {
            $permissions = json_encode($permissions);
        }
        return self::apiPost($bot_token, 'setChatPermissions', [
            'chat_id' => $request['message']['chat']['id'],
            'permissions' => $permissions,
        ]);
    }

    public static function setChatTitle($request, $bot_token)
    {
        return self::apiPost($bot_token, 'setChatTitle', [
            'chat_id' => $request['message']['chat']['id'],
            'title' => $request['message']['title'],
        ]);
    }

    public static function setChatDescription($request, $bot_token)
    {
        return self::apiPost($bot_token, 'setChatDescription', [
            'chat_id' => $request['message']['chat']['id'],
            'description' => $request['message']['description'] ?? '',
        ]);
    }

    public static function leaveChat($chat_id, $bot_token)
    {
        return self::apiPost($bot_token, 'leaveChat', ['chat_id' => $chat_id]);
    }

    public static function exportChatInviteLink($chat_id, $bot_token)
    {
        return self::apiPost($bot_token, 'exportChatInviteLink', ['chat_id' => $chat_id]);
    }

    public static function createChatInviteLink($request, $bot_token)
    {
        $params = ['chat_id' => $request['message']['chat']['id']];
        foreach (['name', 'expire_date', 'member_limit', 'creates_join_request'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'createChatInviteLink', $params);
    }

    public static function editChatInviteLink($request, $bot_token)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'invite_link' => $request['message']['invite_link'],
        ];
        foreach (['name', 'expire_date', 'member_limit', 'creates_join_request'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'editChatInviteLink', $params);
    }

    public static function revokeChatInviteLink($request, $bot_token)
    {
        return self::apiPost($bot_token, 'revokeChatInviteLink', [
            'chat_id' => $request['message']['chat']['id'],
            'invite_link' => $request['message']['invite_link'],
        ]);
    }

    public static function approveChatJoinRequest($request, $bot_token)
    {
        return self::apiPost($bot_token, 'approveChatJoinRequest', [
            'chat_id' => $request['message']['chat']['id'],
            'user_id' => $request['message']['user_id'],
        ]);
    }

    public static function declineChatJoinRequest($request, $bot_token)
    {
        return self::apiPost($bot_token, 'declineChatJoinRequest', [
            'chat_id' => $request['message']['chat']['id'],
            'user_id' => $request['message']['user_id'],
        ]);
    }

    // ===================== CALLBACK QUERIES / INLINE =====================

    /**
     * $request['message']['callback_query_id'] requerido.
     */
    public static function answerCallbackQuery($request, $bot_token)
    {
        $params = ['callback_query_id' => $request['message']['callback_query_id']];
        foreach (['text', 'show_alert', 'url', 'cache_time'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'answerCallbackQuery', $params);
    }

    /**
     * $request['message']['inline_query_id'] y $request['message']['results'] (array JSON) requeridos.
     */
    public static function answerInlineQuery($request, $bot_token)
    {
        $results = $request['message']['results'];
        if (is_array($results)) {
            $results = json_encode($results);
        }
        $params = [
            'inline_query_id' => $request['message']['inline_query_id'],
            'results' => $results,
        ];
        foreach (['cache_time', 'is_personal', 'next_offset', 'button'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'answerInlineQuery', $params);
    }

    // ===================== COMANDOS E INFO DEL BOT =====================

    /**
     * @param array       $commands      [{command, description}, ...]
     * @param array|null  $scope         Objeto BotCommandScope opcional
     * @param string|null $language_code Código IETF opcional
     */
    public static function setMyCommands($commands, $bot_token, $scope = null, $language_code = null)
    {
        $params = ['commands' => is_array($commands) ? json_encode($commands) : $commands];
        if ($scope !== null) {
            $params['scope'] = is_array($scope) ? json_encode($scope) : $scope;
        }
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiPost($bot_token, 'setMyCommands', $params);
    }

    public static function getMyCommands($bot_token, $scope = null, $language_code = null)
    {
        $params = [];
        if ($scope !== null) {
            $params['scope'] = is_array($scope) ? json_encode($scope) : $scope;
        }
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiGet($bot_token, 'getMyCommands', $params);
    }

    public static function deleteMyCommands($bot_token, $scope = null, $language_code = null)
    {
        $params = [];
        if ($scope !== null) {
            $params['scope'] = is_array($scope) ? json_encode($scope) : $scope;
        }
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiPost($bot_token, 'deleteMyCommands', $params);
    }

    public static function setMyName($bot_token, $name, $language_code = null)
    {
        $params = ['name' => $name];
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiPost($bot_token, 'setMyName', $params);
    }

    public static function getMyName($bot_token, $language_code = null)
    {
        $params = [];
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiGet($bot_token, 'getMyName', $params);
    }

    public static function setMyDescription($bot_token, $description, $language_code = null)
    {
        $params = ['description' => $description];
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiPost($bot_token, 'setMyDescription', $params);
    }

    public static function getMyDescription($bot_token, $language_code = null)
    {
        $params = [];
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiGet($bot_token, 'getMyDescription', $params);
    }

    public static function setMyShortDescription($bot_token, $short_description, $language_code = null)
    {
        $params = ['short_description' => $short_description];
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiPost($bot_token, 'setMyShortDescription', $params);
    }

    public static function getMyShortDescription($bot_token, $language_code = null)
    {
        $params = [];
        if ($language_code !== null) {
            $params['language_code'] = $language_code;
        }
        return self::apiGet($bot_token, 'getMyShortDescription', $params);
    }

    public static function setMyDefaultAdministratorRights($bot_token, $rights = null, $for_channels = false)
    {
        $params = ['for_channels' => $for_channels];
        if ($rights !== null) {
            $params['rights'] = is_array($rights) ? json_encode($rights) : $rights;
        }
        return self::apiPost($bot_token, 'setMyDefaultAdministratorRights', $params);
    }

    public static function getMyDefaultAdministratorRights($bot_token, $for_channels = false)
    {
        return self::apiGet($bot_token, 'getMyDefaultAdministratorRights', ['for_channels' => $for_channels]);
    }

    public static function setChatMenuButton($bot_token, $chat_id = null, $menu_button = null)
    {
        $params = [];
        if ($chat_id !== null) {
            $params['chat_id'] = $chat_id;
        }
        if ($menu_button !== null) {
            $params['menu_button'] = is_array($menu_button) ? json_encode($menu_button) : $menu_button;
        }
        return self::apiPost($bot_token, 'setChatMenuButton', $params);
    }

    public static function getChatMenuButton($bot_token, $chat_id = null)
    {
        $params = [];
        if ($chat_id !== null) {
            $params['chat_id'] = $chat_id;
        }
        return self::apiGet($bot_token, 'getChatMenuButton', $params);
    }

    public static function getUserChatBoosts($chat_id, $user_id, $bot_token)
    {
        return self::apiGet($bot_token, 'getUserChatBoosts', [
            'chat_id' => $chat_id,
            'user_id' => $user_id,
        ]);
    }

    // ===================== STICKERS =====================

    public static function sendStickers($request, $bot_token, $autodestroy = 0)
    {
        return self::sendSticker($request, $bot_token, $autodestroy);
    }

    public static function getStickerSet($name, $bot_token)
    {
        return self::apiGet($bot_token, 'getStickerSet', ['name' => $name]);
    }

    public static function getCustomEmojiStickers($custom_emoji_ids, $bot_token)
    {
        $ids = is_array($custom_emoji_ids) ? json_encode($custom_emoji_ids) : $custom_emoji_ids;
        return self::apiPost($bot_token, 'getCustomEmojiStickers', ['custom_emoji_ids' => $ids]);
    }

    // ===================== FORUM TOPICS =====================

    public static function getForumTopicIconStickers($bot_token)
    {
        return self::apiGet($bot_token, 'getForumTopicIconStickers');
    }

    /**
     * $request['message']['name'] requerido.
     */
    public static function createForumTopic($request, $bot_token)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'name' => $request['message']['name'],
        ];
        foreach (['icon_color', 'icon_custom_emoji_id'] as $opt) {
            if (!empty($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'createForumTopic', $params);
    }

    public static function editForumTopic($request, $bot_token)
    {
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'message_thread_id' => $request['message']['message_thread_id'],
        ];
        foreach (['name', 'icon_custom_emoji_id'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'editForumTopic', $params);
    }

    public static function closeForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'closeForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
            'message_thread_id' => $request['message']['message_thread_id'],
        ]);
    }

    public static function reopenForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'reopenForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
            'message_thread_id' => $request['message']['message_thread_id'],
        ]);
    }

    public static function deleteForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'deleteForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
            'message_thread_id' => $request['message']['message_thread_id'],
        ]);
    }

    public static function unpinAllForumTopicMessages($request, $bot_token)
    {
        return self::apiPost($bot_token, 'unpinAllForumTopicMessages', [
            'chat_id' => $request['message']['chat']['id'],
            'message_thread_id' => $request['message']['message_thread_id'],
        ]);
    }

    public static function editGeneralForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'editGeneralForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
            'name' => $request['message']['name'],
        ]);
    }

    public static function closeGeneralForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'closeGeneralForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
        ]);
    }

    public static function reopenGeneralForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'reopenGeneralForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
        ]);
    }

    public static function hideGeneralForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'hideGeneralForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
        ]);
    }

    public static function unhideGeneralForumTopic($request, $bot_token)
    {
        return self::apiPost($bot_token, 'unhideGeneralForumTopic', [
            'chat_id' => $request['message']['chat']['id'],
        ]);
    }

    // ===================== PAGOS =====================

    public static function sendInvoice($request, $bot_token, $autodestroy = 0)
    {
        $prices = $request['message']['prices'];
        if (is_array($prices)) {
            $prices = json_encode($prices);
        }
        $params = [
            'chat_id' => $request['message']['chat']['id'],
            'title' => $request['message']['title'],
            'description' => $request['message']['description'],
            'payload' => $request['message']['payload'],
            'provider_token' => $request['message']['provider_token'] ?? '',
            'currency' => $request['message']['currency'],
            'prices' => $prices,
        ];
        foreach ([
            'max_tip_amount',
            'suggested_tip_amounts',
            'start_parameter',
            'provider_data',
            'photo_url',
            'photo_size',
            'photo_width',
            'photo_height',
            'need_name',
            'need_phone_number',
            'need_email',
            'need_shipping_address',
            'send_phone_number_to_provider',
            'send_email_to_provider',
            'is_flexible'
        ] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        $url = self::buildTelegramUrl($bot_token, 'sendInvoice', $params);
        $response = TelegramController::send($request, $url);
        self::autodestroyMessage($bot_token, $response, $autodestroy);
        return $response;
    }

    public static function answerShippingQuery($request, $bot_token)
    {
        $params = [
            'shipping_query_id' => $request['message']['shipping_query_id'],
            'ok' => $request['message']['ok'],
        ];
        if (!empty($request['message']['shipping_options'])) {
            $opts = $request['message']['shipping_options'];
            $params['shipping_options'] = is_array($opts) ? json_encode($opts) : $opts;
        }
        if (!empty($request['message']['error_message'])) {
            $params['error_message'] = $request['message']['error_message'];
        }
        return self::apiPost($bot_token, 'answerShippingQuery', $params);
    }

    public static function answerPreCheckoutQuery($request, $bot_token)
    {
        $params = [
            'pre_checkout_query_id' => $request['message']['pre_checkout_query_id'],
            'ok' => $request['message']['ok'],
        ];
        if (!empty($request['message']['error_message'])) {
            $params['error_message'] = $request['message']['error_message'];
        }
        return self::apiPost($bot_token, 'answerPreCheckoutQuery', $params);
    }

    // ===================== JUEGOS =====================

    public static function sendGame($request, $bot_token, $autodestroy = 0)
    {
        $url = self::buildTelegramUrl($bot_token, 'sendGame', [
            'chat_id' => $request['message']['chat']['id'],
            'game_short_name' => $request['message']['game_short_name'],
        ]);
        $response = TelegramController::send($request, $url);
        self::autodestroyMessage($bot_token, $response, $autodestroy);
        return $response;
    }

    public static function setGameScore($request, $bot_token)
    {
        $params = [
            'user_id' => $request['message']['user_id'],
            'score' => $request['message']['score'],
        ];
        foreach (['force', 'disable_edit_message', 'chat_id', 'message_id', 'inline_message_id'] as $opt) {
            if (isset($request['message'][$opt])) {
                $params[$opt] = $request['message'][$opt];
            }
        }
        return self::apiPost($bot_token, 'setGameScore', $params);
    }

    public static function getGameHighScores($user_id, $bot_token, $chat_id = null, $message_id = null, $inline_message_id = null)
    {
        $params = ['user_id' => $user_id];
        if ($chat_id !== null) {
            $params['chat_id'] = $chat_id;
            $params['message_id'] = $message_id;
        }
        if ($inline_message_id !== null) {
            $params['inline_message_id'] = $inline_message_id;
        }
        return self::apiGet($bot_token, 'getGameHighScores', $params);
    }

    // ===================== WEBHOOK / UPDATES =====================

    /**
     * @param string $webhook_url URL HTTPS pública del webhook
     * @param array  $extra       Parámetros opcionales: certificate, ip_address, max_connections,
     *                            allowed_updates, drop_pending_updates, secret_token
     */
    public static function setWebhook($webhook_url, $bot_token, $extra = [])
    {
        return self::apiPost($bot_token, 'setWebhook', array_merge(['url' => $webhook_url], $extra));
    }

    public static function deleteWebhook($bot_token, $drop_pending_updates = false)
    {
        return self::apiPost($bot_token, 'deleteWebhook', [
            'drop_pending_updates' => $drop_pending_updates,
        ]);
    }

    public static function getWebhookInfo($bot_token)
    {
        return self::apiGet($bot_token, 'getWebhookInfo');
    }

    /**
     * @param array $params Opcionales: offset, limit, timeout, allowed_updates
     */
    public static function getUpdates($bot_token, $params = [])
    {
        return self::apiGet($bot_token, 'getUpdates', $params);
    }

    public static function logOut($bot_token)
    {
        return self::apiPost($bot_token, 'logOut');
    }

    public static function close($bot_token)
    {
        return self::apiPost($bot_token, 'close');
    }

    // ===================== LOGIN / AUTH =====================

    public function loginCallback(Request $request)
    {
        $bot_token = $request->attributes->get('bot_token');
        $auth_data = $request->all();

        if (!$this->checkTelegramAuthorization($auth_data, $bot_token)) {
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 TelegramController loginCallback !checkTelegramAuthorization: " . json_encode($bot_token) . json_encode($auth_data));
            return redirect('/')->with('error', 'Fallo de integridad.');
        }
        if (env("DEBUG_MODE", false))
            Log::debug("🐞 TelegramController loginCallback checkTelegramAuthorization OK: " . json_encode($bot_token) . json_encode($auth_data));

        // 2. Obtener el file_path de la foto de perfil (sin descargar el archivo)
        $avatarPath = null;
        try {
            // Obtenemos la lista de fotos del usuario
            $photos = self::getUserPhotos($auth_data['id'], $bot_token);
            if (!empty($photos) && isset($photos[0][0]['file_id'])) {
                $fileId = $photos[0][0]['file_id'];

                // PASO B: Consultar a Telegram dónde está ese archivo físicamente
                // Usamos getFileUrl porque este SÍ llama a 'botTOKEN/getFile'
                $fileResponse = json_decode(self::getFileUrl($fileId, $bot_token), true);

                if (isset($fileResponse['ok']) && $fileResponse['ok']) {
                    // Esto nos dará algo como "userphotos/file_5.jpg"
                    $avatarPath = $fileResponse['result']['file_path'];
                }
            }
        } catch (\Exception $e) {
            Log::error("🆘 TelegramController loginCallback: Error obteniendo avatar: " . $e->getMessage());
        }

        // En lugar de base de datos, guardamos en la sesión de Laravel
        session([
            'telegram_user' => [
                'user_id' => $auth_data['id'],
                'name' => $auth_data['first_name'] . ' ' . ($auth_data['last_name'] ?? ''),
                'username' => $auth_data['username'] ?? null,
                'photo_url' => $avatarPath, // Guardamos solo la ruta: "userphotos/file_5.jpg"
            ]
        ]);

        // Opcional: Si quieres usar el sistema de Auth de Laravel sin DB (Login virtual)
        // $user = new \App\Models\User(['id' => $auth_data['id'], 'name' => $auth_data['first_name']]);
        // auth()->login($user);

        return redirect()->intended('/dashboard');
    }

    protected function checkTelegramAuthorization($auth_data, $bot_token)
    {
        //Log::error("🆘 TelegramController checkAuthorization: " . json_encode($auth_data));
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

        if (!isset($auth_data['hash'])) {
            return false;
        }

        $check_hash = $auth_data['hash'];

        // 1. Extraer el hash y limpiar datos nulos
        $data_check_arr = collect($auth_data)
            ->except(['hash']) // Quitamos el hash
            ->filter()         // Quitamos valores nulos/vacíos
            ->map(function ($value, $key) {
                // Importante: Telegram envía las URLs de fotos sin escapar las barras
                // Nos aseguramos de que el valor sea el string puro
                return $key . '=' . $value;
            })
            ->sort() // Ordenar alfabéticamente los strings "key=value"
            ->values();

        // 2. Crear el string de verificación con saltos de línea
        $data_check_string = $data_check_arr->implode("\n");

        // 3. Generar la clave secreta (SHA256 del Bot Token en binario)
        $secret_key = hash('sha256', $bot_token, true);

        // 4. Calcular el HMAC
        $hash = hash_hmac('sha256', $data_check_string, $secret_key);

        // DEBUG para comparar (Solo en desarrollo)
        if (env("DEBUG_MODE", false)) {
            Log::debug("🐞 TelegramController checkTelegramAuthorization Check String:\n" . $data_check_string);
            Log::debug("🐞 TelegramController checkTelegramAuthorization Calculated: $hash vs Original: $check_hash");
        }

        return hash_equals($hash, $check_hash);
    }

    public function proxyAvatar($bot_token, $filePath = null)
    {
        if (!$filePath)
            abort(404);

        // LLAMAMOS AL NUEVO MÉTODO
        $content = self::getFile($filePath, $bot_token);

        if (!$content) {
            abort(404, "No se pudo obtener el contenido del avatar.");
        }

        return response($content)
            ->header('Content-Type', 'image/jpeg') // Telegram suele enviar jpg
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
