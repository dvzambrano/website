<?php
namespace Modules\TelegramBot\Database\Seeders;


use Illuminate\Database\Seeder;
use Modules\TelegramBot\Entities\TelegramBots;
use App\Traits\ModuleTrait;

class TelegramBotsSeeder extends Seeder
{
    use ModuleTrait;

    public function run()
    {
        $bots = array(
            array(
                "name" => "@ZentroNotificationBot",
                "token" => "8198488135:AAHjSshi4P3jTy_bPDDNZF2aIzSL0DkGxBg",
            ),
            array(
                "name" => "@GutoTradeBot",
                "token" => "7252174930:AAFJwAZaLrWiP-ONZHQZ7D0ps77HDoMkixQ",
            ),
            array(
                "name" => "@GutoTradeTestBot",
                "module" => "GutoTradeBot",
                "token" => "7543090584:AAEisZYB1NL24Wwwv2xQ2rVChOugyXYLdBU",
            ),
            array(
                "name" => "@IrelandPaymentsBot",
                "module" => "GutoTradeBot",
                "token" => "7286991852:AAG7TSW_hqF1bb-t7KU7toGVFx4SllCEDcM",
            ),
            array(
                "name" => "@ZentroTraderBot",
                "token" => "6989103595:AAH-qQww_v01UnAt9Ex0ZfmVp3qAIR9KXrE",
            ),
            array(
                "name" => "@KashioBot",
                "module" => "ZentroTraderBot",
                "token" => "8244020104:AAFfsZNfirwGJh3xkDvqGHUaje2h1M4iIYQ",
            ),
            array(
                "name" => "@kashio_bot",
                "module" => "ZentroTraderBot",
                "token" => "8000326796:AAEDctWD07mf6fQEGIYuOVyRE4aePzDBjQ8",
            ),
            array(
                "name" => "@ZentroBaseTelegramBot",
                "token" => "6055381762:AAEGjtR7MHpG7GmDIMVlKzxYzBFCBkobots",
            ),
            array(
                "name" => "@ZentroCriptoBot",
                "token" => "5797151131:AAF0o1P3C9wK8zx3OczGej9QmkILZmekJKc",
            ),
            array(
                "name" => "@ZentroLicensorBot",
                "token" => "1450849635:AAHpvMRi6EMdCajw6yZ9G6uma0WV1FF2JCY",
            ),
            array(
                "name" => "@ZentroOwnerBot",
                "token" => "7948651884:AAGI3FjcxYyaRkmuqrLsAZP34vQxz5B2LwA",
            ),
            array(
                "name" => "@ZentroPackageBot",
                "token" => "8597394927:AAGpQPlhe8nzYYz42ExeyuLbBn0ES9xIUAQ",
            ),
        );

        foreach ($bots as $bot) {
            TelegramBots::create([
                "name" => $bot["name"],
                "module" => isset($bot["module"]) ? $bot["module"] : str_replace("@", "", $bot["name"]),
                "token" => $bot["token"],
                "key" => md5($bot["token"]),
                "database" => str_replace("@", "", strtolower($bot["name"])),
                "username" => "root",
                "password" => "usbw",
                "data" => [],
            ]);
        }
    }
}
