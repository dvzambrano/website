<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTelegramBotsTable extends Migration
{
    protected $connection = 'TelegramBot';
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->down();
        getModuleSchema()->create('bots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();

            // Identificador para la URL (Clave "Pública" de la ruta)
            $table->uuid('key')->unique()->index();

            // Token para el Header (Clave "Privada" de validación)
            $table->string('secret')->nullable();

            $table->string('module')->nullable();

            // Datos de conexión dinámicos
            $table->string('database');
            $table->string('username');
            $table->string('password');

            $table->longtext('token');
            $table->jsonb('data');

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
        getModuleSchema()->dropIfExists('bots');
    }
}
