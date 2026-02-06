<?php

use Illuminate\Database\Migrations\Migration;

class CreateRampordersTable extends Migration
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
        getModuleSchema()->create('ramporders', function ($table) {
            $table->id();
            $table->string('order_id')->unique(); // El ID de Transak
            $table->unsignedBigInteger('bot_id');
            $table->unsignedBigInteger('user_id'); // ID del usuario en tu sistema
            $table->decimal('amount', 18, 8); // Cantidad de crypto
            $table->string('currency')->default('USDC');
            $table->string('status'); // COMPLETED, FAILED, PENDING
            $table->json('raw_data')->nullable(); // Guardamos todo el JSON por si acaso
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
        getModuleSchema()->dropIfExists('ramporders');
    }
}
