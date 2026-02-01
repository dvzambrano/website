<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class MigrateAndSeedModules extends Command
{
    protected $signature = 'modules:migrate-seed';
    protected $description = 'Run migrations and seeders for all modules and the main project';

    public function handle()
    {
        // Ejecutar migrate:fresh para la base de datos general
        $this->info('Running fresh migrations for the main project...');
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--path' => 'database/migrations', // Especificamos la ruta relativa a la raíz
        ], $this->getOutput());


        // Debe estar en Mayusculas para q Linux lo reconozca porq es sencible a may/min;
        $modulesPath = 'Modules';
        // Usamos una versión más compatible con PHP anterior a 7.4
        $modules = array_diff(scandir($modulesPath), ['.', '..']);

        foreach ($modules as $module) {
            $this->runModuleMigrationsAndSeeders($module, "{$modulesPath}/{$module}");
        }

        $this->info('All fresh migrations and seeders have been run successfully.');
        return 0;
    }

    protected function runModuleMigrationsAndSeeders($module, $modulePath)
    {
        $dbName = config("database.connections.{$module}.database");
        if (!$dbName) {
            $this->warn("No database configured for connection: {$module}");
            return;
        }

        $this->info("Resetting database for module: {$module}");
        $this->resetDatabase($module);

        $this->info("Running migrations for module: {$module}");
        $migrationPath = "{$modulePath}/Database/Migrations";
        if (is_dir($migrationPath)) {

            Artisan::call('migrate', ['--database' => $module, '--path' => $migrationPath], $this->getOutput());
        } else {
            $this->warn("Migration path not found for module: {$module}");
        }

        $this->info("Running seeder for module: {$module}");
        $seederClass = "Modules\\{$module}\\Database\\Seeders\\{$module}DatabaseSeeder";
        if (class_exists($seederClass)) {
            Artisan::call('db:seed', ['--database' => $module, '--class' => $seederClass], $this->getOutput());
        } else {
            $this->warn("Seeder class {$seederClass} not found for module: {$module}. (CHECK composer autoload psr-4!!)");
        }
    }

    protected function resetDatabase($connection)
    {
        $dbName = config("database.connections.{$connection}.database");

        // Consultamos las tablas de la base de datos específica
        $tables = DB::connection($connection)->select("
        SELECT table_name AS name 
        FROM information_schema.tables 
        WHERE table_schema = ?",
            [$dbName]
        );

        // Usamos 'name' que definimos en el alias del SQL para evitar problemas de mayúsculas
        $tableNames = array_map(function ($table) {
            return $table->name;
        }, $tables);

        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tableNames as $table) {
            try {
                DB::connection($connection)->table($table)->truncate();
            } catch (QueryException $e) {
                // Ignorar errores de tablas que no existen
                if (!str_contains($e->getMessage(), "doesn't exist")) {
                    throw $e;
                }
            }
        }

        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
