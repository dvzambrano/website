<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase as BaseRefreshDatabase;

/**
 * RefreshDatabase limitado a las migraciones del proyecto: los paquetes de
 * bots registran las suyas con loadMigrationsFrom() y apuntan a la conexión
 * `tenant`, que aquí no existe (cada bot se prueba en su propio repo).
 */
trait RefreshDatabase
{
    use BaseRefreshDatabase;

    protected function migrateUsing()
    {
        return [
            '--seed' => $this->shouldSeed(),
            '--seeder' => $this->seeder(),
            '--path' => 'database/migrations',
        ];
    }

    protected function migrateFreshUsing()
    {
        return array_merge(
            [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ],
            $this->migrateUsing()
        );
    }
}
