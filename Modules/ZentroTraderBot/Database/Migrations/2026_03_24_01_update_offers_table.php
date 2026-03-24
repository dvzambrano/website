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
        // 1. Añadimos las nuevas columnas como NULLABLE inicialmente
        getModuleSchema()->table('offers', function ($table) {
            if (!Schema::connection('tenant')->hasColumn('offers', 'winner_address')) {
                $table->text('winner_address')->nullable()->after('buyer_address');
            }

            if (!Schema::connection('tenant')->hasColumn('offers', 'data')) {
                $table->jsonb('data')->nullable()->after('tx_hash_release');
            }
        });

        // 3. Aplicamos las restricciones de integridad y cambios de ENUM
        getModuleSchema()->table('offers', function ($table) {
            // Actualizamos el ENUM de status
            // NOTA: En MySQL, cambiar un ENUM con datos puede ser caprichoso. 
            // Si el driver falla, se puede usar DB::statement (ver abajo).
            $table->enum('status', [
                'locked',    // LOCKED en contrato
                'disputed',  // DISPUTED en contrato
                'completed', // COMPLETED (fondos liberados)
                'cancelled', // CANCELLED (fondos devueltos)
                'signed',     // Solo local (oculto en el bot)
                'solved',     // Solo local (oculto en el bot)
                'expired',     // Solo local (oculto en el bot)
            ])->default('locked')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        getModuleSchema()->table('offers', function ($table) {
            $table->dropColumn(['winner_address', 'data']);

            // Revertimos el ENUM al estado anterior si es necesario
            $table->enum('status', [
                'locked',
                'disputed',
                'completed',
                'cancelled',
                'signed'
            ])->default('locked')->change();
        });
    }
}
