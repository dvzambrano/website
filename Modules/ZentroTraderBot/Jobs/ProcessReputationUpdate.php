<?php

namespace Modules\ZentroTraderBot\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\ZentroTraderBot\Entities\Suscriptions;
use Modules\TelegramBot\Entities\TelegramBots;
use Modules\Laravel\Services\BehaviorService;

class ProcessReputationUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $stars;
    protected $tenant;

    /**
     * Create a new job instance.
     *
     * @param int $userId El ID de Telegram del usuario calificado
     * @param int $stars  La cantidad de estrellas (1-5)
     * @param string $tenant La clave del tenant para reconectar en el worker
     */
    public function __construct($userId, $stars, $tenant)
    {
        $this->userId = $userId;
        $this->stars = $stars;
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // 1. Reconexión al Tenant (Vital para Jobs en segundo plano)
            $bot = BehaviorService::cache('tenant_' . $this->tenant, function () {
                return TelegramBots::where('key', $this->tenant)->first();
            });

            if (!$bot) {
                Log::error("❌ [ReputationJob] Tenant no encontrado: {$this->tenant}");
                return;
            }
            $bot->connectToThisTenant();

            // 2. Buscar la suscripción del usuario calificado
            $suscription = Suscriptions::on('tenant')->where('user_id', $this->userId)->first();

            if (!$suscription) {
                Log::warning("⚠️ [ReputationJob] Suscripción no encontrada para User ID: {$this->userId}");
                return;
            }

            // 3. Ejecutar la lógica de actualización (la que ya definimos en el Modelo)
            $suscription->updateReputation($this->stars);

            // Log de éxito (opcional, útil en fase de pruebas)
            if (env("DEBUG_MODE", false)) {
                Log::info("✅ Reputación actualizada para User {$this->userId} en Tenant {$this->tenant}");
            }

        } catch (\Throwable $th) {
            Log::error("🆘 ProcessReputationUpdate handle error: " . $th->getMessage(), [
                'user_id' => $this->userId,
                'stars' => $this->stars,
                'tenant' => $this->tenant
            ]);
        }
    }
}