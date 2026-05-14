<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Modules\TelegramBot\Http\Controllers\ActorsController;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;

class AgentsController extends ActorsController
{

    public function notifySuscriptor($bot, $actor, $suscriptor, $show_photo = false)
    {

        $tenant = app('active_bot');

        $array = parent::notifySuscriptor($bot, $actor, $suscriptor, $show_photo);
        $text = $array["message"]["text"];

        // mostrar los meadatos definidos para este suscriptor
        if (isset($suscriptor->data[$tenant->code]) && isset($suscriptor->data[$tenant->code]["metadatas"]))
            foreach ($suscriptor->data[$tenant->code]["metadatas"] as $key => $value) {
                $icon = "{$key} ";
                if ($key == "wallet")
                    $icon = "💰 ";
                $text .= "\n{$icon}`" . $value . "`";
            }

        // mostrar las cuentas asociadas a este suscriptor
        $accounts = $bot->AccountsController->getAccountsOfActor($suscriptor->user_id);
        if (count($accounts) > 0) {
            $text .= "\n";
        }
        foreach ($accounts as $account) {
            $message = $bot->AccountsController->getMessageTemplate($this, $account, $suscriptor->user_id, false);
            $text .= "\n" . $message["message"]["text"];
        }

        $array["message"]["text"] = $text;
        $array["message"]["parse_mode"] = "MarkdownV2";
        //var_dump($array["message"]["photo"]);
        if (isset($array["message"]["photo"])) {
            TelegramController::sendPhoto($array, $tenant->token);
        } else {
            TelegramController::sendMessage($array, $tenant->token);
        }
    }

    public function getRoleMenu($user_id, $role_id)
    {
        $array = parent::getRoleMenu($user_id, $role_id);

        switch ($role_id) {
            case 0:
                $array["role"] = "😳 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.no_role'));
                $array["menu"] = [
                    [
                        ["text" => "💶 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.remesador')), "callback_data" => "promote2-{$user_id}"],
                        ["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.receptor')), "callback_data" => "promote3-{$user_id}"],
                    ],
                    [
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.gestor')), "callback_data" => "promote1-{$user_id}"],
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.capital')), "callback_data" => "promote4-{$user_id}"],
                    ],
                ];
                break;
            case 1:
                $array["role"] = "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.gestor'));
                $array["menu"] = [
                    [
                        ["text" => "💶 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.remesador')), "callback_data" => "promote2-{$user_id}"],
                        ["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.receptor')), "callback_data" => "promote3-{$user_id}"],
                    ],
                    [
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.capital')), "callback_data" => "promote4-{$user_id}"],
                    ],
                ];
                break;
            case 2:
                $array["role"] = "💶 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.remesador'));
                $array["menu"] = [
                    [
                        ["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.receptor')), "callback_data" => "promote3-{$user_id}"],
                    ],
                    [
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.gestor')), "callback_data" => "promote1-{$user_id}"],
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.capital')), "callback_data" => "promote4-{$user_id}"],
                    ],
                ];
                break;
            case 3:
                $array["role"] = "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.receptor'));
                $array["menu"] = [
                    [
                        ["text" => "💶 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.remesador')), "callback_data" => "promote2-{$user_id}"],
                    ],
                    [
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.gestor')), "callback_data" => "promote1-{$user_id}"],
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.capital')), "callback_data" => "promote4-{$user_id}"],
                    ],
                ];
                break;
            case 4:
                $array["role"] = "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.capital'));
                $array["menu"] = [
                    [
                        ["text" => "💶 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.remesador')), "callback_data" => "promote2-{$user_id}"],
                        ["text" => "👍 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.receptor')), "callback_data" => "promote3-{$user_id}"],
                    ],
                    [
                        ["text" => "👮‍♂️ " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.gestor')), "callback_data" => "promote1-{$user_id}"],
                    ],
                ];
                break;
            default:
                break;
        }

        return $array;
    }
}
