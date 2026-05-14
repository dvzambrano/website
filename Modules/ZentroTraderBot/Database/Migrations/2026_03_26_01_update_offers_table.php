<?php

use Illuminate\Database\Migrations\Migration;

class UpdateOffersTable extends Migration
{
    protected $connection = 'tenant';
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Eliminamos la columna que ya no es necesaria
        getModuleSchema()->table('offers', function ($table) {
            // Primero eliminamos el índice antes de la columna por seguridad en algunos motores
            $table->dropIndex(['blockchain_trade_id']);
            $table->dropColumn('blockchain_trade_id');
        });

        // 2. Ajustamos el AUTO_INCREMENT para que empiece en 1001
        // Nota: Esto es específico para MySQL/PostgreSQL. 
        // Suponiendo que usas MySQL para el Tenant:
        $tableName = config('database.connections.tenant.prefix', '') . 'offers';

        DB::connection('tenant')->statement("ALTER TABLE {$tableName} AUTO_INCREMENT = 1001;");

        /* Si ya tienes datos y quieres moverlos al rango de los 1000, 
           podemos hacer un update masivo (solo si el ID no se usa como FK en otra tabla aún)
        */
        $exists = DB::connection('tenant')->table('offers')->count();
        if ($exists > 0) {
            // Desplazamos los IDs actuales sumándoles 1000
            DB::connection('tenant')->statement("UPDATE {$tableName} SET id = id + 1000;");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        getModuleSchema()->table('offers', function ($table) {
            $table->unsignedBigInteger('blockchain_trade_id')->nullable()->index();
        });
    }
}
