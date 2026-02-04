<?php

namespace App\Traits;

use PDO;

trait DatabaseCommandTrait
{
    protected function ensureDatabaseExists($dbName)
    {
        try {
            // Conexión temporal a MySQL sin base de datos seleccionada
            $pdo = new PDO(
                sprintf("mysql:host=%s;port=%s", env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306')),
                env('DB_USERNAME'),
                env('DB_PASSWORD')
            );

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $charset = config('database.connections.mysql.charset', 'utf8mb4');
            $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET {$charset} COLLATE {$collation};");

            return true;
        } catch (\Exception $e) {
            $this->error("❌ No se pudo conectar al servidor o crear la DB {$dbName}: " . $e->getMessage());
            return false;
        }
    }
}