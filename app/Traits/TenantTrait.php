<?php

namespace App\Traits;

trait TenantTrait
{
    /**
     * Determina dinámicamente qué conexión usar.
     */
    public function getConnectionName()
    {
        // 1. Si estamos en medio de una ejecución de un Bot (Middleware o Comando)
        // y la conexión 'tenant' ha sido configurada, la usamos.
        if (config('database.connections.tenant.database')) {
            return 'tenant';
        }

        // 2. Si no hay tenant (ej. estamos en el Admin del sitio principal),
        // usamos la conexión por defecto (mysql/sistema central).
        return config('database.default');
    }

    /**
     * Sobreescribimos el método de Eloquent para que Laravel 
     * llame a nuestra lógica automáticamente.
     */
    public function getConnectionNameAttribute()
    {
        return $this->getConnectionName();
    }
}
