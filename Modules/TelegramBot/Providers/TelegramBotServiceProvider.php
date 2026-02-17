<?php

namespace Modules\TelegramBot\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\TelegramBot\Console\MigrateAndSeedModules;
use Modules\TelegramBot\Console\GetTelegramWebhook;
use Modules\TelegramBot\Console\SetTelegramWebhook;
use Modules\TelegramBot\Console\ResetTelegramWebhooks;
use Modules\TelegramBot\Console\BotSimulate;
use Illuminate\Routing\Router;
use Modules\TelegramBot\Middleware\TenantMiddleware;
use Modules\TelegramBot\Middleware\TelegramBotDataMiddleware;
use Modules\TelegramBot\Middleware\TelegramIsAuthenticatedMiddleware;

class TelegramBotServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'TelegramBot';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'telegrambot';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(Router $router)
    {
        // Registramos el alias del middleware dinámicamente
        $router->aliasMiddleware('tenant.detector', TenantMiddleware::class);
        $router->aliasMiddleware('telegrambot.detector', TelegramBotDataMiddleware::class);
        $router->aliasMiddleware('telegrambot.auth', TelegramIsAuthenticatedMiddleware::class);

        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        // Registramos el comando para migrar los modulos
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateAndSeedModules::class,
                GetTelegramWebhook::class,
                SetTelegramWebhook::class,
                ResetTelegramWebhooks::class,
                BotSimulate::class,
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
