<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\JsonsController;
use Modules\GutoTradeBot\Entities\Comments;
use Modules\TelegramBot\Entities\Actors;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;

class CommentsController extends JsonsController
{
    public function create($comment, $screenshot, $sender_id, $payment_id, $data = array())
    {
        $money = Comments::create([
            'comment' => $comment,
            'screenshot' => $screenshot,
            'sender_id' => $sender_id,
            'payment_id' => $payment_id,
            'data' => $data,
        ]);
        return $money;
    }

    public function getByPaymentIdQuery($payment_id)
    {
        $query = Comments::where('payment_id', $payment_id);

        return $query;
    }
    public function getByPaymentId($payment_id)
    {
        return $this->getByPaymentIdQuery($payment_id)->get();
    }

    public function getMessageTemplate($bot, $comment, $to_id)
    {
        $tenant = app('active_bot');

        $actor = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $to_id);

        $fullname = "";
        $sender = $bot->ActorsController->getFirst(Actors::class, "user_id", "=", $comment->sender_id);

        switch ($sender->data[$tenant->code]["admin_level"]) {
            case 1:
            case "1":
            case 4:
            case "4":
                $fullname = "👮‍♂️ Admin";
                break;
            case 2:
            case "2":
                $suscriptor = $bot->AgentsController->getSuscriptor($bot, $comment->sender_id, true);
                $fullname = $suscriptor->getTelegramInfo($bot, "full_name");
                break;
            case 3:
            case "3":
                $fullname = "🤵 " . TextService::mdv2(Lang::get('gutotradebot::bot.roles.supervisor'));
                break;

            default:
                # code...
                break;
        }

        $created_at = $actor->getLocalDateTime($comment->created_at, $tenant->code);

        $commentQuote = ">" . implode("\n>", explode("\n", TextService::mdv2($comment->comment)));
        $text = TextService::mdv2($fullname) . " 💬\n📅 " . TextService::mdv2($created_at) . "\n\n" . $commentQuote;

        return array(
            "message" => array(
                "text" => $text,
                "photo" => $comment->screenshot ? $comment->screenshot : false,
                "parse_mode" => "MarkdownV2",
                "chat" => array(
                    "id" => $to_id,
                ),
            ),
        );
    }

    public function notifyAfterComment()
    {
        $reply = array(
            "text" => "💬 *" . TextService::mdv2(Lang::get('gutotradebot::bot.comment.sent_title')) . "*\n_" . TextService::mdv2(Lang::get('gutotradebot::bot.comment.sent_desc')) . "_\n\n👇 " . TextService::mdv2(Lang::get('telegrambot::bot.prompts.whatsnext')),
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

}
