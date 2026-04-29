<?php

namespace Modules\ZentroTraderBot\Entities;

use Modules\TelegramBot\Entities\Actors;
use Modules\Laravel\Traits\TenantTrait;
use Modules\ZentroTraderBot\Http\Controllers\TraderWalletController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Lang;
use Modules\Laravel\Services\TextService;
use Carbon\Carbon;
use Modules\ZentroTraderBot\Entities\Offers;

/**
 * @property int $id
 * @property int $user_id
 * @property array|null $data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Actors|null $actor
 */
class Suscriptions extends Actors
{
    use TenantTrait;

    protected $table = "suscriptions";

    public function actor()
    {
        // Vinculamos por el user_id que ambos comparten
        return $this->hasOne(Actors::class, 'user_id', 'user_id');
    }
    public function getActor()
    {
        return $this->actor ? $this->actor : null;
    }

    public function getWallet()
    {
        $currentData = $this->data ?? [];
        $wallet = array();
        // si el usuario no tiene wallet es recien suscrito y hay q completar su estructura
        if (!isset($currentData["wallet"])) {
            $wc = new TraderWalletController();
            $wallet = $wc->getWallet();
            if (!$wallet)
                return null;

            $this->data = array(
                "admin_level" => 0,
                "suscription_level" => 0,
                "wallet" => [
                    'status' => $wallet["status"],
                    'address' => $wallet["address"],
                    'private_key' => encryptValue($wallet["private_key"]), // 🔒 ENCRIPTADO
                    'seed_phrase' => encryptValue($wallet["seed_phrase"]), // 🔒 ENCRIPTADO
                    'created_at' => now()->toIso8601String()
                ]
            );
            $this->save();
            if (env("DEBUG_MODE", false))
                Log::debug("🐞 Suscriptions getWallet: Wallet " . $wallet["address"] . " generada en JSON para usuario " . $this->user_id);

        } else
            $wallet = $this->data["wallet"];

        return $wallet;
    }

    public static function findByAddress($address)
    {
        if (!$address)
            return null;

        return self::on('tenant')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(data, "$.wallet.address"))) = ?', [strtolower($address)])
            ->first();
    }

    public function updateReputation($stars)
    {
        $data = $this->data ?? [];
        $reputation = $data['reputation'] ?? [
            'trades' => 1,
            'stars' => 5,
            'average' => 5,
            'vip' => false
        ];

        // Actualizamos valores
        $reputation['trades']++;
        $reputation['stars'] += $stars;
        $reputation['average'] = round($reputation['stars'] / $reputation['trades'], 2);

        // Lógica VIP de Kashio
        if ($reputation['trades'] >= 50 && $reputation['average'] == 5.00) {
            $reputation['vip'] = true;
        } else {
            $reputation['vip'] = false;
        }

        $data['reputation'] = $reputation;
        $this->update(['data' => $data]);
    }

    public function getBalance()
    {
        $balance = 0;
        try {
            $walletController = new TraderWalletController();
            // 3. Obtener Balance REAL (específicamente de BASE_TOKEN en Polygon)
            $balance = $walletController->getBalance($this);
        } catch (\Throwable $th) {

        }
        // Estados que consideramos como "dinero retenido en Escrow"
        $activeStatuses = ['LOCKED', 'SIGNED', 'DISPUTED', 'EXPIRED'];
        $address = strtolower($this->data['wallet']['address']);
        $buyerBalance = Offers::asBuyer($address)->whereIn('status', $activeStatuses)->sum('amount');
        $sellerBalance = Offers::asSeller($address)->whereIn('status', $activeStatuses)->sum('amount');

        $balanceText = "";
        if ($balance > 0)
            $balanceText .= "\n\n💵 *" . TextService::mdv2(Lang::get("zentrotraderbot::bot.prompts.balance.available")) . "*: ||" . TextService::mdv2(number_format($balance, 2)) . " USD||";
        else
            $balanceText .= "\n";
        if ($sellerBalance > 0)
            $balanceText .= "\n🔒 *" . TextService::mdv2(Lang::get("zentrotraderbot::bot.prompts.balance.locked")) . "*: ||" . TextService::mdv2(number_format($sellerBalance, 2)) . " USD||";


        return [
            "amount" => $balance,
            "escrow" => [
                "buyer" => $buyerBalance,
                "seller" => $sellerBalance,
            ],
            "text" => $balanceText,
        ];
    }

    public function updateStats(array $offerData): void
    {
        $data = $this->data ?? [];
        $stats = $data['stats'] ?? [];

        // Primera vez que se completa un trade: registrar antigüedad
        if (empty($stats['member_since'])) {
            $stats['member_since'] = $offerData['locked_at'] ?? now()->timestamp;
        }

        // Tasa de completado — calculada desde la BD para exactitud
        $address = strtolower($this->data['wallet']['address'] ?? '');
        if ($address) {
            $total = Offers::asSeller($address)
                ->whereIn('status', ['completed', 'cancelled', 'expired'])
                ->count();
            $completed = Offers::asSeller($address)->where('status', 'completed')->count();
            $stats['completion_rate'] = $total > 0 ? round(($completed / $total) * 100, 1) : 100.0;
        }

        $completedAt = $offerData['completed_at'] ?? now()->timestamp;

        // Tiempo de respuesta: desde que el comprador envió comprobante hasta que el vendedor confirmó
        $signedAt = $offerData['signed_at'] ?? null;
        if ($signedAt && $completedAt > $signedAt) {
            $minutes = round(($completedAt - $signedAt) / 60);
            $n = ($stats['response_samples'] ?? 0) + 1;
            $prev = $stats['avg_response_minutes'] ?? 0;
            $stats['avg_response_minutes'] = (int) round($prev + ($minutes - $prev) / $n);
            $stats['response_samples'] = $n;
        }

        // Tiempo de liberación: desde que el trade se bloqueó hasta que se completó
        $lockedAt = $offerData['locked_at'] ?? null;
        if ($lockedAt && $completedAt > $lockedAt) {
            $minutes = round(($completedAt - $lockedAt) / 60);
            $n = ($stats['release_samples'] ?? 0) + 1;
            $prev = $stats['avg_release_minutes'] ?? 0;
            $stats['avg_release_minutes'] = (int) round($prev + ($minutes - $prev) / $n);
            $stats['release_samples'] = $n;
        }

        $data['stats'] = $stats;
        $this->update(['data' => $data]);
    }
}
