<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\FileController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;
use Illuminate\Support\Facades\Log;
use Modules\GutoTradeBot\Entities\Capitals;
use Modules\GutoTradeBot\Entities\Moneys;
use Dvzambrano\TelegramBot\Entities\Actors;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CapitalsController extends MoneysController
{
    public function create($amount, $comment, $screenshot, $sender_id, $supervisor_id, $data = array())
    {
        $capital = parent::createByModel(Capitals::class, $amount, $comment, $screenshot, $sender_id, $supervisor_id, $data);
        Log::channel('storage')->info('capital ' . json_encode($capital->toArray()));

        return $capital;
    }

    public function getUnmatched($id)
    {
        return parent::getUnmatchedMoneys(Capitals::class, $id);

    }
    public function getUnmatchedByAmount($amount, $id)
    {
        return parent::getUnmatchedMoneysByAmount(Capitals::class, $amount, $id);
    }
    public function getUnconfirmedCapitals($bot, $user_id = false)
    {
        return parent::getUnconfirmedMoneys($bot, Capitals::class, $user_id);
    }
    public function getAllCapitals($bot, $user_id = false)
    {
        return parent::getAllMoneys($bot, Capitals::class, $user_id);
    }
    public function getSentByQuery($sender = false)
    {
        $query = Capitals::whereRaw("JSON_EXTRACT(data, '$.fullname') = ?", [$sender]);

        return $query;
    }

    public function getSentBySumQuery($field, $sender = false)
    {
        $query = Capitals::select(DB::raw('SUM(' . $field . ') as total_amount'))
            ->whereRaw("JSON_EXTRACT(data, '$.fullname') = ?", [$sender]);

        $results = $query->get();

        return $results[0]->toArray()["total_amount"];
    }

    public function import($bot)
    {
        $path = public_path() . "/import.xlsm";

        $spreadsheet = IOFactory::load($path);
        //$sheet = $spreadsheet->getActiveSheet();
        $sheet = $spreadsheet->getSheetByName('Recibos');

        // Leer el excel
        $highestRow = $sheet->getHighestRow();
        $data = [];
        // Define las columnas a analizar
        $columns = ['A', 'B', 'C'];
        for ($row = 1; $row <= $highestRow; $row++) {
            foreach ($columns as $column) {
                $cellCoordinate = $column . $row;

                // Obtener valor de la celda
                $cellValue = $sheet->getCell($cellCoordinate)->getValue();
                $date = "";

                // Verificar si el valor es un número (posiblemente una fecha en formato de número de serie)
                if (is_numeric($cellValue)) {
                    // Convertir el número de serie a una fecha
                    $formattedDate = Date::excelToDateTimeObject($cellValue);

                    // Formatear la fecha según tus necesidades
                    $date = $formattedDate->format('Y-m-d');
                }

                // Analizar el color de fondo y el color del texto
                $data[$row][$column] = [
                    'value' => $cellValue,
                    'confirmed' => true,
                    'date' => $date,
                ];
            }
        }
        // Quitando el encabezado
        unset($data[1]);
        // Guardar en la BD los registros
        foreach ($data as $value) {
            $date = $value["A"]["date"];
            $amount = $value["B"]["value"];
            if ($amount > 0) { // validando q sea mayor q 0... si el excel esta en desarrollo da error
                $confirmed = $value["C"]["confirmed"];
                $sender_id = 5482646491;

                $comment = $bot->ProfitsController->getEURtoSendWithActiveRate($amount);

                $json = array(
                    "message_id" => 1,
                );
                if ($confirmed) {
                    $json["confirmation_date"] = $date . " " . date("H:i:s");
                    $json["confirmation_message"] = 1;
                }
                $capital = Capitals::create([
                    'amount' => $comment,
                    'comment' => $amount,
                    'screenshot' => MoneysController::$NOSCREENSHOT_PATH,
                    'sender_id' => $sender_id,
                    'supervisor_id' => 816767995,
                    'data' => $json,
                ]);
                $capital->created_at = Carbon::createFromFormat("Y-m-d H:i:s", $date . " " . date("H:i:s"));
                $capital->save();
            }
        }
    }

    public function getCapitalsSheet($bot, $capitals, $actor, $sheet)
    {
        $sheet->setCellValue("A1", "Fecha");
        $sheet->setCellValue("B1", "Recibido");
        $sheet->setCellValue("C1", "A enviar");

        for ($i = 0; $i < count($capitals); $i++) {
            $sheet->setCellValue("A" . ($i + 2), Carbon::createFromFormat("Y-m-d H:i:s", $capitals[$i]->created_at)->toDateString());
            $sheet->setCellValue("B" . ($i + 2), $capitals[$i]->comment);
            $sheet->setCellValue("C" . ($i + 2), $capitals[$i]->amount);

            if (!$capitals[$i]->isConfirmed()) {
                $sheet->getStyle("B" . ($i + 2) . ":B" . ($i + 2))->getFont()->getColor()->setARGB(Color::COLOR_RED);
            }

        }
        // Obtener la última fila con datos en la columna C
        $lastRow = $sheet->getHighestDataRow('C');
        // Agregar la fórmula SUM en la siguiente fila
        $sheet->setCellValue('A' . ($lastRow + 1), "TOTAL:");
        $sheet->getStyle('A' . ($lastRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        $sheet->setCellValue('B' . ($lastRow + 1), '=SUM(B2:B' . $lastRow . ')');
        // Opcional: aplicar formato a la celda de total
        $sheet->getStyle('B' . ($lastRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE]]
        ]);
        $sheet->setCellValue('C' . ($lastRow + 1), '=SUM(C2:C' . $lastRow . ')');
        // Opcional: aplicar formato a la celda de total
        $sheet->getStyle('C' . ($lastRow + 1))->applyFromArray([
            'font' => ['bold' => true],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE]]
        ]);

        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->freezePane('B2');
        $sheet->setTitle("Recibos");

        // Opcional: estilo para los encabezados
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFD9D9D9']]
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
        // Agregar filtros automáticos a los encabezados (desde A1 hasta F1)
        $sheet->setAutoFilter('A1:C1');
    }

    public function export($bot, $capitals, $actor)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $this->getCapitalsSheet($bot, $capitals, $actor, $sheet);

        $writer = new Xlsx($spreadsheet);
        $filename = FileController::getFileNameAsUnixTime("xlsx", GutoTradeBotController::$TEMPFILE_DURATION_HOURS, "HOURS");

        $path = public_path() . FileController::$AUTODESTROY_DIR;
        // Si la carpeta no existe, crearla
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        // Guardar el archivo en el sistema
        $writer->save($path . "/" . $filename);

        $array = explode(".", $filename);
        return array(
            "filename" => $array[0],
            "extension" => $array[1],
        );
    }

    public function confirm($capitals, $user_id)
    {
        foreach ($capitals as $capital) {
            $array = $capital->data;

            $array = $capital->data;
            $array["confirmation_date"] = date("Y-m-d H:i:s");
            $array["confirmation_actor"] = $user_id;
            $capital->supervisor_id = $user_id;
            $capital->data = $array;

            $capital->data = $array;
            $capital->save();
        }
    }

    public function asignSupervisor($capitals, $supervisor_id)
    {
        foreach ($capitals as $capital) {
            $array = $capital->data;

            $capital->supervisor_id = $supervisor_id;

            $capital->data = $array;
            $capital->save();
        }
    }
    public function requestConfirmation($bot, $capitals)
    {
        $tenant = app('active_bot');

        foreach ($capitals as $capital) {
            if ($capital->supervisor_id && $capital->supervisor_id > 0) {
                // solicitar directamente al supervisor asignado
                $supervisorsmenu = $this->getOptionsMenuForThisOne($bot, $capital, 3);
                $actor = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $capital->supervisor_id);
                $this->notifyStatusRequestToSupervisor($bot, $capital, $actor, $supervisorsmenu);
            } else {
                // si no hay supervisor, solicitar a todos los admins
                $supervisorsmenu = $this->getOptionsMenuForThisOne($bot, $capital, 1);
                $admins = $bot->ActorsController->getData(Actors::class, [
                    [
                        "contain" => true,
                        "name" => "admin_level",
                        "value" => [1, "1", 4, "4"],
                    ],
                ], $bot->tenant->code);
                for ($i = 0; $i < count($admins); $i++) {
                    $this->notifyStatusRequestToSupervisor($bot, $capital, $admins[$i], $supervisorsmenu);
                }
            }
        }
    }

    public function getPrompt($bot, $method)
    {
        $tenant = app('active_bot');
        $bot->ActorsController->updateData(Actors::class, "user_id", $bot->actor->user_id, "last_bot_callback_data", $method, $tenant->code);

        $reply = array(
            "text" => "💰 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.report_prompt.header')) . "*\n\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.report_prompt.instructions')) . "_\n\nEjemplo:    *Juan Perez 1200*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.report_prompt.example_text')) . "_\n\n👇 " . TextService::mdv2(Lang::get('gutotradebot::bot.capital.report_prompt.prompt')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "✋ " . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), "callback_data" => "menu"]],
                ],
            ]),
        );

        return $reply;
    }

    public function getMenu($actor)
    {
        $reply = array();

        $menu = array();

        if ($actor) {
            //array_push($menu, [["text" => "💰 Reportar aporte de capital realizado", "callback_data" => "sendercapitalmenu"]]);
            array_push($menu, [
                ["text" => "🤷🏻‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.unconfirmed')), "callback_data" => "getadminunconfirmedcapitalsmenu"],
            ]);
            array_push($menu, [["text" => "📝 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.export_capitals')), "callback_data" => "getadminallcapitalsmenu"]]);
            array_push($menu, [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtoadminmenu')), "callback_data" => "adminmenu"]]);

            $reply = array(
                "text" => "💰 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.menu.header')) . "*\!\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.menu.description')) . "_\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
                "reply_markup" => json_encode([
                    "inline_keyboard" => $menu,
                ]),
            );

        }

        return $reply;
    }

    public function getUnconfirmed($bot, $user_id, $to_id = false)
    {
        $tenant = app('active_bot');

        if (!$to_id) {
            $to_id = $user_id;
        }

        $text = "👍 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.empty')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.empty_self')) . "_";
        $menu = [
            [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
        ];
        if ($user_id != $to_id) {
            $text = "👍 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.empty')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.empty_user')) . "_";
            $menu = [
                [["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.backtousersmenu')), "callback_data" => "getadminunconfirmedcapitalsmenu"]],
            ];
        }
        $reply = array(
            "text" => $text,
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),
        );

        $actor = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $to_id);
        $isadmin = $actor->isLevel(1, $bot->tenant->code);
        $capitals = $this->getUnconfirmedCapitals($bot, $user_id);

        if (count($capitals) > 0) {
            $amount = 0;
            $count = 0;
            foreach ($capitals as $capital) {

                $pendingmenu = array();
                if ($isadmin) {
                    $pendingmenu = $this->getOptionsMenuForThisOne($bot, $capital, 1);
                    if ($capital->supervisor_id && $capital->supervisor_id > 0) {
                        array_unshift($pendingmenu, [
                            ["text" => "⚠️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.request_confirmation')), "callback_data" => "requestcapitalconfirmation-{$capital->id}"],
                        ]);
                    }
                } else {
                    array_unshift($pendingmenu, [
                        ["text" => "⚠️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.request_confirmation')), "callback_data" => "requestcapitalconfirmation-{$capital->id}"],
                    ]);
                }

                $capital->sendAsTelegramMessage(
                    $bot,
                    $actor,
                    TextService::mdv2(Lang::get('gutotradebot::bot.capital.pending_title')),
                    false,
                    $user_id == "all" ? $capital->sender_id : false,
                    $pendingmenu
                );

                $amount += $capital->amount;
                $count += 1;
            }

            $array = $this->export($bot, $capitals, $actor);
            $xlspath = request()->root() . "/report/" . $array["extension"] . "/" . $array["filename"];
            $amount = TextService::mdv2(Moneys::format($amount));

            $text = "👆 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.self', ['count' => $count])) . "_\n*Total: {$amount}* 💰\n\n" . $bot->getReportFileText($xlspath);
            if ($isadmin) {
                $text = "👆 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.admin', ['count' => $count])) . "_\n*Total: {$amount}* 💰\n\n" . $bot->getReportFileText($xlspath);
            }
            $menu = [
                [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
            ];

            if ($user_id != $to_id) {
                $text = "👆 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_list.admin', ['count' => $count])) . "_\n*Total: {$amount}* 💰\n\n" . $bot->getReportFileText($xlspath);
                $menu = [
                    [["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.backtousersmenu')), "callback_data" => "getadminunconfirmedcapitalsmenu"]],
                ];
            }

            $reply = array(
                "text" => $text,
                "reply_markup" => json_encode([
                    "inline_keyboard" => $menu,
                ]),
            );

        }

        return $reply;
    }

    public function getUnconfirmedMenuForUsers($bot)
    {
        $tenant = app('active_bot');

        $senders = $bot->ActorsController->getData(Actors::class, [
            [
                "contain" => true,
                "name" => "admin_level",
                "value" => [4, "4"],
            ],
        ], $tenant->code);
        $menu = array();
        array_push($menu, [
            ["text" => "👥 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.all')), "callback_data" => "unconfirmedcapitals-all"],
        ]);
        foreach ($senders as $sender) {
            $suscriptor = $bot->AgentsController->getSuscriptor($bot, $sender->user_id, true);
            array_push($menu, [["text" => $suscriptor->getTelegramInfo($bot, "full_name"), "callback_data" => "unconfirmedcapitals-{$sender->user_id}"]]);
        }
        array_push($menu, [
            ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
        ]);

        $reply = array(
            "text" => "💰 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_menu.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.unconfirmed_menu.desc')) . "_\n\n👇 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.who_to_see')),
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),

        );

        return $reply;
    }

    public function getAllMenuForUsers($bot)
    {

        $tenant = app('active_bot');

        $senders = $bot->ActorsController->getData(Actors::class, [
            [
                "contain" => true,
                "name" => "admin_level",
                "value" => [4, "4"],
            ],
        ], $tenant->code);
        $menu = array();
        array_push($menu, [
            ["text" => "👥 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.all')), "callback_data" => "allcapitals-all"],
        ]);
        foreach ($senders as $sender) {
            $suscriptor = $bot->AgentsController->getSuscriptor($bot, $sender->user_id, true);
            array_push($menu, [["text" => $suscriptor->getTelegramInfo($bot, "full_name"), "callback_data" => "allcapitals-{$sender->user_id}"]]);
        }
        array_push($menu, [
            ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
        ]);

        $reply = array(
            "text" => "💰 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_menu.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_menu.desc')) . "_\n\n👇 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.who_to_see')),
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),

        );

        return $reply;
    }

    public function getAllList($bot, $user_id, $to_id = false)
    {
        $tenant = app('active_bot');

        if (!$to_id) {
            $to_id = $user_id;
        }

        $text = "👍 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.empty')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.empty_self')) . "_";
        $menu = [
            [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
        ];
        if ($user_id != $to_id) {
            $text = "👍 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.empty')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.empty_user')) . "_";
            $menu = [
                [["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.backtousersmenu')), "callback_data" => "getadminallcapitalsmenu"]],
            ];
        }
        $reply = array(
            "text" => $text,
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),
        );

        $actor = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $to_id);
        $isadmin = $actor->isLevel(1, $bot->tenant->code);
        $capitals = $this->getAllCapitals($bot, $user_id);

        if (count($capitals) > 0) {
            $amount = 0;
            $count = 0;
            foreach ($capitals as $capital) {
                $amount += $capital->amount;
                $count += 1;
            }

            $array = $this->export($bot, $capitals, $actor);
            $xlspath = request()->root() . "/report/" . $array["extension"] . "/" . $array["filename"];

            $text = "👆 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.self', ['count' => $count]));
            $menu = [
                [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
            ];
            if ($isadmin || $user_id != $to_id) {
                $text = "👆 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.all_list.admin', ['count' => $count]));
                $menu = [
                    [["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.backtousersmenu')), "callback_data" => "getadminallcapitalsmenu"]],
                ];
            }

            $amount = TextService::mdv2(Moneys::format($amount));
            $text .= "_\n*Total: {$amount}* 💶\n\n" . $bot->getReportFileText($xlspath);

            $reply = array(
                "text" => $text,
                "reply_markup" => json_encode([
                    "inline_keyboard" => $menu,
                ]),
            );

        }

        return $reply;
    }

    public function notifyNew($bot, $capital, $actor, $supervisorsmenu)
    {
        $capital->sendAsTelegramMessage(
            $bot,
            $actor,
            TextService::mdv2(Lang::get('gutotradebot::bot.capital.new_title')),
            false,
            true,
            $supervisorsmenu
        );
    }

    public function notifyToGestors($bot, $capital)
    {
        $tenant = app('active_bot');

        $supervisorsmenu = $this->getOptionsMenuForThisOne($bot, $capital, 1);

        $admins = $bot->ActorsController->getData(Actors::class, [
            [
                "contain" => true,
                "name" => "admin_level",
                "value" => [1, "1"],
            ],
        ], $tenant->code);
        for ($i = 0; $i < count($admins); $i++) {
            $this->notifyNew($bot, $capital, $admins[$i], $supervisorsmenu);
        }
    }
    public function notifyConfirmationToOwner($bot, $capital)
    {
        $actor = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $capital->sender_id);

        $capital->sendAsTelegramMessage(
            $bot,
            $actor,
            TextService::mdv2(Lang::get('gutotradebot::bot.capital.confirmed_title')),
            "✅ _" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.confirm.to_owner')) . "_",
            false,
            [
                [
                    ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                ],

            ]
        );
    }

    public function notifyConfirmationToAdmin($bot)
    {
        $tenant = app('active_bot');

        $bot->ActorsController->updateData(Actors::class, "user_id", $bot->actor->user_id, "last_bot_callback_data", "", $tenant->code);

        $reply = array(
            "text" => "✅ *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.confirmed_title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.confirm.to_admin')) . "_\n\n" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.confirm.notification_sent')) . "\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                    ],

                ],
            ]),
        );

        return $reply;
    }

    public function notifyAfterAsign($bot, $user_id)
    {
        // obteniendo datos del usuario de telegram
        $suscriptor = $bot->AgentsController->getSuscriptor($bot, $user_id, true);
        $reply = array(
            "text" => "🆗 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.assigned_title')) . "*\n\n" . $suscriptor->getTelegramInfo($bot, "full_info") . "\n\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                    ],

                ],
            ]),
        );

        return $reply;
    }

    public function notifyStatusRequestToSupervisor($bot, $capital, $actor, $supervisorsmenu)
    {
        $capital->sendAsTelegramMessage(
            $bot,
            $actor,
            TextService::mdv2(Lang::get('gutotradebot::bot.capital.status_title')),
            "⚠️ _" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.status_request.to_supervisor')) . "_",
            true,
            $supervisorsmenu
        );
    }

    public function notifyStatusNotYetToOwner($bot, $capital)
    {
        $actor = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $capital->sender_id);

        $capital->sendAsTelegramMessage(
            $bot,
            $actor,
            TextService::mdv2(Lang::get('gutotradebot::bot.capital.status_title')),
            "🤷🏻‍♂️ _" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.not_yet.to_owner')) . "_",
            false,
            [
                [
                    ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                ],

            ]
        );
    }

    public function notifyStatusNotYetToAdmin()
    {
        $reply = array(
            "text" => "👍 *" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.not_yet.replied_title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.not_yet.replied')) . "_\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                    ],

                ],
            ]),
        );

        return $reply;
    }

    public function notifyAfterStatusRequest()
    {
        $reply = array(
            "text" => "👍 *" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.status_request.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.status_request.to_sender')) . "_\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                    ],

                ],
            ]),
        );

        return $reply;
    }

    public function notifyAfterReceived($bot, $capital, $user_id)
    {
        $tenant = app('active_bot');

        $reply = array();

        $actor = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $user_id);
        $array = $actor->data;
        if (isset($array[$tenant->code]["last_bot_callback_data"])) {
            switch ($array[$tenant->code]["last_bot_callback_data"]) {
                case "getsendercapitalscreenshot":
                    $reply = $this->getMessageTemplate(
                        $bot,
                        $capital,
                        $capital->sender_id,
                        TextService::mdv2(Lang::get('gutotradebot::bot.capital.received_title')),
                        "✅ _" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.confirm.gestors_notify')) . "_",
                        false,
                        [
                            [
                                ["text" => "💰 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.report_another_capital')), "callback_data" => "sendercapitalmenu"],
                            ],
                            [
                                ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                            ],

                        ]
                    );
                    break;
                case "getsupervisorcapitalscreenshot":
                    $reply = $this->getMessageTemplate(
                        $bot,
                        $capital,
                        $capital->sender_id,
                        TextService::mdv2(Lang::get('gutotradebot::bot.capital.reception_completed')),
                        false,
                        false,
                        [
                            [
                                ["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.report_another_capital_reception')), "callback_data" => "supervisorcapitalmenu"],
                            ],
                            [
                                ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                            ],

                        ]
                    );
                    break;
                default:
                    break;
            }
        }
        $array[$tenant->code]["last_bot_callback_data"] = "";
        $actor->data = $array;
        $actor->save();

        return $reply;
    }

}
