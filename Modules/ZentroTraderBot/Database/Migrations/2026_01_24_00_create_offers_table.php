<?php

use Illuminate\Database\Migrations\Migration;

class CreateOffersTable extends Migration
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
        getModuleSchema()->create('offers', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Creador del anuncio

            // --- Datos Económicos ---
            $table->enum('type', ['buy', 'sell']);  // ¿El usuario quiere comprar o vender USD? 
            $table->decimal('amount', 20, 4); // Cantidad total disponible. IMPORTANTE: Cambia a 18 decimales para precisión cripto
            $table->decimal('min_limit', 20, 4);       // Compra mínima (ej: $10)
            $table->decimal('price_per_usd', 16, 2);   // Precio (ej: 1.05 si cobras recargo)
            $table->string('payment_method');          // Zelle, Bizum, Transf. Local
            $table->string('currency')->default('USD');

            // --- Estados Sincronizados con Solidity ---
            // Añadimos: LOCKED (en curso), DISPUTED (litigio)
            $table->enum('status', [
                'active',    // ACTIVE en contrato (esperando buyer o pago FIAT)
                'locked',    // LOCKED en contrato (comprador aplicó)
                'disputed',  // DISPUTED en contrato
                'completed', // COMPLETED (fondos liberados)
                'cancelled', // CANCELLED (fondos devueltos)
                'paused'     // Solo local (oculto en el bot)
            ])->default('active');

            // --- Identificación en Blockchain ---
            $table->unsignedBigInteger('blockchain_trade_id')->nullable()->index();
            $table->integer('network_id')->default(137); // Polygon por defecto
            $table->string('token_address')->nullable(); // Contrato del token (USDC, MATIC, etc.)

            // --- Actores del P2P ---
            $table->string('seller_address')->nullable()->index(); // Wallet del que deposita
            $table->string('buyer_address')->nullable()->index();  // Wallet del que recibe

            // --- Auditoría ---
            $table->string('tx_hash_deposit')->nullable();  // Hash del createTrade
            $table->string('tx_hash_release')->nullable();  // Hash de la liberación/resolución
            $table->timestamps();

            $table->index(['type', 'status', 'blockchain_trade_id', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        getModuleSchema()->dropIfExists('offers');
    }
}
