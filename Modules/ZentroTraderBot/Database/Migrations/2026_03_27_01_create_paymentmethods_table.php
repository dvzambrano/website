<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePaymentmethodsTable extends Migration
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
        getModuleSchema()->create('paymentmethods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');       // Zelle, Bizum, Transferencia, Enzona
            $table->string('identifier'); // identificador único (slug)
            $table->string('icon')->nullable(); // Para el futuro (emoji o url)
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
        getModuleSchema()->dropIfExists('paymentmethods');
    }
}
