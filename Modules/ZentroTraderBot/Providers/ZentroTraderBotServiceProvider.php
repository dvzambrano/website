<?php

namespace Modules\ZentroTraderBot\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Modules\ZentroTraderBot\Entities\Ramporders;
use Modules\ZentroTraderBot\Observers\RamporderObserver;
use Modules\ZentroTraderBot\Entities\Offers;
use Modules\ZentroTraderBot\Observers\OfferObserver;
use Illuminate\Support\Facades\Event;
use Modules\Web3\Events\BlockchainActivityDetected;
use Modules\ZentroTraderBot\Listeners\ProcessBlockchainActivity;
use Modules\ZentroTraderBot\Console\SyncAlchemyAddresses;
use Modules\TelegramBot\Middleware\TenantMiddleware;

class ZentroTraderBotServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'ZentroTraderBot';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'zentrotraderbot';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('tenant', TenantMiddleware::class);

        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        // Registramos el observador para el modelo Ramporders: notifica al usuario cuando hay movimiento en su Orden hecha a traves del RAMP
        Ramporders::observe(RamporderObserver::class);
        // Registramos el observador para el modelo Offer
        Offers::observe(OfferObserver::class);
        // Registramos el evento q escuchamos cuando Alchemy manda info al webhook del modulo Web3
        Event::listen(
            BlockchainActivityDetected::class,
            ProcessBlockchainActivity::class
        );
        // Registramos el comando para sincronizar manualmente wallets existentes con Alchemy
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncAlchemyAddresses::class,
            ]);
        }

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
