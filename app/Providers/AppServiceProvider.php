<?php

namespace App\Providers;

use App\Listeners\ForwardTronDealerServiceWebhook;
use Dvzambrano\TronDealer\Events\TronDealerDepositConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Visor de logs (/logs): solo administradores autenticados. El paquete
        // dvzambrano/filesystem lee este middleware al cargar sus rutas; se
        // fija aquí con la notación de punto para no pisar el resto de su config.
        // 2fa va antes que admin: un admin con 2FA activo debe verificar el código.
        config(['filesystem.logs.middleware' => ['auth', '2fa', 'admin']]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TronDealerDepositConfirmed::class, ForwardTronDealerServiceWebhook::class);
    }
}
