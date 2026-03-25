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
            if (!Schema::connection('tenant')->hasColumn('offers', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }

            if (!Schema::connection('tenant')->hasColumn('offers', 'payment_details')) {
                $table->text('payment_details')->nullable()->after('currency');
            }
        });

        // 2. Poblamos los UUIDs para los registros que ya existen
        $this->populateMissingUuids();

        // 3. Aplicamos las restricciones de integridad y cambios de ENUM
        getModuleSchema()->table('offers', function ($table) {
            // Ponemos el UUID como único y obligatorio ahora que todos tienen uno
            $table->uuid('uuid')->nullable(false)->unique()->change();

            // Ajustamos la precisión de amount (si decides subir a 18 decimales luego, cambia el 4 por 18)
            $table->decimal('amount', 20, 4)->change();

            // Actualizamos el ENUM de status
            // NOTA: En MySQL, cambiar un ENUM con datos puede ser caprichoso. 
            // Si el driver falla, se puede usar DB::statement (ver abajo).
            $table->enum('status', [
                'locked',
                'disputed',
                'completed',
                'cancelled',
                'signed'
            ])->default('locked')->change();
        });
    }

    /**
     * Rellena los UUIDs vacíos en los registros existentes.
     */
    private function populateMissingUuids()
    {
        DB::connection('tenant')->table('offers')->whereNull('uuid')->chunkById(100, function ($offers) {
            foreach ($offers as $offer) {
                DB::connection('tenant')->table('offers')
                    ->where('id', $offer->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }
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
            // Para revertir, primero quitamos el índice único
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'payment_details']);

            // Revertimos el ENUM al estado anterior si es necesario
            $table->enum('status', [
                'active',
                'locked',
                'disputed',
                'completed',
                'cancelled',
                'paused'
            ])->default('active')->change();
        });
    }
}
