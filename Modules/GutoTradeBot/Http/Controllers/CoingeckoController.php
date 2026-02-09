<?php

namespace Modules\GutoTradeBot\Http\Controllers;

use App\Http\Controllers\JsonsController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\GutoTradeBot\Entities\Rates;
use Illuminate\Support\Facades\Http;


class CoingeckoController extends JsonsController
{

    /**
     * Summary of getRate
     * @param mixed $date Y-m-d
     * @param mixed $coin
     * @param mixed $base
     */
    public function getRate($date, $coin = "eur", $base = "tether")
    {
        $rate = $this->getFirst(Rates::class, "date", "=", $date);
        if (!$rate) {
            $array = CoingeckoController::getHistory("eur", "tether", $date);
            $value = $array["direct"];
            if ($value > 0) {
                $rate = Rates::create([
                    'date' => $date,
                    'base' => "tether",
                    'coin' => "eur",
                    'rate' => $value,
                ]);
            } else {
                $rate = Rates::create([
                    'date' => date("Y-m-d"),
                    'base' => "tether",
                    'coin' => "eur",
                    'rate' => 1,
                ]);
            }
        }

        return array(
            "direct" => $rate->rate,
            "inverse" => 1 / $rate->rate,
        );
    }

    /*
     * Summary of getHistory
     * @param mixed $coin "eur"
     * @param mixed $base "tether"
     * @param mixed $date "Y-m-d"
     * @return array|array{direct: int, inverse: int}
     */
    public static function getHistory($coin = "eur", $base = "tether", $date = false)
    {
        if (!$date)
            $date = Carbon::now()->format("d-m-Y");
        else
            $date = Carbon::createFromFormat("Y-m-d", $date)->format("d-m-Y");

        $rate = array(
            "direct" => 0,
            "inverse" => 0,
        );

        //https://api.coingecko.com/api/v3/coins/tether/history?date=11-05-2025&localization=false
        $response = Http::withHeaders([
            "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
        ])->get("https://api.coingecko.com/api/v3/coins/{$base}/history", [
                    "date" => "{$date}",
                    "localization" => "false",
                ]);
        if ($response->successful()) {
            $array = json_decode($response->json(), true);
            if (isset($array["market_data"])) {
                $rate["direct"] = $array["market_data"]["current_price"][$coin];
                $rate["inverse"] = 1 / $rate["direct"];
            } else {
                $date = Carbon::createFromFormat("d-m-Y", $date)->subDay()->format("Y-m-d");
                return CoingeckoController::getHistory("eur", "tether", $date);
            }

        } else {
            Log::error("CoingeckoController getHistory response status: " . $response->status());
        }

        return $rate;

    }
}
