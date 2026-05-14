<?php

use Illuminate\Database\Migrations\Migration;

class CreateOffersRatingsTable extends Migration
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
        getModuleSchema()->create('offers_ratings', function ($table) {
            $table->id();
            $table->unsignedBigInteger('offer_id')->index();
            $table->unsignedBigInteger('rater_user_id')->index(); // El que pone la nota
            $table->unsignedBigInteger('rated_user_id')->index(); // El que la recibe
            $table->tinyInteger('stars')->unsigned(); // 1 a 5
            $table->text('comment')->nullable();
            $table->timestamps();
            // Un usuario solo puede calificar una oferta una vez
            $table->unique(['offer_id', 'rater_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        getModuleSchema()->dropIfExists('offers_ratings');
    }
}
