<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\JsonsController;
use Modules\GutoTradeBot\Entities\Accounts;
use Modules\TelegramBot\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;

class AccountsController extends JsonsController
{

    public function searchAccountsByField($field, $symbol, $value)
    {
        return Accounts::where($field, $symbol, $value)->get();
    }

    public function getAccountsOfActor($user_id)
    {
        return Accounts::where('data', 'LIKE', "%{$user_id}%")->get();
    }

    public function getAccountsGroupedByBank($field, $symbol, $value)
    {
        $accounts = Accounts::select('bank', 'id', 'name', 'number', 'detail', 'is_active', 'data')
            ->where($field, $symbol, $value)
            ->whereNotNull('bank')
            ->get()
            ->groupBy('bank');

        $array = [];

        foreach ($accounts as $bank => $bankAccounts) {
            $array[$bank] = $bankAccounts->toArray();
        }

        return $array;
    }
    public function getOperationsPrompt()
    {
        $reply = array(
            "text" => "🎲 *" . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.ops_prompt_title')) . "*\n\n👇 " . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.ops_prompt')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "✋ " . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), "callback_data" => "menu"]],
                ],
            ]),
        );

        return $reply;
    }

    public function getMessageTemplate($account, $to_id, $show_whattodo = true)
    {
        $menu = array();

        $text = "🏦 *{$account['bank']}*:\n";
        $text .= "-------------------------------------------------------------------\n";
        $text .= "🪪 `{$account['name']}`\n";
        if (isset($account["data"]) && isset($account["data"]["number"])) {
            foreach ($account["data"]["number"] as $number => $value) {
                $text .= "👉 `{$number}`\n";
            }
        } else {
            $text .= "👉 `{$account['number']}`\n";
        }
        if ($account['detail'] != null) {
            $text .= "📌 `{$account['detail']}`\n";
        }
        if ($account["is_active"]) {
            if (isset($account["data"])) {
                $data = $account["data"];
                if (isset($data["remain_operations"])) {
                    array_push($menu, [["text" => "🎲 " . $data['remain_operations'] . " " . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.ops_button')), "callback_data" => "promptaccountoperations-{$account['id']}"]]);
                }
            }
            array_push($menu, [
                ["text" => "🔴 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.deactivate')), "callback_data" => "accountactivation-{$account['id']}-false"],
            ]);
        } else {
            array_push($menu, [
                ["text" => "🟢 " . TextService::mdv2(Lang::get('gutotradebot::bot.options.activate')), "callback_data" => "accountactivation-{$account['id']}-true"],
            ]);
        }

        if ($show_whattodo && count($menu) > 0) {
            $text .= "👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext'));
        }

        return array(
            "message" => array(
                "text" => $text,
                "chat" => array(
                    "id" => $to_id,
                ),
                "reply_markup" => json_encode([
                    "inline_keyboard" => $menu,
                ]),
            ),
        );
    }

    public function getActiveAccounts($bot)
    {
        $tenant = app('active_bot');
        $reply = [];

        $text = "";

        $active_accounts = $this->getAccountsGroupedByBank("is_active", "=", true);
        switch ($bot->actor->data[$tenant->code]["admin_level"]) {
            case '1':
            case 1:
            case '4':
            case 4:
                // buscando cuentas inactivas para agregarselas a los admins y las puedan ver
                $inactive_accounts = $this->getAccountsGroupedByBank("is_active", "=", false);
                foreach ($inactive_accounts as $bank => $accounts) {
                    if (!isset($active_accounts[$bank])) {
                        $active_accounts[$bank] = [];
                    }
                    foreach ($accounts as $account) {
                        $active_accounts[$bank][] = $account;
                    }
                }

                // Para los admins mando cada cuenta por separado con opciones de gestion
                $amount = 0;
                foreach ($active_accounts as $bank => $accounts) {
                    foreach ($accounts as $account) {
                        $array = $this->getMessageTemplate($account, $bot->actor->user_id);
                        TelegramController::sendMessage($array, $tenant->token);
                        $amount++;
                    }
                }
                $text = "👆 *" . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.configured_title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.configured_desc', ['amount' => $amount])) . "_\n\n";
                break;
            default:
                // para cualquier otro mando un solo mensaje con el texto de todas las cuentas
                foreach ($active_accounts as $bank => $accounts) {
                    $account_content = "";
                    foreach ($accounts as $account) {
                        $auth = false;
                        $account_number = "👉 `{$account['number']}`\n";
                        if (isset($account["data"]) && isset($account["data"]["number"])) {
                            foreach ($account["data"]["number"] as $number => $value) {
                                if (array_search($bot->actor->user_id, $value["owners"]) > -1) {
                                    $account_number = "👉 `{$number}`\n";
                                    $auth = true;
                                    break;
                                }
                            }
                        } else {
                            $account_number = "👉 `{$account['number']}`\n";
                            $auth = true;
                        }
                        if ($auth) {
                            $account_content .= "-------------------------------------------------------------------\n";
                            $account_content .= "🪪 `{$account['name']}`\n";

                            $account_content .= $account_number;

                            if (isset($account["data"]) && isset($account["data"]["remain_operations"])) {
                                $account_content .= "🎲 " . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.ops_remaining', ['count' => $account['data']['remain_operations']])) . "\n🧏 _" . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.ops_estimate')) . "_\n";
                            }
                            if ($account['detail'] != null) {
                                $account_content .= "📌 `{$account['detail']}`\n";
                            }
                            if (isset($account["data"]) && isset($account["data"]["notes"])) {
                                foreach ($account["data"]["notes"] as $note) {
                                    $account_content .= "ℹ️ {$note}\n";
                                }
                            }
                        }
                    }
                    if ($account_content != "") {
                        $text .= "🏦 *" . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.bank_header', ['bank' => $bank])) . "*\n";
                        $text .= $account_content;
                        $text .= "===================================\n\n";
                    }
                }
                break;
        }
        $menu = [];
        array_push($menu, [["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtomainmenu')), "callback_data" => "menu"]]);

        if (count($active_accounts) == 0) {
            $text .= "❌ *" . TextService::mdv2(Lang::get('gutotradebot::bot.accounts.none_active')) . "*\n";
        }

        $text .= "👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext'));

        $reply = [
            "text" => $text,
            "reply_markup" => json_encode([
                "inline_keyboard" => $menu,
            ]),
        ];

        return $reply;
    }

}
