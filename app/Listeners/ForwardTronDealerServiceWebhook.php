<?php

namespace App\Listeners;

use Dvzambrano\Filesystem\Facades\AppLog;
use Dvzambrano\TronDealer\Events\TronDealerDepositConfirmed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * webhook/trondealer (segmento {service} vacío, service = 'default') sigue
 * procesándose aquí mismo sin cambios.
 *
 * webhook/trondealer/{service} (ej. "kashio") se reenvía tal cual (body raw
 * + firma) a {service}.{dominio actual}/webhook/trondealer — el subdominio
 * es literalmente el nombre del segmento de la URL, que hace su propia
 * validación con el mismo webhook_secret.
 */
class ForwardTronDealerServiceWebhook
{
    public function handle(TronDealerDepositConfirmed $event): void
    {
        if ($event->service === 'default') {
            return;
        }

        $targetUrl = request()->getScheme() . '://' . $event->service . '.' . request()->getHost() . '/webhook/trondealer';

        try {
            $response = Http::withHeaders(['X-Signature-256' => $event->signature])
                ->withBody($event->rawBody, 'application/json')
                ->timeout((int) config('services.trondealer_service_forward.timeout', 15))
                ->send('POST', $targetUrl);

            AppLog::info('TronDealer webhook forwarded to service subdomain', [
                'target' => $targetUrl,
                'status' => $response->status(),
            ]);
        } catch (ConnectionException $e) {
            AppLog::error('Failed to forward TronDealer webhook to service subdomain', [
                'target' => $targetUrl,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
