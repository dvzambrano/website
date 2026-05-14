<?php

namespace Modules\ZentroTraderBot\Tests;

use Tests\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\App;

class TestCase extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 3. Ahora "pisamos" la configuración que queremos cambiar para el test
        Config::set('cache.default', 'file');

        $cachePath = base_path('Modules/ZentroTraderBot/storage/framework/cache/data');
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        Config::set('cache.stores.file.path', $cachePath);

        // 4. Forzamos el entorno
        App::detectEnvironment(fn() => 'local');

        // El .env ya debería estar cargado por Laravel automáticamente, 
        // pero si necesitas valores específicos, usa Config::set() aquí.
    }
}