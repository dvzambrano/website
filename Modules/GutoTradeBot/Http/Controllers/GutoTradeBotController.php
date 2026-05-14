<?php
namespace Modules\GutoTradeBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\FileController;
use Modules\Laravel\Http\Controllers\GraphsController;
use Modules\Laravel\Http\Controllers\TextController;
use Modules\Laravel\Http\Controllers\JsonsController;
use Modules\Laravel\Services\DateService;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\GutoTradeBot\Entities\Accounts;
use Modules\GutoTradeBot\Entities\Capitals;
use Modules\GutoTradeBot\Entities\Moneys;
use Modules\GutoTradeBot\Entities\Payments;
use Modules\TelegramBot\Entities\Actors;
use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\TelegramBot\Traits\UsesTelegramBot;
use Modules\GutoTradeBot\Jobs\CheckEmails;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Conditional;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;

class GutoTradeBotController extends JsonsController
{
    use UsesTelegramBot;

    public $PaymentsController;
    public $CapitalsController;
    public $AccountsController;
    public $CommentsController;
    public $ProfitsController;
    public $AgentsController;
    public $TextController;
    public $PenaltiesController;
    public $CoingeckoController;

    public static $TEMPFILE_DURATION_HOURS = 168;

    public function __construct()
    {
        $this->parseMode = "MarkdownV2";
        $this->tenant = app('active_bot');

        $this->ActorsController = new ActorsController();
        $this->TelegramController = new TelegramController();
        $this->TextController = new TextController();
        $this->PenaltiesController = new PenaltiesController();
        $this->AccountsController = new AccountsController();
        $this->CommentsController = new CommentsController();
        $this->PaymentsController = new PaymentsController();
        $this->CapitalsController = new CapitalsController();
        $this->ProfitsController = new ProfitsController();
        $this->AgentsController = new AgentsController();
        $this->CoingeckoController = new CoingeckoController();
    }


    public function processMessage()
    {
        $tenant = app('active_bot');

        $array = $this->getCommand($this->message["text"]);
        //Log::info("✅ GutoTradeBotController getCommand " . json_encode($array));

        $this->strategies["/help"] =
            $this->strategies["help"] =
            $this->strategies["/ayuda"] =
            $this->strategies["ayuda"] =
            function () use ($tenant) {
                $manualUrl = request()->root() . "/Bot.pdf";
                $termsUrl = request()->root() . "/TermsAndConditions.pdf";
                $text = "📖 *" . TextService::mdv2(Lang::get('gutotradebot::bot.help.header')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.help.intro')) . "_\n\n";
                $text .= "1️⃣ *" . TextService::mdv2(Lang::get('gutotradebot::bot.help.mainmenu_title')) . "*: /menu\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.help.mainmenu_desc')) . "_\n";
                $text .= "2️⃣ *" . TextService::mdv2(Lang::get('gutotradebot::bot.help.search_title')) . "*: /buscar\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.help.search_desc')) . " /buscar 1234_\n";
                $text .= "3️⃣ *" . TextService::mdv2(Lang::get('gutotradebot::bot.help.timezone_title')) . "*: /utc\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.help.timezone_desc')) . "_\n\n";
                $text .= "📚 *" . TextService::mdv2(Lang::get('gutotradebot::bot.help.manual_title')) . "*:\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.help.manual_desc')) . "_ [" . TextService::mdv2($manualUrl) . "](" . $manualUrl . ")\n\n";
                $text .= "👮‍♂️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.help.terms_title')) . "*:\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.help.terms_desc')) . "_ [" . TextService::mdv2($termsUrl) . "](" . $termsUrl . ")\n*" . TextService::mdv2(Lang::get('gutotradebot::bot.help.terms_implicit')) . "*";
                return [
                    "text" => $text,
                    "reply_markup" => json_encode([
                        "inline_keyboard" => [
                            [
                                ["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.gotomainmenu')), "callback_data" => "menu"],
                            ],

                        ],
                    ]),
                ];
            };

        $this->strategies["/payments"] =
            function () use ($tenant) {
                $reply = $this->mainMenu($this->actor);
                if ($this->actor->isLevel(1, $tenant->code) || $this->actor->isLevel(4, $tenant->code)) {
                    $reply = $this->PaymentsController->getMenu($this, $this->actor);
                }
                return $reply;
            };
        $this->strategies["/capitals"] =
            function () use ($tenant) {
                $reply = $this->mainMenu($this->actor);
                if ($this->actor->isLevel(1, $tenant->code) || $this->actor->isLevel(4, $tenant->code)) {
                    $reply = $this->CapitalsController->getMenu($this->actor);
                }
                return $reply;
            };


        $this->strategies["buscar"] =
            function () use ($tenant) {
                return $this->PaymentsController->getSearchPrompt(
                    $this,
                    "getpaymentbyvalue",
                    $this->actor->getBackOptions(
                        "✋ " . TextService::mdv2(Lang::get("telegrambot::bot.options.cancel")),
                        $tenant->code,
                        [1, 4]
                    )
                );
            };
        $this->strategies["/buscar"] =
            function () use ($tenant, $array) {
                if ($array["message"] && strlen($array["message"]) > 1) {
                    $reply = $this->PaymentsController->renderPaymentsByAny(
                        $this,
                        $array["message"],
                        TextService::mdv2(Lang::get('gutotradebot::bot.payment.found_title'))
                    );
                    /*
                    try {
                        $reply = $this->PaymentsController->renderPaymentsByAny(
                            $this,
                            $array["message"],
                            TextService::mdv2(Lang::get('gutotradebot::bot.payment.found_title'))
                        );
                    } catch (\Throwable $th) {
                         Log::error("🆘 GutoTradeBotController /buscar ERROR CODE {$th->getCode()} line {$th->getLine()}: {$th->getMessage()}");
                        // Log::error("🆘 GutoTradeBotController TraceAsString: " . $th->getTraceAsString());

                        $reply = [
                            "text" => "😬 *Ha ocurrido un error {$th->getCode()}*\n_{$th->getMessage()}_",
                        ];
                    }
                    */
                } else {
                    $reply = $this->notifyShortSearchParameter($this->actor->user_id, $array["message"]);
                }
                return $reply;
            };
        $this->strategies["/findbyid"] =
            function () use ($tenant, $array) {
                return $this->PaymentsController->renderPaymentsByField(
                    $this,
                    TextService::mdv2(Lang::get('gutotradebot::bot.payment.found_title')),
                    "id",
                    "=",
                    $array["message"]
                );
            };

        $this->strategies["promptpaymentdaysold"] =
            function () use ($tenant) {
                return $this->PaymentsController->getDaysPrompt(
                    $this,
                    "getpaymentbydaysold",
                    $this->actor->getBackOptions(
                        "✋ " . TextService::mdv2(Lang::get("telegrambot::bot.options.cancel")),
                        $tenant->code,
                        [1, 4]
                    )
                );
            };

        $this->strategies["senderpaymentmenu"] =
            function () use ($tenant) {
                return (new ScreenshotWizardController())->startWizard($this, 'payment', 2, 2);
            };
        $this->strategies["sendercapitalmenu"] =
            function () use ($tenant) {
                return (new ScreenshotWizardController())->startWizard($this, 'capital', 4, 1);
            };

        $this->strategies["supervisorpaymentmenu"] =
            function () use ($tenant) {
                return (new ScreenshotWizardController())->startWizard($this, 'payment', 3, 2);
            };
        $this->strategies["supervisorcapitalmenu"] =
            function () use ($tenant) {
                return (new ScreenshotWizardController())->startWizard($this, 'capital', 1, 1);
            };

        $this->strategies["getadminunconfirmedcapitalsmenu"] =
            function () use ($tenant) {
                return $this->CapitalsController->getUnconfirmedMenuForUsers($this);
            };

        $this->strategies["/confirm"] =
            function () use ($tenant) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(3, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $reply = $this->PaymentsController->getUnconfirmedMenuForUsers($this);
                }
                return $reply;
            };

        $this->strategies["/liquidate"] =
            function () use ($tenant) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $reply = $this->PaymentsController->getUnliquidatedMenuForUsers($this);
                }
                return $reply;
            };

        $this->strategies["getadminallpaymentsmenu"] =
            function () use ($tenant) {
                return $this->PaymentsController->getAllMenuForUsers($this);
            };


        $this->strategies["getadminallcapitalsmenu"] =
            function () use ($tenant) {
                return $this->CapitalsController->getAllMenuForUsers($this);
            };

        $this->strategies["/storage"] =
            function () use ($tenant, $array) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(3, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $menu = [
                        [
                            ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                        ],
                    ];

                    $fc = new FileController();
                    $payments = $fc->searchInLog('payment', $array["message"], basename(config('logging.channels.storage.path')), false);

                    $amount = count($payments);
                    if ($amount > 20) {
                        $payments = $fc->searchInLog('payment', $array["message"], basename(config('logging.channels.storage.path')));
                        $amount = count($payments);
                    }

                    if ($amount > 20)
                        $reply = [
                            "text" => "⚠️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.storage.many_title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.storage.many_desc', ['text' => $array["message"], 'amount' => $amount])) . "_\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
                            "chat" => [
                                "id" => $this->actor->user_id,
                            ],
                            "reply_markup" => json_encode([
                                "inline_keyboard" => $menu,
                            ]),
                        ];
                    else {
                        foreach ($payments as $key => $array) {
                            $payment = new Payments($array);
                            $payment->id = $array["id"];
                            $payment->created_at = $array["created_at"];
                            $payment->updated_at = $array["updated_at"];
                            $payment->sendAsTelegramMessage(
                                $this,
                                $this->actor,
                                TextService::mdv2(Lang::get('gutotradebot::bot.payment.storage.title')),
                                false,
                                false,
                                [
                                    [
                                        ["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.import')), "callback_data" => "/importbyid " . $array["id"]],
                                    ],
                                ]
                            );
                        }
                        $reply = array(
                            "text" => "👆 *" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.storage.list_title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.storage.list_desc', ['amount' => $amount])) . "_",
                            "reply_markup" => json_encode([
                                "inline_keyboard" => $menu,
                            ]),
                        );
                    }
                }

                return $reply;
            };

        $this->strategies["/market"] =
            function () use ($tenant, $array) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $amount = 100;
                    $rate = $this->CoingeckoController->getRate(Carbon::now()->format("Y-m-d"));

                    $flow = $this->ProfitsController->calculateFlow($amount, $rate["inverse"]);

                    $capitals = Capitals::query()
                        ->select([
                            DB::raw('DATE(created_at) as date'),
                            DB::raw('SUM(amount) as amount'),
                            DB::raw('SUM(comment) as arrival'),
                            DB::raw('COUNT(id) as count'),
                            DB::raw('JSON_ARRAYAGG(JSON_OBJECT("id", id, "amount", amount, "comment", comment, "screenshot", screenshot, "sender_id", sender_id, "supervisor_id", supervisor_id, "data", data)) as items'),
                        ])
                        ->whereNotNull(DB::raw("JSON_EXTRACT(data, '$.rate')"))
                        ->groupBy(DB::raw('DATE(created_at)'))
                        ->orderByDesc(DB::raw('DATE(created_at)'))
                        ->limit(10)
                        ->get()
                        ->toArray();
                    for ($i = 0; $i < count($capitals); $i++) {
                        $items = json_decode($capitals[$i]["items"], true);
                        foreach ($items as $key => $item) {
                            if (isset($item["data"]["rate"])) {
                                $capitals[$i]["data"] = $items[$key]["data"];
                                break;
                            }
                        }
                    }

                    $symbol = "➰";
                    if (count($capitals) > 0 && isset($capitals[0]["data"]["rate"])) {
                        if ($rate["inverse"] > $capitals[0]["data"]["rate"]["oracle"]["inverse"]) {
                            $symbol = "📈";
                        }
                        if ($rate["inverse"] < $capitals[0]["data"]["rate"]["oracle"]["inverse"]) {
                            $symbol = "📉";
                        }
                    }

                    $outputsymbol = " ";
                    if ($flow["output"]["percent"] > 0)
                        $outputsymbol = " +";

                    $text = "ℹ️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.market.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.market.desc')) . "_\n\n" .
                        "💰  *100.00* 💶 _" . TextService::mdv2(Lang::get('gutotradebot::bot.market.initial')) . "_\n" .
                        "{$symbol}  " . Moneys::format($rate["inverse"], 4) . " 💱 _" . $rate["direct"] . "_\n" .
                        "🛬  *" . Moneys::format($flow["arrival"]) . "* 💵 _" . TextService::mdv2(Lang::get('gutotradebot::bot.market.nets')) . "_\n" .
                        "➰    - " . Moneys::format($flow["waste"]["amount"]) . " 💵 _" . TextService::mdv2(Lang::get('gutotradebot::bot.market.expenses')) . " " . $flow["waste"]["percent"] . "%_\n" .
                        "🏭  *" . Moneys::format($flow["capital"]) . "* 💵 _" . TextService::mdv2(Lang::get('gutotradebot::bot.market.workable')) . "_\n" .
                        "➿   " . $outputsymbol . Moneys::format($flow["output"]["amount"]) . " 💱 _" . TextService::mdv2(Lang::get('gutotradebot::bot.market.client')) . " " . $flow["output"]["percent"] . "%_\n" .
                        "🛫  *" . Moneys::format($flow["profit"]["amount"]) . "* 💶 _" . TextService::mdv2(Lang::get('gutotradebot::bot.market.result')) . "_ *" . Moneys::format($flow["profit"]["percent"]) . "%*\n\n";

                    $dates = [];
                    $percents = [];
                    $sender = [];
                    $sendersum = 0;
                    $receiver = [];
                    $receiversum = 0;
                    for ($i = 0; $i < count($capitals); $i++) {
                        if (isset($capitals[$i]["data"]["rate"])) {
                            $symbol = "〰️";
                            if ($i < count($capitals) - 1) {
                                $next = $capitals[$i + 1]["data"]["rate"]["oracle"]["inverse"];
                                if ($capitals[$i]["data"]["rate"]["oracle"]["inverse"] > $next) {
                                    $symbol = "📈";
                                } else {
                                    $symbol = "📉";
                                }
                            }
                            $flow = $this->ProfitsController->calculateFlow($amount, $capitals[$i]["data"]["rate"]["oracle"]["inverse"], $capitals[$i]["data"]["profit"]["salary"], $capitals[$i]["data"]["profit"]["profit"]);

                            $dates[] = $capitals[$i]["date"];
                            $percent = $flow["profit"]["percent"];
                            $percents[] = $percent;

                            $sernderamount = $capitals[$i]["amount"] * $percent / 100;
                            $sendersum += $sernderamount;
                            $sender[] = $sernderamount;

                            $receiveramount = $capitals[$i]["arrival"] * $flow["waste"]["percent"] / 100;
                            $receiversum += $receiveramount;
                            $receiver[] = $receiveramount;

                            //die($capitals[$i]["date"] . " = " . $sernderamount . " / " . $receiveramount);
    
                            if ($capitals[$i]["date"] == date("Y-m-d")) {
                                $found = true;
                            }

                            $date = Carbon::createFromDate($capitals[$i]["date"]);
                            $text .= $symbol . " " . $date->format("Y-m-d") . " 💱 " . Moneys::format($capitals[$i]["data"]["rate"]["oracle"]["inverse"], 4) . " 👉 " . Moneys::format($percent) . "%\n";
                        }
                    }

                    $dates = array_reverse($dates);
                    $percents = array_reverse($percents);
                    $sender = array_reverse($sender);
                    $receiver = array_reverse($receiver);

                    $filename = false;
                    if (count($dates) > 0) {
                        $filename = GraphsController::generateGroupBarsGraph($dates, [
                            [
                                "values" => [$percents],
                                "weight" => 3,
                                "color" => ["black"],
                                "label" => ["Percent"],
                                "trend" => [
                                    "style" => "solid",
                                    "weight" => 2,
                                ],
                            ],
                            [
                                "values" => [[$receiver, $sender]],
                                "color" => [["#fbdfaa", "#aeffae"]],
                                "label" => [["Waste", "Profit"]],
                                "y" => true,
                            ],
                        ]);
                    }

                    $reply = [
                        "photo" => $filename ? request()->root() . FileController::$AUTODESTROY_DIR . "/{$filename}" : null,
                        "text" => $text,
                        "reply_markup" => json_encode([
                            "inline_keyboard" => [
                                [
                                    ["text" => "🔃 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.reload')), "callback_data" => "/market"],
                                ],
                                [
                                    ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                                ],

                            ],
                        ]),
                    ];
                }
                return $reply;
            };

        $this->strategies["/stats"] =
            function () use ($tenant, $array) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $array = explode(" ", $array["message"]);
                    $current_date = false;
                    $days = false;
                    if (count($array) == 2) {
                        $current_date = $array[0];
                        $days = $array[1];
                    }
                    //$reply = $this->PaymentsController->matchAny($this, $array[0], $array[1]);
                    $reply = $this->notifyStats($this->actor, $current_date, $days);
                }
                return $reply;
            };

        $this->strategies["/export"] =
            function () use ($tenant) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $reply = $this->getSystemInfo();
                }
                return $reply;
            };

        $this->strategies["/capital"] =
            function () use ($tenant) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $array = $this->PaymentsController->getCapitalizationReport($this);

                    $xlspath = request()->root() . "/report/" . $array["extension"] . "/" . $array["filename"];

                    $text = "📋 *" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.capitalization.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.capital.capitalization.desc')) . "_";
                    $menu = [
                        [["text" => "✅ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.use_report')), "callback_data" => "capitalize-" . $array["filename"]]],
                        [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
                    ];
                    $text .= "\n\n" . $this->getReportFileText($xlspath);

                    $reply = array(
                        "text" => $text,
                        "reply_markup" => json_encode([
                            "inline_keyboard" => $menu,
                        ]),
                    );
                }
                return $reply;
            };

        $this->strategies["capitalize"] =
            function () use ($tenant, $array) {
                return $this->PaymentsController->capitalizeReport($this, $array["pieces"][1]);
            };

        $this->strategies["/cashflow"] =
            function () use ($tenant) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $reply = $this->PaymentsController->getAllCash($this);
                }

                return $reply;
            };
        $this->strategies["/flow"] =
            function () use ($tenant, $array) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $array = explode(" ", $array["message"]);
                    $current_date = false;
                    $days = 14;
                    if (count($array) == 2) {
                        $current_date = $array[0];
                        $days = $array[1];
                    }
                    //$reply = $this->PaymentsController->matchAny($this, $array[0], $array[1]);
                    $reply = $this->notifyFlow($this->actor, $current_date, $days);
                }

                return $reply;
            };

        $this->strategies["promptaccountoperations"] =
            function () use ($tenant, $array) {
                $this->ActorsController->updateData(
                    Actors::class,
                    "user_id",
                    $this->actor->user_id,
                    "last_bot_callback_data",
                    "promptaccountoperations2-" . $array["pieces"][1],
                    $tenant->code
                );
                $reply = $this->AccountsController->getOperationsPrompt();

                return $reply;
            };

        $this->strategies["promptmoneyamount"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getRevalorizationPrompt($this, $array["pieces"][1]);
                return $reply;
            };

        $this->strategies["promptmoneycomment"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getRecommentPrompt($this, $array["pieces"][1]);
                return $reply;
            };

        $this->strategies["/accounts"] =
            function () use ($tenant, $array) {
                $reply = $this->AccountsController->getActiveAccounts($this);
                return $reply;
            };

        $this->strategies["accountactivation"] =
            function () use ($tenant, $array) {
                $account = $this->AccountsController->getFirst(Accounts::class, "id", "=", $array["pieces"][1]);
                $account->is_active = $array["pieces"][2] == "true";
                $account->save();

                return $this->AccountsController->getMessageTemplate($account->toArray(), $this->actor->user_id);
            };

        $this->strategies["confirmation"] =
            function () use ($tenant, $array) {
                $reply = $this->getAreYouSurePrompt($array["pieces"][1], $array["pieces"][2]);
                return $reply;
            };

        $this->strategies["asignpaymentsupervisor"] =
            function () use ($tenant, $array) {
                // asignar un RECEPTOR a un pago
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][2]);
                $this->PaymentsController->asignSupervisor([$payment], $array["pieces"][1]);

                $supervisorsmenu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, 3);
                $this->actor = $this->ActorsController->getFirst(Actors::class, "user_id", "=", $array["pieces"][1]);
                $payment->sendAsTelegramMessage(
                    $this,
                    $this->actor,
                    TextService::mdv2(Lang::get('gutotradebot::bot.payment.assign.new')),
                    $this->message["text"],
                    true,
                    $supervisorsmenu
                );

                $reply = $this->PaymentsController->notifyAfterAsign($this, $array["pieces"][1]);
                return $reply;
            };

        $this->strategies["confirmpayment"] =
            function () use ($tenant, $array) {
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                if (!$payment->isConfirmed()) {
                    $this->PaymentsController->confirm([$payment], $this->actor->user_id);
                    $this->PaymentsController->notifyConfirmationToOwner($this, $payment);

                    if (
                        isset($tenant->data["notifications"]["capitals"]["noenough"]["tocapitals"]) &&
                        $tenant->data["notifications"]["capitals"]["noenough"]["tocapitals"] == 1
                    ) {
                        // notificar nivel de capital bajo
                        $reply = $this->notifyFlow(
                            $this->actor,
                            false,
                            14,
                            "🚨 *ADVERTENCIA del sistema*\n_No hay capital suficiente para liquidar lo confirmado:_"
                        );
                        if ($reply["data"]["stock"] < 0) {
                            $admins = $this->ActorsController->getData(Actors::class, [
                                [
                                    "contain" => true,
                                    "name" => "admin_level",
                                    "value" => [1, "1", 4, "4"],
                                ],
                            ], $tenant->code);
                            for ($i = 0; $i < count($admins); $i++) {
                                $reply["chat"]["id"] = $admins[$i]->user_id;

                                $array = array(
                                    "message" => $reply,
                                );
                                $array["message"]["parse_mode"] = "MarkdownV2";
                                $array["message"]["reply_markup"] = $array["message"]["reply_markup"];
                                TelegramController::sendPhoto($array, $tenant->token);
                            }
                        }
                    }

                }
                $reply = $this->PaymentsController->notifyConfirmationToAdmin($this);
                return $reply;
            };

        $this->strategies["/match"] =
            function () use ($tenant, $array) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code) ||
                    $this->actor->isLevel(3, $tenant->code) ||
                    $this->actor->isLevel(4, $tenant->code)
                ) {
                    $array = explode(" ", $array["message"]);
                    $reply = $this->PaymentsController->matchAny($this, $array[0], $array[1]);
                }
                return $reply;
            };
        $this->strategies["matchpayments"] =
            function () use ($tenant, $array) {
                // se notifica la recepcion al remesador en la llamada a match
                // y aqui se notifica al admin q esta haciendo la operacion
                $reply = $this->PaymentsController->matchAny($this, $array["pieces"][1], $array["pieces"][2]);

                return $reply;
            };

        $this->strategies["asignpaymentsender"] =
            function () use ($tenant, $array) {
                // asignar un REMESADOR a un pago
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][2]);
                $this->PaymentsController->asignSender([$payment], $array["pieces"][1]);
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][2]);

                // notificar la recepcion al remesador
                $this->PaymentsController->notifyConfirmationToOwner($this, $payment);

                // notificar la accion al q la esta haciendo
                $reply = $this->PaymentsController->notifyAfterAsign($this, $array["pieces"][1]);
                return $reply;
            };

        $this->strategies["requestpaymentconfirmation"] =
            function () use ($tenant, $array) {
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                $this->PaymentsController->requestConfirmation($this, [$payment]);
                $reply = $this->PaymentsController->notifyAfterStatusRequest();
                return $reply;
            };

        $this->strategies["requestpaymentcomments"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getComments($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };
        $this->strategies["commentpayment"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getCommentPrompt($this, "payment", $array["pieces"][1]);
                return $reply;
            };

        $this->strategies["liquidatepayment"] =
            function () use ($tenant, $array) {
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                $this->PaymentsController->liquidate([$payment], $this->actor->user_id);
                $reply = $this->PaymentsController->notifyAfterLiquidate();
                return $reply;
            };

        $this->strategies["unconfirmedpayments"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getUnconfirmed($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };

        $this->strategies["unliquidatedpayments"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getUnliquidated($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };

        $this->strategies["/float"] =
            function () use ($tenant, $array) {
                $array["pieces"][1] = "all";
                $reply = $this->PaymentsController->getFloating($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };
        $this->strategies["floatingpayments"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getFloating($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };

        $this->strategies["changepaymentscreenshot"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getScreenshotChangePrompt($this, "getnewpaymentscreenshot-" . $array["pieces"][1]);
                return $reply;
            };

        $this->strategies["notyetpayment"] =
            function () use ($tenant, $array) {
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                $this->PaymentsController->notifyStatusNotYetToOwner($this, $payment);

                $reply = $this->PaymentsController->notifyStatusNotYetToAdmin();
                return $reply;
            };

        $this->strategies["allpayments"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getAllList($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };

        $this->strategies["deletepayment"] =
            function () use ($tenant, $array) {
                // eliminar un pago
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                if ($payment->sender_id && $payment->sender_id > 0) {
                    $owner = $this->ActorsController->getFirst(Actors::class, 'user_id', '=', $payment->sender_id);
                    $payment->sendAsTelegramMessage(
                        $this,
                        $owner,
                        TextService::mdv2(Lang::get('gutotradebot::bot.payment.deleted_title')),
                        "⚠️ _" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.deleted_notification')) . "_",
                        true,
                        [
                            [
                                ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                            ],

                        ]
                    );
                    Log::channel('storage')->info('payment ' . json_encode($payment->toArray()));
                }
                //$tenant, $actor, $title, $message = false, $show_owner_id = true, $menu = false, $demo = false
                $payment->delete();

                $reply = $this->PaymentsController->notifyAfterDelete();
                return $reply;
            };

        $this->strategies["unconfirmpayment"] =
            function () use ($tenant, $array) {
                // desconfirmar un pago
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                $array = $payment->data;

                unset($array["confirmation_date"]);
                unset($array["confirmation_message"]);

                $payment->data = $array;
                $payment->save();

                $reply = $this->PaymentsController->notifyAfterUnconfirm();
                return $reply;
            };

        $this->strategies["asigncapitalsupervisor"] =
            function () use ($tenant, $array) {
                // asignar un RECEPTOR a un aporte
                $capital = $this->CapitalsController->getFirst(Capitals::class, "id", "=", $array["pieces"][2]);
                $this->CapitalsController->asignSupervisor([$capital], $array["pieces"][1]);

                $this->actor = $this->ActorsController->getFirst(Actors::class, "user_id", "=", $array["pieces"][1]);

                $supervisorsmenu = $this->CapitalsController->getOptionsMenuForThisOne($this, $capital, 3);
                $this->CapitalsController->notifyNew($this, $capital, $this->actor, $supervisorsmenu);

                $reply = $this->CapitalsController->notifyAfterAsign($this, $array["pieces"][1]);
                return $reply;
            };

        $this->strategies["confirmcapital"] =
            function () use ($tenant, $array) {
                $capital = $this->CapitalsController->getFirst(Capitals::class, "id", "=", $array["pieces"][1]);
                $this->CapitalsController->confirm([$capital], $this->actor->user_id);
                $this->CapitalsController->notifyConfirmationToOwner($this, $capital);
                $reply = $this->CapitalsController->notifyConfirmationToAdmin($this);
                return $reply;
            };

        $this->strategies["requestcapitalconfirmation"] =
            function () use ($tenant, $array) {
                $capital = $this->CapitalsController->getFirst(Capitals::class, "id", "=", $array["pieces"][1]);
                $this->CapitalsController->requestConfirmation($this, [$capital]);
                $reply = $this->CapitalsController->notifyAfterStatusRequest();
                return $reply;
            };

        $this->strategies["notyetcapital"] =
            function () use ($tenant, $array) {
                $capital = $this->CapitalsController->getFirst(Capitals::class, "id", "=", $array["pieces"][1]);
                $this->CapitalsController->notifyStatusNotYetToOwner($this, $capital);

                $reply = $this->CapitalsController->notifyStatusNotYetToAdmin();
                return $reply;
            };

        $this->strategies["unconfirmedcapitals"] =
            function () use ($tenant, $array) {
                $reply = $this->CapitalsController->getUnconfirmed($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };

        $this->strategies["allcapitals"] =
            function () use ($tenant, $array) {
                $reply = $this->CapitalsController->getAllList($this, $array["pieces"][1], $this->actor->user_id);
                return $reply;
            };

        $this->strategies["deletecapital"] =
            function () use ($tenant, $array) {
                // eliminar un pago
                $capital = $this->CapitalsController->getFirst(Capitals::class, "id", "=", $array["pieces"][1]);
                $capital->delete();

                $reply = $this->CapitalsController->notifyAfterDelete();
                return $reply;
            };

        $this->strategies["/comments"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->getComments($this, $array["message"], $this->actor->user_id);
                return $reply;
            };
        $this->strategies["/comment"] =
            function () use ($tenant, $array) {
                $id = $this->getIdOfRepliedMessage();
                if ($id && $id > 0) {
                    $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $id);

                    $this->CommentsController->create($array["message"], $payment->screenshot, $this->actor->user_id, $payment->id);
                    $reply = $this->CommentsController->notifyAfterComment();

                    return $reply;
                }
            };

        $this->strategies["/profit"] =
            function () use ($tenant, $array) {
                $reply = $this->mainMenu($this->actor);
                if (
                    $this->actor->isLevel(1, $tenant->code)
                ) {
                    $reply = $this->ProfitsController->getPrompt($this);
                }
                return $reply;
            };

        $this->strategies["/asign"] =
            $this->strategies["/assign"] =
            function () use ($tenant, $array) {
                $id = $this->getIdOfRepliedMessage();
                if ($id && $id > 0) {
                    $suscriptor = $this->AgentsController->getSuscriptor($this, $array["message"], true);
                    if ($suscriptor) {
                        $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $id);
                        $payment->sender_id = $suscriptor->user_id;
                        $payment->save();
                        $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $id);
                        // preparar el menu de opciones sobre este pago
                        $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, $this->actor->data[$tenant->code]["admin_level"]);
                        $payment->sendAsTelegramMessage(
                            $this,
                            $this->actor,
                            TextService::mdv2(Lang::get('gutotradebot::bot.payment.updated_title')),
                            "⚠️ _" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.updated_note')) . "_",
                            true,
                            $menu
                        );
                        // Haciendo q no haya respuesta adicional
                        $reply = [
                            "text" => "",
                        ];
                        return $reply;
                    }
                }
            };

        $this->strategies["/rate"] =
            function () use ($tenant, $array) {
                $id = $this->getIdOfRepliedMessage();
                if ($id && $id > 0) {
                    $rate = $array["message"];
                    if (is_numeric($rate)) {
                        $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $id);
                        $array = $payment->data;
                        $array["rate"]["internal"] = $rate;
                        $payment->data = $array;
                        $payment->save();
                        $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $id);
                        // preparar el menu de opciones sobre este pago
                        $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, $this->actor->data[$tenant->code]["admin_level"]);
                        $payment->sendAsTelegramMessage(
                            $this,
                            $this->actor,
                            TextService::mdv2(Lang::get('gutotradebot::bot.payment.updated_title')),
                            "⚠️ _" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.updated_note')) . "_",
                            true,
                            $menu
                        );
                        // Haciendo q no haya respuesta adicional
                        $reply = [
                            "text" => "",
                        ];

                        return $reply;
                    }
                }
            };

        $this->strategies["/importbyid"] =
            function () use ($tenant, $array) {
                $id = $array["message"];
                $fc = new FileController();
                $payments = $fc->searchInLog('payment', $id, basename(config('logging.channels.storage.path')), true);
                foreach ($payments as $array)
                    if ($array["id"] == $id) {
                        $payment = new Payments($array);
                        $payment->id = $array["id"];
                        $payment->created_at = $array["created_at"];
                        $payment->updated_at = $array["updated_at"];
                        $payment->save();

                        // preparar el menu de opciones sobre este pago
                        $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, $this->actor->data[$tenant->code]["admin_level"]);
                        $payment->sendAsTelegramMessage(
                            $this,
                            $this->actor,
                            TextService::mdv2(Lang::get('gutotradebot::bot.payment.imported_title')),
                            "⚠️ _" . TextService::mdv2(Lang::get('gutotradebot::bot.payment.imported_note')) . "_",
                            true,
                            $menu
                        );

                        break;
                    }

                // Haciendo q no haya respuesta adicional
                $reply = [
                    "text" => "",
                ];

                return $reply;
            };

        $this->strategies["/checkemails"] =
            function () use ($tenant, $array) {
                $job = new CheckEmails();
                $job->handle(); // Llama directamente al método handle()
    
                $reply = [
                    "text" => "📧 *" . TextService::mdv2(Lang::get('gutotradebot::bot.email.checked')) . "* " . date("H:i:s"),
                    "autodestroy" => 1,
                ];
                return $reply;
            };

        $this->strategies["getpaymentbyvalue"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->renderPaymentsByAny(
                    $this,
                    $this->message["text"],
                    TextService::mdv2(Lang::get('gutotradebot::bot.payment.found_title')),
                    [
                        [
                            ["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.backtopaymentsmenu')), "callback_data" => "/payments"],
                        ],
                    ]
                );
                return $reply;
            };

        $this->strategies["getpaymentbydaysold"] =
            function () use ($tenant, $array) {
                $reply = $this->PaymentsController->renderPaymentsByDate(
                    $this,
                    $this->message["text"],
                    TextService::mdv2(Lang::get('gutotradebot::bot.payment.old_title')),
                    [
                        [
                            ["text" => "↖️ " . TextService::mdv2(Lang::get('gutotradebot::bot.options.backtopaymentsmenu')), "callback_data" => "/payments"],
                        ],
                    ]
                );
                return $reply;
            };

        $this->strategies["promptaccountoperations2"] =
            function () use ($tenant, $array) {
                $account = $this->AccountsController->getFirst(Accounts::class, "id", "=", $array["pieces"][1]);
                $array = $account->data;
                $array["remain_operations"] = $this->message["text"];
                $account->data = $array;
                $account->save();

                return $this->AccountsController->getMessageTemplate($account->toArray(), $this->actor->user_id);
            };

        $this->strategies["promptmoneyamount2"] =
            function () use ($tenant) {
                $command = "";
                if (isset($this->actor->data[$tenant->code]["last_bot_callback_data"]))
                    $command = $this->actor->data[$tenant->code]["last_bot_callback_data"];
                $array = $this->getCommand($command);

                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                $payment->amount = $this->message["text"];
                $payment->save();

                // preparar el menu de opciones sobre este pago
                $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, $this->actor->data[$tenant->code]["admin_level"]);
                $payment->sendAsTelegramMessage(
                    $this,
                    $this->actor,
                    TextService::mdv2(Lang::get('gutotradebot::bot.payment.modified_title')),
                    false,
                    true,
                    $menu
                );
                $reply = [
                    "text" => "",
                ];
                return $reply;
            };

        $this->strategies["promptmoneycomment2"] =
            function () use ($tenant) {
                $command = "";
                if (isset($this->actor->data[$tenant->code]["last_bot_callback_data"]))
                    $command = $this->actor->data[$tenant->code]["last_bot_callback_data"];
                $array = $this->getCommand($command);

                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                $payment->comment = $this->message["text"];
                $payment->save();

                // preparar el menu de opciones sobre este pago
                $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, $this->actor->data[$tenant->code]["admin_level"]);
                $payment->sendAsTelegramMessage(
                    $this,
                    $this->actor,
                    TextService::mdv2(Lang::get('gutotradebot::bot.payment.modified_title')),
                    false,
                    true,
                    $menu
                );
                $reply = [
                    "text" => "",
                ];
                return $reply;
            };

        $this->strategies["promptmoneycomment2"] =
            function () use ($tenant, $array) {
                $command = "";
                if (isset($this->actor->data[$tenant->code]["last_bot_callback_data"]))
                    $command = $this->actor->data[$tenant->code]["last_bot_callback_data"];
                $array = $this->getCommand($command);

                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                $payment->comment = $this->message["text"];
                $payment->save();

                // preparar el menu de opciones sobre este pago
                $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, $this->actor->data[$tenant->code]["admin_level"]);
                $payment->sendAsTelegramMessage(
                    $this,
                    $this->actor,
                    TextService::mdv2(Lang::get('gutotradebot::bot.payment.modified_title')),
                    false,
                    true,
                    $menu
                );
                $reply = [
                    "text" => "",
                ];
                return $reply;
            };

        $this->strategies["promptpaymentcomment"] =
            function () use ($tenant) {
                $command = "";
                if (isset($this->actor->data[$tenant->code]["last_bot_callback_data"]))
                    $command = $this->actor->data[$tenant->code]["last_bot_callback_data"];
                $array = $this->getCommand($command);

                //Log::info("✅ GutoTradeBotController promptpaymentcomment " . json_encode($array));
    
                $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);

                //$comment, $screenshot, $sender_id, $payment_id, $data = array()
                $this->CommentsController->create($this->message["text"], $payment->screenshot, $this->actor->user_id, $array["pieces"][1]);

                $commentQuote = ">" . implode("\n>", explode("\n", TextService::mdv2($this->message["text"])));

                switch ($this->actor->data[$tenant->code]["admin_level"]) {
                    // si lo ha escrito un remesador se notifica a los supervisores o a los admin4
                    case "2":
                    case 2:
                        if (
                            isset($tenant->data["notifications"]["comments"]["new"]["tosupervisors"]) &&
                            $tenant->data["notifications"]["comments"]["new"]["tosupervisors"] == 1
                        ) {
                            if ($payment->supervisor_id && $payment->supervisor_id > 0) {
                                $supervisor = $this->ActorsController->getFirst(Actors::class, "user_id", "=", $payment->supervisor_id);
                                $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, 3);
                                $payment->sendAsTelegramMessage(
                                    $this,
                                    $supervisor,
                                    TextService::mdv2(Lang::get('gutotradebot::bot.comment.on')),
                                    $commentQuote,
                                    true,
                                    $menu
                                );
                            } else {
                                $this->PaymentsController->notifyToCapitals($this, $payment, $commentQuote, TextService::mdv2(Lang::get('gutotradebot::bot.comment.on')));
                            }
                        }
                        if (
                            isset($tenant->data["notifications"]["comments"]["new"]["togestors"]) &&
                            $tenant->data["notifications"]["comments"]["new"]["togestors"] == 1
                        ) {
                            $this->PaymentsController->notifyToGestors($this, $payment, $commentQuote, TextService::mdv2(Lang::get('gutotradebot::bot.comment.on')));
                        }
                        break;
                    // si lo ha escrito cualquier otro se le notifica al remesador
                    default:
                        if ($payment->sender_id && $payment->sender_id > 0) {
                            $sender = $this->ActorsController->getFirst(Actors::class, "user_id", "=", $payment->sender_id);
                            $menu = $this->PaymentsController->getOptionsMenuForThisOne($this, $payment, 2);
                            $payment->sendAsTelegramMessage(
                                $this,
                                $sender,
                                TextService::mdv2(Lang::get('gutotradebot::bot.comment.on')),
                                $commentQuote,
                                true,
                                $menu
                            );
                        }
                        break;
                }

                $reply = $this->CommentsController->notifyAfterComment();
                return $reply;
            };

        $this->strategies["promptprofit"] =
            function () use ($tenant) {
                $command = "";
                if (isset($this->actor->data[$tenant->code]["last_bot_callback_data"]))
                    $command = $this->actor->data[$tenant->code]["last_bot_callback_data"];
                $array = $this->getCommand($command);

                $reply = [
                    "text" => "",
                ];

                $array = explode(":", $this->message["text"]);

                if (count($array) == 2) {
                    $stats = $this->CapitalsController->getStats($this);
                    // la cantidad de USDT en la wallet q aun no se han procesado
                    $amount = $stats["usdt"]["pending"] + $stats["usdt"]["unconfirmed"];
                    if ($amount > 0) {
                        $negative = -1 * $amount;
                        $data = [
                            "confirmation_date" => date("Y-m-d H:i:s"),
                            "confirmation_message" => request("message")["message_id"],
                        ];
                        $this->CapitalsController->create(
                            $this->ProfitsController->getEURtoSendWithActiveRate($negative),
                            $negative,
                            "AgACAgEAAxkBAALd_GcZYv85lMhzVQ-Ue8oWgwABZORGwAACQLAxG7X30UQcBx3z45dK6AEAAwIAA3kAAzYE",
                            $this->actor->user_id,
                            $this->actor->user_id,
                            $data
                        );
                    }

                    $this->ProfitsController->update($array[0], $array[1]);

                    // ajustar el capital restante
                    if ($amount > 0)
                        $this->CapitalsController->create(
                            $this->ProfitsController->getEURtoSendWithActiveRate($amount),
                            $amount,
                            "AgACAgEAAxkBAALd_GcZYv85lMhzVQ-Ue8oWgwABZORGwAACQLAxG7X30UQcBx3z45dK6AEAAwIAA3kAAzYE",
                            $this->actor->user_id,
                            $this->actor->user_id,
                            $data
                        );

                    $reply = $this->ProfitsController->notifyAfterChange();
                }
                return $reply;
            };

        $this->strategies["adminmenu"] =
            function () use ($tenant) {
                if (
                    $this->actor->isLevel(1, $this->tenant->code) ||
                    $this->actor->isLevel(4, $this->tenant->code)
                )
                    $reply = $this->adminMenu($this->actor);
                else
                    $reply = $this->mainMenu($this->actor);
                return $reply;
            };



        if (isset($this->message["text"]) && $this->message["text"] != "") {
            // Responder al texto recibido
            return $this->getProcessedMessage();
        }

        // si ha llegado hasta aqui es porq no mandaron texto, sino otra cosa y se requiere un reply diferente
        $reply = [
            "text" => "",
        ];

        if ((isset($this->message["photo"])) || isset($this->message["document"])) {
            // para poder analizar fotos y documentos para procesarlos como pago debe existir el actor previamente
            // si es una animacion no es un pago, es un mal manejo
            if ($this->actor && $this->actor->id > 0 && !isset($this->message["animation"])) {

                // Si hay un wizard activo, enrutar la foto directamente al paso correspondiente
                $wizardCacheKey = "wizard_{$this->tenant->key}_{$this->actor->user_id}";
                if (Cache::has($wizardCacheKey)) {
                    $wizard = Cache::get($wizardCacheKey);
                    return app()->make($wizard['controller'])->{$wizard['method']}($this);
                }

                $command = $this->actor->data[$tenant->code]["last_bot_callback_data"] ?? "";
                $array = $this->getCommand($command);

                switch ($array["command"]) {
                    case "getnewpaymentscreenshot":
                        // Actualizar solo la captura de un pago existente, sin wizard (no requiere caption)
                        $path = $this->getScreenshotPath();

                        $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);
                        $this->PaymentsController->updateScreenshot($payment, $path);
                        $payment = $this->PaymentsController->getFirst(Payments::class, "id", "=", $array["pieces"][1]);

                        $reply = $this->PaymentsController->getMessageTemplate(
                            $this,
                            $payment,
                            $this->actor->user_id,
                            TextService::mdv2(Lang::get('gutotradebot::bot.payment.report_title')),
                            "🖼 _" . TextService::mdv2(Lang::get('gutotradebot::bot.screenshot.updated_desc')) . "_",
                            false,
                            [
                                [["text" => "🔃 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.reload')), "callback_data" => "/findbyid {$payment->id}"]],
                                [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
                            ]
                        );
                        break;

                    default:
                        // Para todos los demás casos (comandos legacy o foto enviada sin contexto)
                        // iniciar el wizard de captura según el rol del usuario
                        $screenshotWizard = new ScreenshotWizardController();
                        if ($this->actor->isLevel(1, $tenant->code))
                            $reply = $screenshotWizard->startWizard($this, 'payment', 1, 2);
                        elseif ($this->actor->isLevel(2, $tenant->code))
                            $reply = $screenshotWizard->startWizard($this, 'payment', 2, 2);
                        elseif ($this->actor->isLevel(3, $tenant->code))
                            $reply = $screenshotWizard->startWizard($this, 'payment', 3, 2);
                        elseif ($this->actor->isLevel(4, $tenant->code))
                            $reply = $screenshotWizard->startWizard($this, 'payment', 3, 2);
                        break;
                }

            }
        }

        return $reply;
    }

    public function mainMenu($actor)
    {
        $bot = $this->tenant;

        $menu = array();

        // admin_level = 1 Admnistrador, 2 Remesador, 3 Receptor, 4 Admin de capital
        switch ($actor->data[$this->tenant->code]["admin_level"]) {
            case "0":
            case 0:
                $array = $this->AgentsController->getRoleMenu($actor->user_id, 0);
                array_push($array["menu"], [["text" => "❌ " . TextService::mdv2(Lang::get('telegrambot::bot.options.delete')), "callback_data" => "confirmation|deleteuser-{$actor->user_id}|menu"]]);
                $this->notifyUserWithNoRole($actor->user_id, $array);

                //$text .= "🤔 *Por alguna razón ud aun no tiene rol asignado. Le hemos enviado notficación a los administradores para que lo corrijan*.\n\n";
                break;
            case "1":
            case 1:
                array_push($menu, [["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.capital_reception')), "callback_data" => "supervisorcapitalmenu"]]);
                array_push($menu, [["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.admin')), "callback_data" => "adminmenu"]]);
                array_push($menu, [["text" => "🏦 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.active_accounts')), "callback_data" => "/accounts"]]);
                break;
            case "2":
            case 2:
                array_push($menu, [["text" => "💶 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.report_payment')), "callback_data" => "senderpaymentmenu"]]);
                array_push($menu, [
                    ["text" => "🤷🏻‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.unconfirmed')), "callback_data" => "unconfirmedpayments-{$actor->user_id}"],
                    ["text" => "🫰🏻 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.unsettled')), "callback_data" => "unliquidatedpayments-{$actor->user_id}"],
                ]);
                array_push($menu, [["text" => "🔎 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.search')), "callback_data" => "buscar"]]);
                array_push($menu, [["text" => "📝 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.export_payments')), "callback_data" => "allpayments-{$actor->user_id}"]]);
                array_push($menu, [["text" => "🏦 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.active_accounts')), "callback_data" => "/accounts"]]);
                break;
            case "3":
            case 3:
                array_push($menu, [["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.payment_reception')), "callback_data" => "supervisorpaymentmenu"]]);
                array_push($menu, [
                    ["text" => "🤷🏻‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.unconfirmed')), "callback_data" => "/confirm"],
                ]);
                break;
            case "4":
            case 4:
                array_push($menu, [["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.payment_reception')), "callback_data" => "supervisorpaymentmenu"]]);
                array_push($menu, [["text" => "💰 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.capital_contribution')), "callback_data" => "sendercapitalmenu"]]);
                array_push($menu, [["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.admin')), "callback_data" => "adminmenu"]]);
                array_push($menu, [["text" => "🏦 " . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.active_accounts')), "callback_data" => "/accounts"]]);
                break;
            default:
                break;
        }

        return $this->getMainMenu(
            $actor,
            $menu,
            "_" . TextService::mdv2(Lang::get('gutotradebot::bot.mainmenu.description')) . "_\n\n",
            true
        );
    }

    public function adminMenu($actor)
    {
        $menu = [];

        array_push($menu, [
            ["text" => "💶 " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.payments')), "callback_data" => "/payments"],
            ["text" => "💰 " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.capital')), "callback_data" => "/capitals"],
        ]);
        // admin_level = 1 Admnistrador, 4 Admin de capital
        switch ($actor->data[$this->tenant->code]["admin_level"]) {
            case "1":
            case 1:
                array_push($menu, [
                    ["text" => "💹 " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.stats')), "callback_data" => "/stats"],
                    ["text" => "🧮 " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.cashflow')), "callback_data" => "/cashflow"]
                ]);
                array_push($menu, [["text" => "🤑 " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.profits')), "callback_data" => "/profit"]]);
                break;
            case "4":
            case 4:
                array_push($menu, [
                    ["text" => "💹 " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.stats')), "callback_data" => "/stats"],
                    ["text" => "🧮 " . TextService::mdv2(Lang::get('gutotradebot::bot.adminmenu.cashflow')), "callback_data" => "/cashflow"]
                ]);
                break;
            default:
                break;
        }


        return $this->getAdminMenu(
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

    public function notifyShortSearchParameter($user_id, $message)
    {
        $reply = [
            "text" => "ℹ️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.search.short_title')) . "*\n\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.search.short_desc', ['text' => $message])) . "_\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "chat" => [
                "id" => $user_id,
            ],
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "🔎 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.search_another')), "callback_data" => "buscar"],
                    ],
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"],
                    ],

                ],
            ]),
        ];

        return $reply;
    }

    /**
     * Summary of notifyStats
     * @param mixed $actor
     * @param mixed $start_date Y-m-d
     * @param mixed $end_date Y-m-d / days
     * @return array
     */
    public function notifyStats($actor, $start_date = false, $end_date = false)
    {
        $bot = $this->tenant;

        $array = $this->PaymentsController->getPaymentsStats($this, $start_date, $end_date);
        $from_date = $array["from_date"];
        $to_date = $array["to_date"];
        $array = $array["items"];

        //dd($array);

        $stats = "";
        //if ($current_date) {
        $stats .= "🛬 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.received')) . "*: " . TextService::mdv2(Moneys::format($array["received"]["amount"])) . " 💵" .
            "\n🏷 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.tosend')) . "*: " . TextService::mdv2(Moneys::format($array["received"]["tosend"])) . " 💶" .
            "\n🛫 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.sent')) . "*: " . TextService::mdv2(Moneys::format($array["sent"]["amount"])) . " 💶 (" . TextService::mdv2(Moneys::format($array["sent"]["percent"])) . "%)" .
            "\n🏭 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.pending')) . "*: " . TextService::mdv2(Moneys::format($array["pending"]["amount"])) . " 💶 (" . TextService::mdv2(Moneys::format($array["pending"]["percent"])) . "%)";
        //}

        $stats .= "\n\n🤷🏻‍♂️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.unconfirmed')) . "*: " . TextService::mdv2(Moneys::format($array["unconfirmed"])) . " 💶" .
            "\n🫰🏻 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.unsettled')) . "*: " . TextService::mdv2(Moneys::format($array["unsettled"])) . " 💶";

        switch (strtolower($this->tenant->code)) {
            case "gutotradebot":
                $stats .= "\n\n💰 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.usdt')) . "*: " . TextService::mdv2(Moneys::format($array["stock"])) . " 💵";

                $value = $array["stock"] + $this->ProfitsController->getProfit($array["stock"]);

                $stats .= "\n💱 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.equivalents')) . "*: " . TextService::mdv2(Moneys::format($value)) . " 💶";

                if ($actor->isLevel(1, $this->tenant->code)) {
                    $stats .= "\n\n☑ *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.should')) . "*: " . TextService::mdv2(Moneys::format($array["should"])) . " 💵";
                    if ($array["having"] >= $array["should"]) {
                        $stats .= "\n✅ ";
                    } else {
                        if ($array["having"] >= $array["unsettled"]) {
                            $stats .= "\n😳 ";
                        } else {
                            $stats .= "\n🥵 ";
                        }
                    }

                    $stats .= "*" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.having')) . "*: " . TextService::mdv2(Moneys::format($array["having"])) . " 💵";
                }
                break;

            default:
                break;
        }

        $records = $this->PaymentsController->getRecords($from_date, $to_date);
        if (count($records["dates"]) == 0) {
            array_push($records["dates"], Carbon::now()->subDays(1)->toDateString());
            array_push($records["dates"], Carbon::now()->toDateString());
            array_push($records["receiveds"], 0);
            array_push($records["receiveds"], 0);
            array_push($records["confirmeds"], 0);
            array_push($records["confirmeds"], 0);
            array_push($records["sents"], 0);
            array_push($records["sents"], 0);
            array_push($records["balances"], 0);
            array_push($records["balances"], 0);
            array_push($records["confirmed_balances"], 0);
            array_push($records["confirmed_balances"], 0);
        }

        $filename = GraphsController::generateLinesGraph(
            $records["dates"],
            [
                [
                    "values" => $records["receiveds"],
                    "weight" => 2,
                    "color" => "green",
                    "label" => "Recibido",
                ],
                [
                    "values" => $records["sents"],
                    "style" => "dashed",
                    "weight" => 2,
                    "color" => "orange",
                ],
                [
                    "values" => $records["confirmeds"],
                    "weight" => 2,
                    "color" => "orange",
                    "label" => "Enviado",
                ],
                [
                    "values" => $records["confirmed_balances"],
                    "weight" => 3,
                    "color" => "#FF0000",
                    "label" => "Balance",
                    "trend" => [
                        "style" => "dashed",
                        "weight" => 2,
                        "color" => [
                            "positive" => "green",
                            "negative" => "red",
                        ],
                    ],
                ],
                [
                    "values" => $records["balances"],
                    "style" => "dashed",
                    "weight" => 2,
                    "color" => "#FF0000",
                ],
            ]
        );

        $text = TextService::mdv2(Lang::get('gutotradebot::bot.stats.now'));
        if ($end_date) {
            $text = "{$to_date->format("Y-m-d")}";
        }

        //$records["balances"]
        //$records["confirmeds"]
        $icon = "ℹ️";
        $limit = $records["balances"][count($records["balances"]) - 2] + $records["receiveds"][count($records["receiveds"]) - 1];
        if ($records["confirmeds"][count($records["confirmeds"]) - 1] < $limit) {
            $icon = "❇️";
        } else {
            if ($array["stock"] > 0) {
                $icon = "📳";
            } else {
                $icon = "🆘";
            }
        }

        $text = "{$icon} *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.desc', ['date' => $text])) . "_";

        $menu = [];
        $adminmenu = [];
        if (
            $actor->isLevel(1, $this->tenant->code) ||
            $actor->isLevel(4, $this->tenant->code)
        ) {
            if ($array["unconfirmed"] > 0) {
                array_push($adminmenu, ["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.confirm')), "callback_data" => "/confirm"]);
            }
        }
        if ($actor->isLevel(1, $this->tenant->code)) {
            if ($array["unsettled"] > 0) {
                array_push($adminmenu, ["text" => "🫰🏻 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.liquidate')), "callback_data" => "/liquidate"]);
            }
        }

        if (count($adminmenu) > 0) {
            array_push($menu, $adminmenu);
        }

        array_push($menu, [["text" => "🔃 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.reload')), "callback_data" => "/stats"]]);
        array_push($menu, [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtoadminmenu')), "callback_data" => "adminmenu"]]);

        $reply = [
            "text" => $text . "\n\n{$stats}\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "photo" => request()->root() . FileController::$AUTODESTROY_DIR . "/{$filename}",
            "chat" => [
                "id" => $actor->user_id,
            ],
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),
        ];

        return $reply;
    }

    public function notifyFlow($actor, $start_date = false, $end_date = false, $text = false)
    {
        $array = $this->PaymentsController->getPaymentsStats($this, $start_date, $end_date);
        $from_date = $array["from_date"];
        $to_date = $array["to_date"];
        $array = $array["items"];
        //dd($array);

        $stats = "";

        $stats .= "💰 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.usdt')) . "*: " . TextService::mdv2(Moneys::format($array["stock"])) . " 💵";

        $value = $array["stock"] + $this->ProfitsController->getProfit($array["stock"]);

        $stats .= "\n💱 *" . TextService::mdv2(Lang::get('gutotradebot::bot.stats.equivalents')) . "*: " . TextService::mdv2(Moneys::format($value)) . " 💶";

        $records = $this->PaymentsController->getRecords($from_date, $to_date);
        //dd($records);
        $sendamount = 0;
        $records["sentprom"] = [];
        $receivedamount = 0;
        $records["receivedprom"] = [];
        for ($i = 0; $i < count($records["confirmeds"]); $i++) {
            $records["sents"][$i] -= $records["confirmeds"][$i];

            $sendamount += $records["confirmeds"][$i];
            $records["sentprom"][$i] = $sendamount / ($i + 1);

            $receivedamount += $records["receiveds"][$i];
            $records["receivedprom"][$i] = $receivedamount / ($i + 1);
        }

        $stats .= "\n\n🛬 *" . TextService::mdv2(Lang::get('gutotradebot::bot.flow.avg_received')) . "*: " . TextService::mdv2(Moneys::format($records["receivedprom"][count($records["receivedprom"]) - 1])) . " 💵";
        $stats .= "\n🛫 *" . TextService::mdv2(Lang::get('gutotradebot::bot.flow.avg_sent')) . "*: " . TextService::mdv2(Moneys::format($records["sentprom"][count($records["sentprom"]) - 1])) . " 💶";

        if (count($records["dates"]) == 0) {
            array_push($records["dates"], Carbon::now()->subDays(1)->toDateString());
            array_push($records["dates"], Carbon::now()->toDateString());
            array_push($records["receiveds"], 0);
            array_push($records["receiveds"], 0);
            array_push($records["confirmeds"], 0);
            array_push($records["confirmeds"], 0);
            array_push($records["sents"], 0);
            array_push($records["sents"], 0);
            array_push($records["balances"], 0);
            array_push($records["balances"], 0);
            array_push($records["confirmed_balances"], 0);
            array_push($records["confirmed_balances"], 0);
        }

        $filename = GraphsController::generateGroupBarsGraph($records["dates"], [
            [
                "values" => [[$records["sents"], $records["confirmeds"]], $records["receiveds"]],
                "color" => [["#fafa8f", "#fbdfaa"], "#aeffae"],
                "label" => [[null, "Enviado"], "Recibido"],
            ],
            [
                "values" => [$records["sentprom"], $records["receivedprom"]],
                "weight" => 3,
                "color" => ["#eb6f01", "#12b512"],
                "label" => [null, null],
                "trend" => [
                    "style" => "solid",
                    "weight" => 2,
                ],
            ],
        ]);

        if (!$text) {
            $text = "ℹ️ *" . TextService::mdv2(Lang::get('gutotradebot::bot.flow.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.flow.desc')) . "_";
        }

        $reply = [
            "text" => $text . "\n\n{$stats}\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "photo" => request()->root() . FileController::$AUTODESTROY_DIR . "/{$filename}",
            "chat" => [
                "id" => $actor->user_id,
            ],
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "🔃 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.reload')), "callback_data" => "/flow"],
                    ],
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtoadminmenu')), "callback_data" => "adminmenu"],
                    ],

                ],
            ]),
            "data" => $array,
        ];

        return $reply;
    }

    public function getIdOfRepliedMessage()
    {
        $message = request()->input('message', []);

        if (isset($message["reply_to_message"])) {
            $message = $message["reply_to_message"];
            //Log::info("✅ GutoTradeBotController getIdOfRepliedMessage " . json_encode($message));

            // Los mensajes con foto usan 'caption_entities', los de texto 'entities'
            $entities = $message['caption_entities'] ?? $message['entities'] ?? [];

            foreach ($entities as $entity) {
                // Buscamos específicamente entidades de tipo text_link
                if ($entity['type'] === 'text_link' && isset($entity['url'])) {
                    $url = $entity['url'];

                    // Verificamos si la URL empieza con nuestro esquema personalizado
                    if (str_starts_with($url, 'tg://metadata')) {
                        // Parseamos la URL para obtener los parámetros
                        $queryString = parse_url($url, PHP_URL_QUERY);
                        parse_str($queryString, $params);

                        return $params['id'] ?? null;
                    }
                }
            }
        }
        return null;
    }

    public function getSystemInfo()
    {
        $spreadsheet = new Spreadsheet();
        $sheet_payments = $spreadsheet->getActiveSheet();
        $this->PaymentsController->getPaymentsSheet(
            $this,
            Payments::where('id', '>', 0)->get(),
            $this->actor,
            $sheet_payments
        );

        $sheet_capitals = $spreadsheet->createSheet();
        $this->CapitalsController->getCapitalsSheet(
            $this,
            Capitals::where('id', '>', 0)->get(),
            $this->actor,
            $sheet_capitals
        );

        $sheet_flow = $spreadsheet->createSheet();
        $this->PaymentsController->getCashFlowSheet(
            $this->PaymentsController->getCashFlow($this),
            $sheet_flow
        );

        // Obtener la última fila con datos en la columna B del flujo
        $flowlastRow = $sheet_flow->getHighestDataRow('B');
        // Obtener la última fila con datos en la columna C de los pagos
        $paymentslastRow = $sheet_payments->getHighestDataRow('C');
        // Calcular USDT a enviar por la cantidad de EUR q hay sin liquidar en la hoja de payments
        $value = $this->ProfitsController->getUSDTtoSendWithActiveRate($sheet_payments->getCell("C" . $paymentslastRow)->getValue());

        // Aplicar formato condicional 100
        $greencond = new Conditional();
        $greencond->setConditionType(Conditional::CONDITION_EXPRESSION);
        $greencond->setOperatorType(Conditional::OPERATOR_NONE);
        $greencond->addCondition($sheet_flow->getTitle() . "!B" . $flowlastRow . " > " . $value);
        $greencond->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('green');

        $redcond = new Conditional();
        $redcond->setConditionType(Conditional::CONDITION_EXPRESSION);
        $redcond->setOperatorType(Conditional::OPERATOR_NONE);
        $redcond->addCondition($sheet_flow->getTitle() . "!B" . $flowlastRow . " < " . $value);
        $redcond->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('red');

        // Aplicar el formato condicional
        $sheet_payments->getStyle("C" . $paymentslastRow)->setConditionalStyles([$greencond, $redcond]);


        // Volver a la primera hoja como activa
        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $filename = FileController::getFileNameAsUnixTime("xlsx", 2, "HOURS");

        $path = public_path() . FileController::$AUTODESTROY_DIR;
        // Si la carpeta no existe, crearla
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        // Guardar el archivo en el sistema
        $writer->save($path . "/" . $filename);

        $array = explode(".", $filename);
        $xlspath = request()->root() . "/report/" . $array[1] . "/" . $array[0];

        $text = "📋 *" . TextService::mdv2(Lang::get('gutotradebot::bot.system.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.system.desc')) . "_";
        $menu = [
            [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]],
        ];
        $text .= "\n\n" . $this->getReportFileText($xlspath);

        $reply = array(
            "text" => $text,
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),
        );

        return $reply;
    }

    public function getReportFileText($path)
    {
        $pieces = explode("/", $path);
        $diff = DateService::getTimeDifference(Carbon::now()->getTimestamp(), $pieces[count($pieces) - 1]);
        $text = "📎 " . TextService::mdv2(Lang::get('gutotradebot::bot.system.file_generated')) . "\n" .
            "[" . TextService::mdv2($path) . "](" . $path . ")\n" .
            "_" . TextService::mdv2(Lang::get('gutotradebot::bot.system.file_available', ['time' => $diff["legible"]])) . "_";
        return $text;
    }
}
