<?php

namespace Modules\ZentroTraderBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\Web3\Http\Controllers\AlchemyController;

class SyncAlchemyAddresses extends Command
{
    protected $signature = 'zentrotraderbot:sync-alchemy 
                        {--webhookid= : El ID del webhook de Alchemy} 
                        {--authtoken= : El Token de autenticación de Alchemy}';
    protected $description = 'Registra todas las wallets de los suscriptores en el webhook de Alchemy';

    public function handle()
    {
        // Capturamos los valores de los parámetros
        $webhookId = $this->option('webhookid');
        $authToken = $this->option('authtoken');
        // Validación de seguridad
        if (!$webhookId || !$authToken) {
            $this->error('❌ Faltan parámetros. Uso: php artisan zentrotraderbot:sync-alchemy --webhookid=XXX --authtoken=YYY');
            return;
        }
        $this->info("Iniciando sincronización con Alchemy Webhook ID: {$webhookId}");

        // 1. Obtener todas las wallets únicas de tus suscriptores
        // Nota: Asegúrate de que el path del JSON sea el correcto para tu DB
        $addresses = Suscriptions::all()
            ->map(fn($s) => $s->data['wallet']['address'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($addresses)) {
            $this->warn('No se encontraron wallets para sincronizar.');
            return;
        }

        $this->info('Enviando ' . count($addresses) . ' direcciones a Alchemy...');

        // 2. Llamada a la API de Alchemy (Update Webhook)
        $response = AlchemyController::updateWebhookAddresses($webhookId, $authToken, $addresses);
        if ($response->successful()) {
            $this->info('✅ Sincronización exitosa. Alchemy ahora vigila estas wallets.');
        } else {
            $this->error('❌ Error en Alchemy: ' . $response->body());
        }
    }
}