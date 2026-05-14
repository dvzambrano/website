<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCurrencypaymentmethodsTable extends Migration
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
        getModuleSchema()->create('currencypaymentmethods', function (Blueprint $table) {
            $table->bigIncrements('id');

            // CORRECCIÓN: Apuntar a los nombres de tabla correctos
            $table->foreignId('currency_id')->constrained('currencies')->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained('paymentmethods')->onDelete('cascade');

            $table->decimal('min_limit', 15, 2)->default(0.00);
            $table->decimal('max_limit', 15, 2)->default(999999.99);
            $table->text('instructions')->nullable();
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
        getModuleSchema()->dropIfExists('currencypaymentmethods');
    }
}
