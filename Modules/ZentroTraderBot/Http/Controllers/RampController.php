<?php

namespace Modules\ZentroTraderBot\Http\Controllers;

use Modules\Laravel\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redirect;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\ZentroTraderBot\Contracts\RampProviderInterface;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Modules\ZentroTraderBot\Entities\Ramporders;

class RampController extends Controller
{
    private function getProvider($name): RampProviderInterface
    {
        return match ($name) {
            'transak' => app(TransakController::class),
            //'wert' => app(WertController::class),
            //'guardarian' => app(GuardarianController::class),
            default => throw new \Exception("Proveedor no soportado"),
        };
    }

    public function redirect($action, $key, $secret, $user_id)
    {
        // Recuperamos el bot que el Middleware ya encontró y guardó
        $tenant = app('active_bot');
        // Forzamos la consulta a la base de datos del Tenant
        $suscriptor = Suscriptions::on('tenant')->where("user_id", $user_id)->first();
        if (!$suscriptor || !isset($suscriptor->data["wallet"]["address"])) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.suscriptor");
        }

        // Decidir el proveedor de RAMP activo para este bot
        $providerName = $bot->data['ramp'] ?? 'transak';
        $provider = $this->getProvider($providerName);

        $url = $provider->getWidgetUrl($tenant, $suscriptor, strtoupper($action));
        Log::debug("🐞 RampController redirect: success redirect hit " . json_encode(request()->all()));
        if (!$url) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.widgeturl");
        }

        return Redirect::to($url);
    }

    public function success($key, $secret, $user_id)
    {
        // Recuperamos el bot que el Middleware ya encontró y guardó
        $tenant = app('active_bot');
        // Forzamos la consulta a la base de datos del Tenant
        $suscriptor = Suscriptions::on('tenant')->where("user_id", $user_id)->first();
        if (!$suscriptor || !isset($suscriptor->data["wallet"]["address"])) {
            return Lang::get("zentrotraderbot::bot.prompts.fail.suscriptor");
        }

        /*
        redirectURL
        User will be redirected to the partner page passed in redirectURL along with the following parameters appended to the URL:
        orderId: Transak order ID
        fiatCurrency: Payout fiat currency
        cryptoCurrency: Token symbol to be transferred
        fiatAmount: Expected payout fiat amount
        cryptoAmount: Amount of crypto to be transferred
        isBuyOrSell: Will be 'Sell' in case of off ramp
        status: Transak order status
        walletAddress: Destination wallet address where crypto should be transferred
        totalFeeInFiat: Total fee charged in local currency for the transaction
        partnerCustomerId: Partner's customer ID (if present)
        partnerOrderId: Partner's order ID (if present)
        network: Network on which relevant crypto currency needs to be transferred

        https://dev.micalme.com/ramp/success?
        orderId=4d6a0067-2d37-493f-b671-3424f3207f25
        &fiatCurrency=USD
        &cryptoCurrency=USDC
        &fiatAmount=300
        &cryptoAmount=294
        &isBuyOrSell=BUY
        &status=FAILED
        &walletAddress=0xd2531438b90232f4aab4ddfc6f146474e84e1ea1
        &totalFeeInFiat=6
        &isNFTOrder=undefined
        &network=polygon
        */

        // Guardamos en la tabla que creamos (ramporders)
        $this->createRamporder(
            $tenant->id,
            request('orderId'),
            $user_id,
            request('cryptoAmount'),
            request('status'),
            request()->all()
        );

        return Redirect::to("https://t.me/" . $tenant->code);
    }

    public function processWebhook()
    {
        // Recuperamos el bot que el Middleware ya encontró y guardó
        $tenant = app('active_bot');
        // Decidir el proveedor de RAMP activo para este bot
        $providerName = $tenant->data['ramp'] ?? 'transak';
        $provider = $this->getProvider($providerName);

        $array = $provider->processWebhook(request());

        // El provider detecto una actualizacion de Orden
        if (isset($array["order"])) {
            $order = $this->createRamporder(
                $array['botId'],
                $array['orderId'],
                $array['userId'],
                $array['amount'],
                $array['status'],
                $array['payload']
            );
        }
        // El provider detecto una actualizacion de KYC
        if (isset($array["kyc"])) {
            $suscriptor = Suscriptions::on('tenant')->where("user_id", $array['userId'])->first();
            if ($suscriptor) {
                $data = $suscriptor->data;
                $data['kyc'] = $array['status'];
                $suscriptor->data = $data;
                $suscriptor->save();
            }
        }

        return response()->json(['status' => 'processed'], 200);
    }

    private function createRamporder($botId, $orderId, $userId, $amount, $status, $payload)
    {
        // Guardamos en la tabla que creamos (ramporders)
        return Ramporders::updateOrCreate(
            ['order_id' => $orderId],
            [
                'user_id' => $userId,
                'bot_id' => $botId,
                'amount' => $amount,
                'status' => $status,
                'raw_data' => $payload
            ]
        );
    }
}