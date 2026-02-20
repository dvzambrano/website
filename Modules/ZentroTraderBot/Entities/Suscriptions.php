<?php

namespace Modules\ZentroTraderBot\Entities;

use Modules\TelegramBot\Entities\Actors;
use Modules\Laravel\Traits\TenantTrait;
use Modules\ZentroTraderBot\Http\Controllers\TraderWalletController;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;


class Suscriptions extends Actors
{
    use TenantTrait;

    protected $table = "suscriptions";

    public function getWallet()
    {
        $currentData = $this->data ?? [];
        $wallet = array();
        // si el usuario no tiene wallet es recien suscrito y hay q completar su estructura
        if (!isset($currentData["wallet"])) {
            $tenant = app('active_bot');

            $wc = new TraderWalletController();
            $wallet = $wc->getWallet($tenant);
            if (!$wallet)
                return null;

            $this->data = array(
                "admin_level" => 0,
                "suscription_level" => 0,
                "wallet" => [
                    'address' => $wallet["address"],
                    'private_key' => Crypt::encryptString($wallet["private_key"]), // 🔒 ENCRIPTADO
                    'created_at' => now()->toIso8601String()
                ]
            );
            $this->save();
            Log::debug("✅ Wallet " . $wallet["address"] . " generada en JSON para usuario " . $this->user_id);

        } else
            $wallet = $this->data["wallet"];

        return $wallet;
    }
}
