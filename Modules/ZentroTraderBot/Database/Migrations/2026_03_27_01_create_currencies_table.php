<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCurrenciesTable extends Migration
{
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->down();
        getModuleSchema()->create('currencies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique(); // USD, EUR, CUP, MLC
            $table->string('name');                // Euro, Peso Cubano
            $table->string('symbol', 10);          // €, ₱, $
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        getModuleSchema()->dropIfExists('currencies');
    }
}
