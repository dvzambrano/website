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
        getModuleSchema()->table('offers', function ($table) {
            // Verificamos si la columna existe antes de intentar borrarla
            if (Schema::connection('tenant')->hasColumn('offers', 'min_limit')) {
                $table->dropColumn('min_limit');
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
            // Revertimos el cambio añadiendo la columna de nuevo por si necesitamos volver atrás
            $table->decimal('min_limit', 20, 4)->after('amount')->nullable();
        });
    }
}
