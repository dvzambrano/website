<?php
namespace Modules\TelegramBot\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Modules\TelegramBot\Entities\TelegramBots;
use App\Traits\DatabaseCommandTrait;

class MigrateAndSeedModules extends Command
{
    use DatabaseCommandTrait;

    protected $signature = 'modules:migrate-seed';
    protected $description = 'Run migrations and seeders for all modules and the main project';

    public function handle()
    {
        // PROYECTO -------------------

        $dbName = config("database.connections.mysql.database");
        $this->ensureDatabaseExists($dbName);

        // Ejecutar migrate:fresh para la base de datos general
        $this->info('  ℹ️  Running fresh migrations for the main project...');
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--path' => 'database/migrations', // Especificamos la ruta relativa a la raíz
        ], $this->getOutput());

        $this->info('  🟢  All fresh migrations and seeders for the main project ran successfully.');
        $this->info('');


        // MODULES -------------------



        // Debe estar en Mayusculas para q Linux lo reconozca porq es sencible a may/min;
        $modulesPath = 'Modules';
        // Usamos una versión más compatible con PHP anterior a 7.4
        $modules = array_diff(scandir($modulesPath), ['.', '..']);

        foreach ($modules as $module) {
            $this->runModuleMigrationsAndSeeders($module, "{$modulesPath}/{$module}");
        }

        $this->info('  🟢  All tenants migrations and seeders ran successfully.');
        $this->info('');





        // TENANTS -------------------

        $this->runTenantMigrations();

        $this->info('  🟢  All tenants migrations and seeders ran successfully.');
        $this->info('');
        return 0;
    }

    protected function runModuleMigrationsAndSeeders($module, $modulePath)
    {
        config([
            'database.connections.tenant' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => strtolower($module),
                'username' => env('DB_USERNAME'),
                'password' => env('DB_PASSWORD'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);
        DB::purge('tenant');



        $dbName = config("database.connections.{$module}.database");
        if (!$dbName) {
            $this->info("  ⚠️  No database configured for connection: {$module}");
            $this->info('');
            return;
        }
        $this->ensureDatabaseExists($dbName);

        $this->info("  🧹  Resetting database for module: {$module}");

        $this->info("  ⚙️  Running migrations for module: {$module}");
        $migrationPath = "{$modulePath}/Database/Migrations";
        if (is_dir($migrationPath)) {

            Artisan::call('migrate:fresh', [
                '--database' => $module,
                '--path' => $migrationPath
            ], $this->getOutput());

        } else {
            $this->info("  ❌  Migration path not found for module: {$module}");
            $this->info('');
        }

        $this->info("  📖  Running seeder for module: {$module}");
        $seederClass = "Modules\\{$module}\\Database\\Seeders\\{$module}DatabaseSeeder";
        if (class_exists($seederClass)) {
            Artisan::call('db:seed', ['--database' => $module, '--class' => $seederClass], $this->getOutput());
        } else {
            $this->info("  ⚠️  Seeder class {$seederClass} not found for module: {$module}. (CHECK composer autoload psr-4!!)");
            $this->info('');
        }
    }

    protected function runTenantMigrations()
    {
        $tenants = TelegramBots::all();
        foreach ($tenants as $tenant) {
            if (empty($tenant->database)) {
                $this->info("  ❌  {$tenant->name} no tiene DB asignada.");
                $this->info('');
                return;
            }

            $this->info("  ℹ️  Migrando Tenant: {$tenant->name} (DB: {$tenant->database}) " . 'Modules/' . $tenant->module . '/Database/Migrations');

            config([
                'database.connections.tenant' => [
                    'driver' => 'mysql',
                    'host' => env('DB_HOST', '127.0.0.1'),
                    'port' => env('DB_PORT', '3306'),
                    'database' => $tenant->database,
                    'username' => $tenant->username ?: env('DB_USERNAME'),
                    'password' => $tenant->password ?: env('DB_PASSWORD'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ]
            ]);

            DB::purge('tenant');

            try {
                // 1. Verificamos que la base de datos exista físicamente (por si acaso)
                $this->ensureDatabaseExists($tenant->database);

                $migrationPath = 'Modules/' . $tenant->module . '/Database/Migrations';

                if (is_dir(base_path($migrationPath))) {
                    $this->info("  🚀 Ejecutando fresh migration para {$tenant->database}...");

                    // 2. Usamos migrate:fresh para que Laravel borre las tablas existentes 
                    // y la tabla 'migrations' antes de empezar, evitando el error 1050.
                    Artisan::call('migrate:fresh', [
                        '--database' => 'tenant',
                        '--path' => $migrationPath, // No hace falta base_path si no usas realpath, o úsalos ambos
                        '--force' => true
                    ], $this->getOutput());

                    // 3. Opcional: Si tienes Seeders específicos para el bot
                    $seederClass = "Modules\\{$tenant->module}\\Database\\Seeders\\{$tenant->module}DatabaseSeeder";
                    if (class_exists($seederClass)) {
                        Artisan::call('db:seed', [
                            '--database' => 'tenant',
                            '--class' => $seederClass
                        ], $this->getOutput());
                    }
                }
            } catch (\Exception $e) {
                $this->error("❌ Error en {$tenant->name}: " . $e->getMessage());
                $this->info('');
            }
        }
    }
}
