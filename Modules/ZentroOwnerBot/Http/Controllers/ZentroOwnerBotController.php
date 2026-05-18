<?php

namespace Modules\ZentroOwnerBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\JsonsController;
use Modules\TelegramBot\Traits\UsesTelegramBot;
use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Modules\ZentroOwnerBot\Services\SecurityService;
use Illuminate\Support\Facades\Lang;

use Modules\Laravel\Entities\sfSecurity;

class ZentroOwnerBotController extends JsonsController
{
    use UsesTelegramBot;

    private static $AUTODESTROY_TIME_IN_MINS = 1;

    public function __construct()
    {
        $this->cleanChatMode = "delete_and_send";
        $this->tenant = app('active_bot');

        $this->ActorsController = new ActorsController();
        $this->TelegramController = new TelegramController();
    }

    public function processMessage()
    {
        $array = $this->getCommand($this->message["text"]);
        //var_dump($array);
        //die;

        $this->strategies["/p"] =
            $this->strategies["/pass"] =
            $this->strategies["/password"] =
            function () use ($array) {
                $key = strtolower($array["message"]);
                $demo = false;
                //$demo = isset($request["demo"]);
                $hashV1 = SecurityService::generateHash($this->actor->user_id, $key, 20, $demo);
                $hashV2 = SecurityService::derivePassword($key, $this->actor->user_id);
                return array(
                    "text" =>
                        "🔐 *" . strtoupper($key) . " hash:*\n\n" .
                        "*V1:*\n" .
                        "`{$hashV1}`\n\n" .
                        "*V2:*\n" .
                        "`{$hashV2}`\n\n" .
                        "_" . Lang::choice("zentroownerbot::bot.prompts.password.warning", ZentroOwnerBotController::$AUTODESTROY_TIME_IN_MINS, ['count' => ZentroOwnerBotController::$AUTODESTROY_TIME_IN_MINS]) . "_",
                    "autodestroy" => ZentroOwnerBotController::$AUTODESTROY_TIME_IN_MINS,
                );
            };

        $this->strategies["/h"] = function () use ($array) {
            $key = strtolower($array["message"]);
            $hash = SecurityService::derivePassword($key, $this->actor->user_id);
            return array(
                "text" =>
                    "🔐 *" . strtoupper($key) . " hash:*\n" .
                    "`{$hash}`\n" .
                    "_" . Lang::choice("zentroownerbot::bot.prompts.password.warning", ZentroOwnerBotController::$AUTODESTROY_TIME_IN_MINS, ['count' => ZentroOwnerBotController::$AUTODESTROY_TIME_IN_MINS]) . "_",
                "autodestroy" => ZentroOwnerBotController::$AUTODESTROY_TIME_IN_MINS,
            );
        };

        $this->strategies["/f"] =
            function () use ($array) {
                return array(
                    "text" => $this->obtenerIniciales($array["message"]),
                );
            };

        $this->strategies["/l"] =
            $this->strategies["/lic"] =
            $this->strategies["/licencia"] =
            $this->strategies["/license"] =
            function () use ($array) {
                try {
                    $license = $this->generateZentroLicence(array(
                        "name" => $array["pieces"][1],
                        "pc" => $array["pieces"][2],
                        "end" => $array["pieces"][3],
                        "build" => "FU",
                    ));

                    $time = 2 * ZentroOwnerBotController::$AUTODESTROY_TIME_IN_MINS;
                    return array(
                        "text" =>
                            "💻 *" . $array["pieces"][1] . "*\n\n" .
                            "🔐 `" . $license["licence"] . "`\n" .
                            "📅 " . $license["installed"] . " ❌ " . $license["expire"] . " _" . $license["given"] . "_\n\n" .
                            "_" . Lang::choice("zentroownerbot::bot.prompts.password.warning", $time, ['count' => $time]) . "_"
                        ,
                        "autodestroy" => $time,
                    );
                } catch (\Exception $e) {
                    return array(
                        "text" => "❌ *ERROR:* " . $array["message"] . ":\n" . $e->getMessage(),
                    );
                }
            };


        // Kashio Commmands -------------------------------------------------------------------------------

        if ($this->actor->isLevel(1, $this->tenant->code)) {
            $this->strategies["/arbiter"] =
                function () use ($array) {
                    try {
                        $controller = new EscrowController();
                        $hash = $controller->proposeArbiter($array["pieces"][1]);
                        return array(
                            "text" =>
                                "✅ proposeArbiter `" . $array["pieces"][1] . "` DONE:\n" .
                                "`{$hash}`",
                        );
                    } catch (\Exception $e) {
                        return array(
                            "text" => "❌ *ERROR:* " . $array["message"] . ":\n" . $e->getMessage(),
                        );
                    }
                };

            $this->strategies["/dispute"] =
                function () use ($array) {
                    try {
                        $controller = new EscrowController();
                        $hash = $controller->resolveDispute($array["pieces"][1], $array["pieces"][2]);
                        return array(
                            "text" =>
                                "✅ resolveDispute `" . $array["pieces"][1] . "` DONE:\n" .
                                "🥇 Winner=`" . $array["pieces"][2] . "`\n" .
                                "`{$hash}`",
                        );
                    } catch (\Exception $e) {
                        return array(
                            "text" => "❌ *ERROR:* " . $array["message"] . ":\n" . $e->getMessage(),
                        );
                    }
                };

            $this->strategies["/rescue"] =
                function () use ($array) {
                    try {
                        $controller = new EscrowController();
                        $hash = $controller->rescueTokens($array["pieces"][1]);
                        return array(
                            "text" =>
                                "✅ rescueTokens `" . $array["pieces"][1] . "` DONE:\n" .
                                "`{$hash}`",
                        );
                    } catch (\Exception $e) {
                        return array(
                            "text" => "❌ *ERROR:* " . $array["message"] . ":\n" . $e->getMessage(),
                        );
                    }
                };

            $this->strategies["/percentagefee"] =
                function () use ($array) {
                    try {
                        $controller = new EscrowController();
                        $hash = $controller->setFee($array["pieces"][1]);
                        return array(
                            "text" =>
                                "✅ setFee `" . $array["pieces"][1] . "` DONE:\n" .
                                "`{$hash}`",
                        );
                    } catch (\Exception $e) {
                        return array(
                            "text" => "❌ *ERROR:* " . $array["message"] . ":\n" . $e->getMessage(),
                        );
                    }
                };

            $this->strategies["/tokenfee"] =
                function () use ($array) {
                    try {
                        $controller = new EscrowController();
                        $hash = $controller->setMinFeePerToken($array["pieces"][1]);
                        if ($hash)
                            return array(
                                "text" =>
                                    "✅ setMinFeePerToken `" . $array["pieces"][1] . "` DONE:\n" .
                                    "`{$hash}`",
                            );
                        return array(
                            "text" =>
                                "❌ setMinFeePerToken `" . $array["pieces"][1] . "`",
                        );
                    } catch (\Exception $e) {
                        return array(
                            "text" => "❌ *ERROR:* " . $array["message"] . ":\n" . $e->getMessage(),
                        );
                    }
                };


            $this->strategies["/withdraw"] =
                function () use ($array) {
                    try {
                        $controller = new EscrowController();
                        $hash = $controller->withdrawFees();
                        return array(
                            "text" =>
                                "✅ withdrawFees DONE:\n" .
                                "`{$hash}`",
                        );
                    } catch (\Exception $e) {
                        return array(
                            "text" => "❌ *ERROR:* " . $array["message"] . ":\n" . $e->getMessage(),
                        );
                    }
                };
        }

        return $this->getProcessedMessage();
    }

    public function mainMenu($actor)
    {
        return $this->getMainMenu(
            $actor
        );
    }

    public function configMenu($actor)
    {
        return $this->getConfigMenu(
            $actor
        );
    }

    function obtenerIniciales($texto)
    {
        $palabras = preg_split('/\s+/', trim($texto));
        $iniciales = '';

        foreach ($palabras as $palabra) {
            if (!empty($palabra)) {
                $iniciales .= strtoupper(substr($palabra, 0, 1));
            }
        }

        return $iniciales;
    }

    public function generateZentroLicence($request)
    {
        $key = $request["pc"];


        // guessing installation date
        $array = explode("-", $request["pc"]);
        $installed = date_create_from_format("Y-m-d", date("Y-m-d"));
        if (count($array) > 1) {
            try {
                $installed = date_create_from_format("Y-m-d", date("Y-m-d", $array[count($array) - 1]));
            } catch (\Throwable $th) {
            }
        }
        unset($array[count($array) - 1]);


        $response = array(
            "installed" => $installed->format("d/m/Y")
        );

        $key = implode("-", $array);

        $end = $request["end"];
        if (strpos($end, "/") > -1) {
            $expire = date_create_from_format("d/m/Y", $end);
            if ($expire) {
                $span = $expire->diff($installed);
                $period = "";
                if ($span->format("%y") > 0)
                    $period = $period . $span->format("%y") . "Y";
                if ($span->format("%m") > 0)
                    $period = $period . $span->format("%m") . "M";
                if ($span->format("%d") > 0)
                    $period = $period . $span->format("%d") . "D";

                $end = "";
            }
        } else {
            $period = strtoupper($request["end"]);
            $expire = $installed->add(new \DateInterval("P" . $period));
        }
        $response["expire"] = $expire->format("d/m/Y");

        // adjusting app name to Zentro&reg;
        $text = str_replace("®", "", $request["name"]);
        $text = str_replace("�", "", $text);
        $text = str_replace("&reg;", "", $text);
        $text = str_replace("Zentro", "Zentro&reg;", $text);
        $request["name"] = $text;

        $response["given"] = $period;
        $response["licence"] = sfSecurity::generateRegistrationCode(strtoupper($request["name"] . $key), $period, $request["build"]);

        return $response;
    }

}
