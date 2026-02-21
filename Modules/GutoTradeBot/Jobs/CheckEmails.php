<?php

namespace Modules\GutoTradeBot\Jobs;

use DOMDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Webklex\IMAP\Facades\Client;
use Carbon\Carbon;
use Modules\GutoTradeBot\Http\Controllers\GutoTradeBotController;
use Modules\Laravel\Http\Controllers\GraphsController;
use Modules\Laravel\Http\Controllers\FileController;
use Illuminate\Support\Facades\Log;
use Modules\GutoTradeBot\Entities\Moneys;
use Modules\Laravel\Http\Controllers\TextController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Entities\TelegramBots;

class CheckEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    private function getAmount($valor)
    {
        // Eliminar caracteres no deseados y espacios
        $limpio = preg_replace('/[^\d,.-]/', '', $valor);
        // Reemplazar comas por puntos si es necesario (para decimales)
        $limpio = str_replace(',', '.', $limpio);
        // Extraer el símbolo de moneda
        $moneda = 'EUR';
        if (strpos($valor, '$') !== false) {
            $moneda = 'USD';
        }

        // Convertir a número float
        $cantidad = (float) $limpio;

        // Formatear con 2 decimales
        $cantidadFormateada = number_format($cantidad, 2, '.', '');

        return array(
            'amount' => $cantidadFormateada,
            'coin' => $moneda
        );
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Carbon::setLocale('en');
        $tc = new TextController();

        $bots = TelegramBots::whereJsonContainsKey('data->email')->get();
        foreach ($bots as $tenant) {
            $client = Client::make([
                'host' => $tenant->data["email"]["host"],     // ej: 'mail.micalme.com'
                'port' => $tenant->data["email"]["port"] ?? 993,     // ej: 993
                'encryption' => $tenant->data["email"]["encryption"] ?? 'ssl', // ej: 'ssl' o 'tls'
                'validate_cert' => $tenant->data["email"]["cert"],
                'username' => $tenant->data["email"]["username"],     // ej: 'guto@micalme.com'
                'password' => $tenant->data["email"]["password"], // La contraseña guardada
                'protocol' => 'imap'
            ]);

            app()->instance('active_bot', $tenant);
            $bot = new GutoTradeBotController();

            try {
                $client->connect();
                // Abrir la bandeja de entrada
                $inbox = $client->getFolder('INBOX');

                // Obtener correos no leídos
                //$messages = $inbox->query()->all()->get();
                $messages = $inbox->query()->unseen()->get();
                foreach ($messages as $message) {
                    /*
                    // Metadatos básicos
                    $messageId = $message->getUid(); // UID del mensaje
                    $subject = $message->getSubject(); // Asunto

                    $from = $message->getFrom(); // Remitente(s)
                    foreach ($from as $sender) {
                        $name = $sender->name;    // Nombre del remitente
                        $mail = $sender->mail;    // Dirección de correo
                        $full = $sender->full;    // Cadena completa "Nombre <email>"
                    }

                    $to = $message->getTo(); // Destinatario(s)
                    $cc = $message->getCc(); // Copias
                    $bcc = $message->getBcc(); // Copias ocultas

                    $date = $message->getDate(); // Fecha
                    // $date es un objeto Carbon que puedes formatear:
                    $formattedDate = $date->format('Y-m-d H:i:s');

                    $flags = $message->getFlags(); // Banderas (visto, respondido, etc.)

                    // Obteniendo información sobre adjuntos (sin descargarlos)
                    $attachments = $message->getAttachments();
                    foreach ($attachments as $attachment) {
                        $filename = $attachment->getName();
                        $size = $attachment->getSize();
                        $contentType = $attachment->getContentType();
                    }
                    */

                    $html = $message->getHTMLBody();

                    $dom = new DOMDocument();
                    @$dom->loadHTML($html); // El uso de @ evita warnings en HTML mal formado

                    // Buscar todos los elementos <b>
                    $spanTags = $dom->getElementsByTagName('span');

                    if (isset($spanTags[9])) {
                        // Parsear la fecha
                        $carbonDate = Carbon::parse($spanTags[3]->textContent);
                        /*
                        0 => "110,00\u{A0}€ EUR"
                        1 => "$451.62 USD"
                        2 => "50,00Â\u{A0}â‚¬ EUR"
                        3 => "60,00Â\u{A0}â‚¬ EUR"
                        4 => "370,00Â\u{A0}â‚¬ EUR"

                         */


                        $value = $this->getAmount($spanTags[0]->textContent);

                        $amount = Moneys::format($value["amount"], 2, ".", "");
                        $rate = floatval(str_replace("@", "", explode(" ", $spanTags[17]->textContent)[0]));
                        $usd = Moneys::format($tc->parseNumber(str_replace("$", "", explode(" ", $spanTags[19]->textContent)[0])), 2, ".", "");
                        $name = $spanTags[9]->textContent;
                        $transaction = [
                            "date" => $carbonDate->format('Y-m-d') . " " . Carbon::now()->format("H:i"),
                            "id" => $spanTags[5]->textContent,
                            "name" => $name,
                            "amount" => $amount,
                            "coin" => $value["coin"],
                            "to" => $spanTags[7]->textContent,
                            "rate" => $rate,
                            "usd" => $usd,
                        ];
                        $filename = GraphsController::generateComprobantGraph($transaction, true);
                        $url = "https://dev.micalme.com" . FileController::$AUTODESTROY_DIR . "/{$filename}";
                        $text = $name . " " . $value["amount"];
                        $array = array(
                            "message" => array(
                                "text" => $text,
                                "photo" => $url,
                                "chat" => array(
                                    "id" => env("TELEGRAM_GROUP_GUTO_TRADE_BOT"),
                                ),
                            ),
                        );
                        $response = TelegramController::sendPhoto($array, $bot->tenant->token);
                        Log::info("✅ CheckEmails sendtogroup message = " . json_encode($array["message"]) . " response = " . json_encode($response) . "\n");
                        $array = json_decode($response, true);
                        if (isset($array["result"]) && isset($array["result"]["message_id"]) && $array["result"]["message_id"] > 0) {
                            $payment = $bot->PaymentsController->create(
                                $bot,
                                $value["amount"],
                                $name,
                                isset($array["result"]["photo"]) ? $array["result"]["photo"][count($array["result"]["photo"]) - 1]["file_id"] : "AgACAgEAAxkBAALd_GcZYv85lMhzVQ-Ue8oWgwABZORGwAACQLAxG7X30UQcBx3z45dK6AEAAwIAA3kAAzYE",// foto de pago vacio
                                null,
                                $bot->tenant->data["info"]["id"],
                                array(
                                    "message_id" => $array["result"]["message_id"],
                                    "confirmation_date" => $carbonDate->format('Y-m-d') . " " . Carbon::now()->format("H:i:s"),
                                    "confirmation_message" => $array["result"]["message_id"],
                                    "transaction" => $transaction,
                                )
                            );

                            // Marcar el mensaje como leído si ha sido procesado
                            $message->setFlag('Seen');

                            // Notificar en el bot
                            $title = "Nuevo reporte automático";
                            if (
                                isset($bot->data["notifications"]["payments"]["new"]["frombot"]["tocapitals"]) &&
                                $bot->data["notifications"]["payments"]["new"]["frombot"]["tocapitals"] == 1
                            )
                                $bot->PaymentsController->notifyToCapitals(
                                    $bot,
                                    $payment,
                                    false,
                                    $title
                                );
                            if (
                                isset($bot->data["notifications"]["payments"]["new"]["frombot"]["togestors"]) &&
                                $bot->data["notifications"]["payments"]["new"]["frombot"]["togestors"] == 1
                            )
                                $bot->PaymentsController->notifyToGestors(
                                    $bot,
                                    $payment,
                                    false,
                                    $title,
                                    [
                                        [
                                            ["text" => "❌ Eliminar", "callback_data" => "confirmation|deletepayment-{$payment->id}|menu"],
                                        ],
                                    ]
                                );
                        }

                    } else {
                        // Marcar el mensaje como leído porq no cumple con el formato de mensaje Meru
                        $message->setFlag('Seen');
                    }

                }

                // Desconectarse del servidor IMAP
                $client->disconnect();
            } catch (\Throwable $th) {
                Log::error("🆘 CheckEmails job ERROR CODE {$th->getCode()} line {$th->getLine()}: {$th->getMessage()}");
            }

        }
    }

}
