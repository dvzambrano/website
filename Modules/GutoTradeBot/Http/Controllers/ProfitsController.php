<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\JsonsController;
use Modules\GutoTradeBot\Entities\Profits;
use Modules\TelegramBot\Entities\Actors;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;

class ProfitsController extends JsonsController
{
    public function getAll()
    {
        return Profits::where("id", ">", 0)->get();
    }

    public function getPrompt($bot)
    {
        $tenant = app('active_bot');
        $bot->ActorsController->updateData(Actors::class, "user_id", $bot->actor->user_id, "last_bot_callback_data", "promptprofit", $tenant->code);

        $salary = $this->getFirst(Profits::class, "name", "=", "salary");
        $salary->save();

        $profit = $this->getFirst(Profits::class, "name", "=", "profit");

        $reply = array(
            "text" => "🤑 *" . TextService::mdv2(Lang::get('gutotradebot::bot.profits.title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.profits.desc')) . "_\n\n" .
                "*Ejemplo:* `1:7`\n_1% de salario y 7% de ganancias_\n\n" .
                "*" . TextService::mdv2(Lang::get('gutotradebot::bot.profits.current')) . "* `" . $salary->value . ":" . $profit->value . "`\n_" . TextService::mdv2((string)$salary->value) . TextService::mdv2(Lang::get('gutotradebot::bot.profits.salary_label')) . TextService::mdv2((string)$profit->value) . TextService::mdv2(Lang::get('gutotradebot::bot.profits.profit_label')) . "\n" .
                TextService::mdv2(Lang::get('gutotradebot::bot.profits.total_rate')) . TextService::mdv2((string)($salary->value + $profit->value)) . "%_\n\n" .
                "👇 " . TextService::mdv2(Lang::get('gutotradebot::bot.profits.prompt')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [["text" => "✋ " . TextService::mdv2(Lang::get('telegrambot::bot.options.cancel')), "callback_data" => "adminmenu"]],
                ],
            ]),
        );

        return $reply;
    }

    public function notifyAfterChange()
    {
        $reply = array(
            "text" => "🤑 *" . TextService::mdv2(Lang::get('gutotradebot::bot.profits.updated_title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.profits.updated_desc')) . "_\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
            "reply_markup" => json_encode([
                "inline_keyboard" => [
                    [
                        ["text" => "↖️ " . TextService::mdv2(Lang::get('telegrambot::bot.options.backtoadminmenu')), "callback_data" => "adminmenu"],
                    ],

                ],
            ]),
        );

        return $reply;
    }

    public function update($new_salary, $new_profit)
    {
        $salary = $this->getFirst(Profits::class, "name", "=", "salary");
        $salary->value = $new_salary;
        $salary->save();

        $profit = $this->getFirst(Profits::class, "name", "=", "profit");
        $profit->value = $new_profit;
        $profit->save();
    }

    public function calculateFlow($amount, $rate, $salary_percent = false, $profit_percent = false)
    {
        $arrival = $amount * $rate;

        if ($salary_percent === false) {
            $salary = $this->getFirst(Profits::class, "name", "=", "salary");
            $salary_percent = $salary->value;
        }
        $salary = $this->getSalary($arrival, $salary_percent);

        $towork = $arrival - $salary;

        if ($profit_percent === false) {
            $profit = $this->getFirst(Profits::class, "name", "=", "profit");
            $profit_percent = $profit->value;
        }
        $tosend_percent = $profit_percent + $salary_percent;
        $profit = $towork * $tosend_percent / 100;
        $tosend = $towork + $profit;

        return array(
            "arrival" => $arrival,
            "capital" => $towork,
            "waste" => array(
                "amount" => $salary,
                "percent" => $salary_percent,
            ),
            "output" => array(
                "amount" => $profit,
                "percent" => $tosend_percent,
            ),
            "profit" => array(
                "amount" => $towork + $profit,
                "percent" => $towork + $profit - $amount,
            ),
        );
    }

    public function getSalary($amount = false, $percent = false)
    {
        if ($percent === false) {
            $salary = $this->getFirst(Profits::class, "name", "=", "salary");
            $percent = $salary->value;
        }

        if (!$amount) {
            return $percent;
        }

        return $amount * $percent / 100;
    }

    public function getProfit($amount = false, $percent = false, $salary_percent = false)
    {
        if ($percent === false) {
            $profit = $this->getFirst(Profits::class, "name", "=", "profit");
            $percent = $profit->value;
        }

        if ($amount === false) {
            return $percent;
        }

        if ($salary_percent === false) {
            $salary = $this->getFirst(Profits::class, "name", "=", "salary");
            $salary_percent = $salary->value;
        }

        return $amount * ($percent + $salary_percent) / 100;
    }

    // calculo de USDT procesados dada la cantidad de Euros enviados
    public function getSpended($amount, $percent = false, $salary_percent = false)
    {

        if ($percent === false) {
            $profit = $this->getFirst(Profits::class, "name", "=", "profit");
            $percent = $profit->value;
        }

        if ($salary_percent === false) {
            $salary = $this->getFirst(Profits::class, "name", "=", "salary");
            $salary_percent = $salary->value;
        }

        $rate = $salary_percent + $percent;
        $procesados = $amount * ((100 - $rate) / 100);
        if ($rate < 0)
            $procesados = $amount * (1 + abs($rate) / 100);

        return $procesados;
    }

    // calculo de USDT ganados dada la cantidad de Euros enviados
    public function getEarned($amount)
    {
        $salary = $this->getFirst(Profits::class, "name", "=", "salary");

        $procesados = $this->getSpended($amount);

        $total = $procesados * 100 / (100 - $salary->value);

        return $this->getSalary($total);
    }

    // calculos de EUROS a enviar dada la cantidad de USDT recibida
    public function getEURtoSendWithActiveRate($amount, $percent = false, $salary_percent = false)
    {
        if ($percent === false) {
            $profit = $this->getFirst(Profits::class, "name", "=", "profit");
            $percent = $profit->value;
        }

        if ($salary_percent === false) {
            $salary = $this->getFirst(Profits::class, "name", "=", "salary");
            $salary_percent = $salary->value;
        }

        // este es el valor q se va a enviar luego de descontar el 1% del pago de mi salario
        $value = $amount - ($amount * $salary_percent / 100);
        $value = $value + ($value * ($percent + $salary_percent) / 100);

        return $value;
    }

    // calculos de USDT a enviar dada la cantidad de EUROS recibida
    public function getUSDTtoSendWithActiveRate($amount, $percent = false, $salary_percent = false)
    {
        if ($percent === false) {
            $profit = $this->getFirst(Profits::class, "name", "=", "profit");
            $percent = $profit->value;
        }

        if ($salary_percent === false) {
            $salary = $this->getFirst(Profits::class, "name", "=", "salary");
            $salary_percent = $salary->value;
        }

        $rate = $salary_percent + $percent;
        $procesados = $amount * ((100 - $rate) / 100);
        if ($rate < 0)
            $procesados = $amount * (1 + abs($rate) / 100);

        return $procesados;
    }

    // calculo de USDT recibidos dada la cantidad de Euros enviados
    public function getUSDTreceived($amount)
    {
        if (floatval($amount) == 0)
            return 0;
        return $this->getSpended($amount) + $this->getEarned($amount);
    }

}
